<?php

namespace Vault_Client;

class VaultClient {

	protected $url;
	protected $key;
	protected $secret;

	public function __construct( $url, $key, $secret ) {
		$this->url = $url;
		$this->key = $key;
		$this->secret = $secret;
	}

	protected function call( $name, array $args ) {

		$args_json = json_encode($args);
		$timestamp = time();

		$postdata = [
			'n' => $name,
			'a' => $args_json,
		];

		$ch = curl_init();

		curl_setopt( $ch, CURLOPT_URL, $this->url . "/request" );
		curl_setopt( $ch, CURLOPT_POST, TRUE );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
		curl_setopt( $ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $postdata );
		curl_setopt( $ch, CURLOPT_USERPWD, $this->key . ':' . $this->secret );

		$error = NULL;
		if ( curl_exec( $ch ) ) {
			$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			if ( $code != 200 ) {
				$error = "$this->url returned HTTP $code";
			}
		} else {
			$error = curl_error( $ch );
		}
		curl_close( $ch );

		if ( $error ) {
			throw new \RuntimeException( $error );
		}

		return json_decode($res);
	}

	public function add_request( $email, $app_data = NULL, $instructions = NULL ) {
		return $this->call( 'request',
		                    [
			                    'app_data' => $app_data,
			                    'instructions' => $instructions,
		                    ] );
	}
}
