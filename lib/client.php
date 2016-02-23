<?php

namespace Vault_Client;

class VaultClientException extends \Exception {}

class VaultClient {

	protected $url;
	protected $key;
	protected $secret;

	const INSTRUCTIONS_ALLOWED_TAGS = '<p><br><b><i><strong><em><ul><ol><li>';

	static public function esc_instructions( $instructions ) {
		return strip_tags( $instructions,
		                   VaultClient::INSTRUCTIONS_ALLOWED_TAGS );
	}

	public function __construct( $url, $key, $secret ) {
		$this->url = $url;
		$this->key = $key;
		$this->secret = $secret;
	}

	protected function call( $name, array $args ) {

		$ch = curl_init();

		curl_setopt( $ch, CURLOPT_URL, $this->url . "/" . $name);
		curl_setopt( $ch, CURLOPT_POST, TRUE );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
		curl_setopt( $ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $args );
		curl_setopt( $ch, CURLOPT_USERPWD, $this->key . ':' . $this->secret );

		$res = curl_exec( $ch );

		$error = NULL;
		if ( $res ) {
			$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			if ( $code != 200 ) {
				$error = "$this->url returned HTTP $code";
			}
		} else {
			$error = curl_error( $ch );
		}
		curl_close( $ch );

		if ( $error ) {
			throw new VaultClientException( $error );
		}

		// FIXME: handle JSON decoding errors
		return json_decode( $res, TRUE );
	}

	public function add_request( $email, $instructions = NULL, $app_data = NULL ) {
		return $this->call( 'request',
		                    [
			                    'email' => $email,
			                    'instructions' => $instructions,
			                    'app_data' => $app_data,
		                    ] );
	}
}
