<?php

namespace Vault_Client;

class ForbiddenException extends \Exception {}
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
	protected $user;

	public function __construct($name) {
		// Workaround '_SERVER' not present in $GLOBALS, unless
		// referenced before (see
		// https://bugs.php.net/bug.php?id=65223).
		$_SERVER;

		$web_factory = new \Aura\Web\WebFactory( $GLOBALS );
		$this->request = $web_factory->newRequest();
		$this->response = $web_factory->newResponse();

		$this->response->content->setType( 'text/html' );
		$this->response->content->setCharset( 'utf-8' );

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
		$this->response->status->setCode( 500 );
		$this->log->addError( $ex->getMessage(), [ 'exception' => $ex ] );
		$view = $this->views->get( 'exception' );
		$this->display_page( __( 'Oops..'), $view );
	}

	protected function init_router() {
		$this->router->addGet( 'request', '/' );
		$this->router->addPost( 'request#submission', '/' );
		$this->router->addGet( 'confirm', '/confirm' );
		$this->router->addPost( 'confirm#submission', '/confirm' );
		$this->router->addGet( 'logout', '/logout' );

		// We do not require a user to be logged in on the following
		// paths
		$this->router->addPost( 'ping', '/ping' )
			->addValues(  [ '_skip_login_check' => TRUE ] );

		$this->router->addGet( 'auth', '/auth' )
			->addValues( [ '_skip_login_check' => TRUE ] );
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
		$view->set('user', $this->user);

		$this->response->content->set($view);
	}

	protected function new_client() {
		$url = $this->get_conf('api', 'url');
		$key = $this->get_conf('api', 'key');
		$secret = $this->get_conf('api', 'secret');

		if ( !$url ) {
			throw new \RuntimeException( 'No API URL in config.ini' );
		}
		if ( !$key ) {
			throw new \RuntimeException( 'No API key in config.ini' );
		}
		if ( !$secret ) {
			throw new \RuntimeException( 'No API secret in config.ini' );
		}

		return new VaultClient($url, $key, $secret);
	}

	protected function get_request_form() {
		$form = $this->views->get( 'request-form' );
		return $form;
	}

	protected function get_confirm_token( $timestamp, $req_email,
	                                      $user_email, $instructions ) {
		return strtr(
			base64_encode(
				hash_hmac(
					'sha1',
					"$timestamp $req_email $user_email $instructions",
					$this->get_conf( 'api', 'secret' ),
					TRUE
				) ),
			[
				'+' => '-',
				'/' => '.',
				'=' => '',
			]);
	}

	protected function display_login_page() {
		$wpcc_state = base64_encode( openssl_random_pseudo_bytes( 16 ) );
		$this->session->setFlash( 'wpcc_state', $wpcc_state );

		$url_to = $this->get_conf( 'oauth', 'authenticate_url' )
			. '?'
			. http_build_query(
				[
					'response_type' => 'code',
					'client_id' => $this->get_conf( 'oauth',
					                                'client_id' ),
					'state' => $wpcc_state,
					'redirect_uri' => $this->get_conf( 'oauth',
					                                   'redirect_url' ),
				] );

		$this->display_page( __( 'Vault log in' ),
		                     '<a id="login-button" href="' . $url_to . '"><img src="//s0.wp.com/i/wpcc-button.png" width="231"></a>' );
	}

	protected function log_in( User $user ) {
		$this->log->addInfo( $user->email . ' logged in' );
		$this->session->set( 'user', $user );
		$this->user = $user;
	}

	protected function handle_auth() {

		// No matter what, we always redirect to the request page
		$this->response->redirect->to( $this->router->generate( 'request' ) );

		$code = $this->request->query->get( 'code' );
		if ( ! $code ) {
			$this->flashError( __( 'You must login to access this system.' ) );
			return;
		}

		if ( ! hash_equals( $this->session->getFlash( 'wpcc_state' ), $this->request->query->get( 'state' ) ) ) {
			$this->flashError( __( 'Invalid request.' ) );
			return;
		}

		$postfields = [
			'client_id' => $this->get_conf( 'oauth', 'client_id' ),
			'redirect_uri' => $this->get_conf( 'oauth', 'redirect_url' ),
			'client_secret' => $this->get_conf( 'oauth', 'client_secret' ),
			'code' => $code,
			'grant_type' => 'authorization_code'
		];

		$ch = curl_init( $this->get_conf( 'oauth', 'request_token_url' ) );
		curl_setopt( $ch, CURLOPT_POST, TRUE );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $postfields );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
		$auth = curl_exec( $ch );

		$secret = json_decode( $auth, TRUE );

		if ( empty( $secret[ 'access_token' ] ) ) {
			throw new \RuntimeException( 'No access token was returned from OAauth' );
		}

		$ch = curl_init( 'https://public-api.wordpress.com/rest/v1/me/' );
		curl_setopt( $ch, CURLOPT_HTTPHEADER,
		             [ 'Authorization: Bearer ' . $secret[ 'access_token' ] ] );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
		$res = curl_exec( $ch );
		$user = json_decode( $res, TRUE );

		if ( empty( $user[ 'verified' ] ) ) {
			$this->flashError( __('You need to verify your e-mail in WordPress.com before logging in here.') );
			return;
		}

		$this->log_in( new User( $user[ 'ID' ],
		                         $user[ 'email' ],
		                         $user[ 'display_name' ] ) );
	}

	protected function check_user() {
		$this->user = $this->session->get( 'user' );
		if ( ! $this->user ) {
			$this->display_login_page();
			throw new ForbiddenException();
		}
	}

	protected function handle_request_form() {
		$this->display_page( __( 'Send a Vault Request' ),
		                     $this->get_request_form() );
	}

	protected function handle_request_form_submission() {
		$req_email = $this->request->post->get( 'req-email' );
		$user_email = $this->request->post->get( 'user-email' );
		$instructions = $this->request->post->get( 'instructions' );

		$errors = [];
		if ( empty( $req_email) || ! Valid::email( $req_email ) ) {
			$errors[ 'req_email' ] = 'Input a valid e-mail address.';
		}

		if ( empty( $user_email) || ! Valid::email( $user_email ) ) {
			$errors[ 'user_email' ] = 'Input a valid e-mail address.';
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

			$this->display_page( __( 'Send a Vault Request' ), $form );
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
		$mailer->Subject = 'A Vault request awaits your confirmation';
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
			$this->log->addNotice( 'Invalid session state' );
			$this->response->redirect->to(
				$this->router->generate( 'request' ) );
			return;
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

		$this->display_page( __( 'Request Confirmation' ), $form );
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
			$this->flashError( __( 'The confirmation token you entered is not valid.' ) );
			$this->response->redirect->afterPost(
				$this->router->generate( 'confirm' ) );
			return;
		}

		$client = $this->new_client();

		$res = $client->add_request( $user_email, $instructions, $req_email );

		$this->flashInfo( __( '<p>The request was sent.</p><p>You will receive an e-mail when the user submits the information requested.</p>' ) );

		$this->response->redirect->afterPost(
			$this->router->generate( 'request' ) );
	}

	protected function handle_ping_submission( array $args ) {

		$body = $this->views->get('email-unlock');
		$body->set( 'reqid', $args[ 'reqid' ] );
		$body->set( 'unlock_url', $args[ 'unlock_url' ] );
		$body->set( 'unlock_key', $args[ 'unlock_key' ] );

		$mailer = new Mailer( $this->conf, $this->log );
		$mailer->addAddress( $args[ 'app_data' ] );
		$mailer->Subject = 'The information you requested is now available';
		$mailer->Body = (string) $body;

		$mailer->send();

	}

	protected function handle_ping() {
		$subject = $this->request->post->get('s');
		$payload = $this->request->post->get('p');
		$mac = $this->request->post->get('m');

		$known_mac = hash_hmac( 'sha1',  "$subject $payload",
		                        $this->get_conf( 'api', 'vault_secret' ),
		                        TRUE );
		if ( ! hash_equals( $known_mac, $mac ) ) {
			throw new NotFoundException( 'Could not authenticate ping' );
		}

		$args = json_decode( $payload, TRUE );

		switch ( $subject ) {
		case 'submission':
			$this->handle_ping_submission( $args );
			break;

		default:
			throw new NotFoundException("Unsupported ping subject '$subject'");
		}
	}

	protected function handle_logout() {
		$this->session->set( 'user', NULL );
		$this->flashInfo( __( 'You have successfully logged out.') );
		$this->response->redirect->to( $this->router->generate( 'request' ) );
	}

	protected function handle_request() {
		$path = $this->request->url->get( PHP_URL_PATH );
		$route = $this->router->match( $path, $this->request->server->get() );
		if ( ! $route ) {
			throw new NotFoundException($path);
		}

		if ( empty( $route->params[ '_skip_login_check' ] ) ) {
			$this->check_user();
		}

		switch ( $route->params['action'] ) {

		case 'auth':
			$this->handle_auth();
			break;

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

		case 'ping':
			$this->handle_ping();
			break;

		case 'logout':
			$this->handle_logout();
			break;

		default:
			throw new \RuntimeException( "Invalid action: "
			                             . $route->params[ 'action' ] );
		}
	}

	protected function handle_not_found() {
		$this->response->status->setCode(404);
		$this->session->setFlashNow( 'messages', [] );
		$this->display_page( __( 'Page not found' ),
		                     __( "Sorry, the page you were looking for doesn't exist or has been moved." ) );
	}

	protected function handle_forbidden() {
		$this->response->status->setCode(403);
		$this->session->setFlashNow( 'messages', [] );
	}

	protected function prepare_response() {
		$type = $this->response->content->getType();
		$charset = $this->response->content->getCharset();

		$this->response->headers->set( 'Content-Type',
									   "$type; charset=\"$charset\"" );
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
		} catch ( ForbiddenException $ex ) {
			$this->log->addNotice( 'Forbidden' );
			$this->handle_forbidden();
		} catch ( \Exception $ex ) {
			$this->handle_exception( $ex );
		}

		$this->prepare_response();
		$this->send_response();
	}
}
