<?php

namespace Vault_Client;

class Valid {
	static public function email( $email ) {
		return filter_var( $email, FILTER_VALIDATE_EMAIL );
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
