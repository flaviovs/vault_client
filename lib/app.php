<?php

namespace Vault_Client;

class NotFoundException extends \Exception {}

class App {
	protected $request;
	protected $response;
	protected $router;
	protected $conf;
	protected $session;
	protected $log;
	protected $views;
	protected $messages;

	public function __construct($name) {

		$web_factory = new \Aura\Web\WebFactory( $GLOBALS );
		$this->request = $web_factory->newRequest();
		$this->response = $web_factory->newResponse();

		$router_factory = new \Aura\Router\RouterFactory();
		$this->router = $router_factory->newInstance();

		$this->conf = [];

		$session_factory = new \Aura\Session\SessionFactory;
		$session = $session_factory->newInstance(
			$this->request->cookies->get() );
		$this->session = $session->getSegment( __CLASS__ );

		$this->log = new \Monolog\Logger($name);

		$this->views = new \UView\Registry( __DIR__ . '/../view' );

		$this->messages = new MessageArea();

	}

	protected function get_conf( $section, $key, $default = NULL ) {
		return ( ! empty( $this->conf[ $section ])
		         && array_key_exists($key, $this->conf[ $section ] ) ) ?
			$this->conf[ $section ][ $key ] : $default;
	}

	protected function init_logging() {
		$handler = new \Monolog\Handler\ErrorLogHandler();
		$handler->setFormatter(
			new \Monolog\Formatter\LineFormatter(
				"[%level_name%] %channel%: %message% %context% %extra%\n"));
		$this->log->setHandlers([$handler]);
		$this->log->pushProcessor(new \Monolog\Processor\WebProcessor());
	}

	protected function load_config() {
		$this->conf = parse_ini_file( __DIR__ . '/../config.ini', TRUE );
	}

	protected function handle_exception( \Exception $ex ) {
		$this->log->addError( $ex );
		echo "oops";
	}

	protected function init_router() {
		$this->router->addGet( 'request', '/' );
		$this->router->addPost( 'request#submission', '/' );
		$this->router->addGet( 'confirm', '/confirm' );
		$this->router->addPost( 'confirm#submission', '/confirm' );
	}


	protected function flashMessage($msg, $level) {
		$messages = $this->session->getFlashNext( 'messages', [] );
		if ( ! array_key_exists($level, $messages) ) {
			$messages[ $level ] = [];
		}
		$messages[ $level ][] = $msg;
		$this->session->setFlash( 'messages', $messages );
	}

	protected function flashInfo($msg) {
		$this->flashMessage( $msg, MessageArea::INFO );
	}

	protected function flashError($msg) {
		$this->flashMessage( $msg, MessageArea::ERROR );
	}

	protected function display_page( $title, $contents ) {
		$view = $this->views->get('page');

		foreach ( $this->session->getFlash( 'messages', [] ) as $level => $msgs ) {
			foreach ( $msgs as $msg ) {
				$this->messages->addMessage( $level, $msg );
			}
		}

		$view->set('messages', (string) $this->messages);
		$view->set('title', $title);
		$view->set('contents', $contents);

		$this->response->content->set($view);
	}

	protected function new_client() {
		$url = $this->get_conf('vault', 'url');
		$key = $this->get_conf('vault', 'key');
		$secret = $this->get_conf('vault', 'secret');

		if ( !$url ) {
			throw new \RuntimeException( 'No Vault URL in config.ini' );
		}
		if ( !$key ) {
			throw new \RuntimeException( 'No Vault key in config.ini' );
		}
		if ( !$secret ) {
			throw new \RuntimeException( 'No Vault secret in config.ini' );
		}

		return new VaultClient($url, $key, $secret);
	}

	protected function get_request_form() {
		$form = $this->views->get( 'request-form' );
		return $form;
	}

	protected function get_confirm_token( $timestamp, $req_email,
	                                      $user_email, $instructions ) {
		return base64_encode(
			hash_hmac(
				'sha1',
				"$timestamp $req_email $user_email $instructions",
				$this->get_conf( 'vault', 'secret' ),
				TRUE
			) );
	}

	protected function handle_request_form() {
		$this->display_page( 'Request a secret', $this->get_request_form() );
	}

