<?php

namespace Vault_Client;

require __DIR__ . '/../vendor/autoload.php';

function main() {
	$app = new App( 'vault-client' );
	$app->run();
}

main();
