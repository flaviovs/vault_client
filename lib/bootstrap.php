<?php

namespace Vault_Client;

/**
 * Stub translation procedure.
 */
function __( $text ) {
	return $text;
}

/**
 * Our error handler
 */
function error_handler( $severity, $message, $file, $line ) {
	if ( error_reporting() & $severity ) {
		throw new \ErrorException( $message, 0, $severity, $file, $line );
	}
}

error_reporting( E_ALL );

set_error_handler( __NAMESPACE__ . '\\error_handler' );