	protected function handle_request_form_submission() {
		$req_email = $this->request->post->get( 'req-email' );
		$user_email = $this->request->post->get( 'user-email' );
		$instructions = $this->request->post->get( 'instructions' );

		$errors = [];
		if ( empty( $req_email) || ! Valid::email( $req_email ) ) {
			$errors[ 'req_email' ] = 'You need to provide a valid e-mail address.';
		}

		if ( empty( $user_email) || ! Valid::email( $user_email ) ) {
			$errors[ 'user_email' ] = 'You need to provide a valid e-mail address.';
		}

		if ( $errors ) {
			$form = $this->get_request_form();

			$form->set( 'req_email', $req_email );
			$form->set( 'user_email', $user_email );
			$form->set( 'instructions', $instructions );

			$form->set( 'req_email_error',
			            isset( $errors[ 'req_email' ] ) ?
			            $errors[ 'req_email' ] : NULL );
			$form->set( 'user_email_error',
			            isset( $errors[ 'user_email' ] ) ?
			            $errors[ 'user_email' ] : NULL );

			$this->display_page( 'Request a secret', $form );
			return;
		}

		$timestamp = microtime( TRUE );

		$body = $this->views->get( 'email-confirm' );
		$body->set( 'token', $this->get_confirm_token( $timestamp,
		                                               $req_email,
		                                               $user_email,
		                                               $instructions ) );

		$mailer = new Mailer( $this->conf, $this->log );
		$mailer->addAddress( $req_email );
		$mailer->Subject = 'A secret request awaits your confirmation';
		$mailer->Body = (string) $body;

		$mailer->send();

		$this->session->setFlash( 'timestamp', $timestamp );
		$this->session->setFlash( 'req_email', $req_email );
		$this->session->setFlash( 'user_email', $user_email );
		$this->session->setFlash( 'instructions', $instructions );

		$this->response->redirect->afterPost(
			$this->router->generate( 'confirm' )
		);
	}

	protected function handle_confirm() {
		$timestamp = $this->session->getFlash( 'timestamp' );
		$req_email = $this->session->getFlash( 'req_email' );
		$user_email = $this->session->getFlash( 'user_email' );
		$instructions = $this->session->getFlash( 'instructions' );

		if ( empty( $timestamp ) || empty( $req_email ) || empty( $user_email ) ) {
			// Should never happen, but let's stay on the safe side here.
			throw new NotFoundException( 'Missing session variables' );
		}

		if ( (time() - $timestamp) < 120 ) {
			// Keep the request variables in session for at least 2 minutes.
			$this->session->keepFlash();
		}

		$form = $this->views->get('confirm');

		$form->set( 'action', $this->router->generate( 'confirm#submission' ) );
		$form->set( 'timestamp', $timestamp );
		$form->set( 'req_email', $req_email );
		$form->set( 'user_email', $user_email );
		$form->set( 'instructions',
		            VaultClient::esc_instructions( $instructions ) );

		$this->display_page('Confirmation', $form);
	}

	protected function handle_confirm_submission() {
		$timestamp = $this->request->post->get( 'timestamp' );
		$req_email = $this->request->post->get( 'req_email' );
		$user_email = $this->request->post->get( 'user_email' );
		$instructions = $this->request->post->get( 'instructions' );

		$token = $this->get_confirm_token( $timestamp,
		                                   $req_email,
		                                   $user_email,
		                                   $instructions );

		if ( ! hash_equals( $this->request->post->get('token'), $token ) ) {
			$this->session->setFlash( 'timestamp', $timestamp );
			$this->session->setFlash( 'req_email', $req_email );
			$this->session->setFlash( 'user_email', $user_email );
			$this->session->setFlash( 'instructions', $instructions );
			$this->flashError('The confirmation token is not valid.');
			$this->response->redirect->afterPost(
				$this->router->generate( 'confirm' ) );
			return;
		}

		$client = $this->new_client();

		$res = $client->add_request( $user_email, $instructions, $req_email );

		$this->flashInfo('<p>The request was registered, and an e-mail sent to the user.</p><p>You will receive an e-mail when the user submits the information you requested.</p>');

		$this->response->redirect->afterPost(
			$this->router->generate( 'request' ) );
	}

	protected function handle_request() {
		$path = $this->request->url->get( PHP_URL_PATH );
		$route = $this->router->match( $path, $this->request->server->get() );
		if ( ! $route ) {
			throw new NotFoundException($path);
		}

		switch ( $route->params['action'] ) {

		case 'request':
			$this->handle_request_form();
			break;

		case 'request#submission':
			$this->handle_request_form_submission();
			break;

		case 'confirm':
			$this->handle_confirm();
			break;

		case 'confirm#submission':
			$this->handle_confirm_submission();
			break;

		default:
			throw new \RuntimeException( "Invalid action: "
			                             . $route->params[ 'action' ] );
		}
	}

	protected function handle_not_found() {
		$this->response->status->setCode(404);
		$this->display_page( 'Page not found',
		                     "Sorry, the page you were looking for doesn't exist or has been moved." );
	}

	protected function send_response() {
		header( $this->response->status->get(),
		        true,
		        $this->response->status->getCode() );

		foreach ( $this->response->headers->get() as $label => $value ) {
			header( "{$label}: {$value}" );
		}

		foreach ( $this->response->cookies->get() as $name => $cookie ) {
			setcookie( $name,
			           $cookie['value'],
			           $cookie['expire'],
			           $cookie['path'],
			           $cookie['domain'],
			           $cookie['secure'],
			           $cookie['httponly'] );
		}

		echo $this->response->content->get();
	}

	public function run() {
		try {
			$this->init_logging();
			$this->load_config();
			$this->init_router();
			$this->handle_request();
		} catch ( NotFoundException $ex ) {
			$this->log->addNotice( 'Not found (' . $ex->getMessage() . ')' );
			$this->handle_not_found();
		}

		$this->send_response();
	}
}
