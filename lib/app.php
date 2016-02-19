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
	}

	protected function display_page( $title, $contents ) {
		$view = $this->views->get('page');
		$view->set('title', $title);
		$view->set('contents', $contents);

		$this->response->content->set($view);
	}


	protected function handle_request_form() {
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
			$this->log->addNotice( 'Not found' );
			$this->handle_not_found();
		}

		$this->send_response();
	}
}
