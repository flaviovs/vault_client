The Vault reference client app
==============================

This is the reference client for the Vault system. It allows one to
setup a system to securely exchange secret information (such as
passwords, API keys, etc.) between two parties (e.g., an Happiness
Engineer and a support user).

This app is only a thin client to the Vault engine, which is the
ultimate tool that do secure interactions with users and secret
storage.

For more information about the Vault engine, visit
https://github.com/flaviovs/vault


Requirements
============

* A web server with support for PHP >= 5.6

* The following PHP extensions: openssl, curl

* A WordPress.com OAuth2 app ID and secret

* A Vault API key, secret, and Vault secret



Installation
============

Installing the client app is straightforward. Here's the steps:

1. Run `composer install` in the project root to bring in all the
dependencies.

2. `cp config.ini.dist config.ini`

3. Edit config.ini and edit/review the settings.

4. Setup a web address in your web server. Point the document root to
   the `www/` directory.
