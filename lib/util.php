<?php

namespace Vault_Client;

class Valid {
	static public function email( $email ) {
		return filter_var( $email, FILTER_VALIDATE_EMAIL );
	}
}
