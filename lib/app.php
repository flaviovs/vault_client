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
	}

	protected function handle_request() {
	}

	protected function handle_not_found() {
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
			$this->log->addNotice( $ex->getMessage() );
			$this->handle_not_found();
		}

		$this->send_response();
	}
}
