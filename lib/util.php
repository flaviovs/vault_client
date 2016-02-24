<?php

namespace Vault_Client;

class Valid {
	static public function email( $email ) {
		return filter_var( $email, FILTER_VALIDATE_EMAIL );
	}
}

class Mailer extends \PHPMailer {
	protected $debug;
	protected $log;

	public function __construct(array $conf, \Monolog\Logger $log) {
		parent::__construct( TRUE ); // Tell PHPMailer that we want exceptions.

		if ( ! isset( $conf[ 'mailer' ] ) ) {
			throw new \RuntimeException('No mailer configuration found');
		}

		$conf = $conf[ 'mailer' ];

		if ( empty( $conf[ 'from_address' ] ) ) {
			throw new \RuntimeException('Missing from_address mailer configuration');
		}

		if ( empty( $conf[ 'from_name' ] ) ) {
			throw new \RuntimeException('Missing from_name mailer configuration');
		}

		$this->debug = ! empty( $conf[ 'debug' ] );

		if ( $this->debug ) {
			$this->Mailer = 'debug';
		}

		$this->setFrom( $conf[ 'from_address' ], $conf[ 'from_name' ] );

		$this->log = $log;
	}

	protected function debugSend($headers, $body) {
		$this->log->addDebug('Omitting email to '
		                     . implode( ',',
		                                array_keys( $this->all_recipients ) ),
		                     [
			                     'headers' => $headers,
			                     'body' => $body,
		                     ]);
		return TRUE;
	}
}

class MessageArea {
	const INFO = 0;
	const ERROR = 1;

	protected $messages = [
		MessageArea::INFO => [],
		MessageArea::ERROR => [],
	];

	public function addMessage($level, $msg) {
		$this->messages[$level][] = $msg;
	}

	public function getMessagesList($level) {
		switch ( count( $this->messages[$level] ) ) {
		case 0:
			return '';

		case 1:
			return $this->messages[$level][0];
		}

		$list = "<ul>\n";
		foreach ( $this->messages[$level] as $msg ) {
			$list .= "<li>$msg</li>\n";
		}
		$list .= "</ul>\n";

		return $list;
	}

	public function __toString() {
		$info = $this->getMessagesList(static::INFO);
		$error = $this->getMessagesList(static::ERROR);

		$out = '';

		if ( $info ) {
			$out .= '<div class="info">' . $info . "</div>\n";
		}

		if ( $error ) {
			$out .= '<div class="error">' . $error . "</div>\n";
		}

		return $out;
	}
}


class User {
	public $ID;
	public $email;
	public $name;

	public function __construct($id, $email, $name) {
		$this->ID = $id;
		$this->email = $email;
		$this->name = $name;
	}
}
