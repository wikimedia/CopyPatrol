# CopyPatrol
This is a web interface for [Plagiabot's Copyright RC feed](https://en.wikipedia.org/wiki/User:EranBot/Copyright/rc).

[![Build Status](https://travis-ci.org/wikimedia/CopyPatrol.svg?branch=master)](https://travis-ci.org/Niharika29/PlagiabotWeb)

#### To test locally:
1. Run `composer install` and `composer update` after cloning the repository
2. Setup your .env file with the following params:
	* OAUTH_CONSUMER_TOKEN
	* OAUTH_SECRET_TOKEN
	* OAUTH_ENDPOINT
	* OAUTH_REDIR
	* OAUTH_CALLBACK
	* DB_DSN_PLAGIABOT
	* DB_DSN_ENWIKI
	* DB_USER
	* DB_PASS
3. Rewrite your routing locally, if needed

This application makes of use the [Wikimedia-slimapp](https://github.com/wikimedia/wikimedia-slimapp) library and uses Twig as its templating engine.

##### To add a new translation message:
1. Add it to en.json
2. Update the qqq.json documentation accordingly
3. Call it in Twig as `{{ '<message-key>'|message }}`. If the message contains any HTML, you'll need to append the `|raw` filter after `message`.
4. To use a translation message in JavaScript, add it as a global variable in `templates/base.html`. Then simply access it in the JS.
5. To get a message in PHP, use `$this->i18nContext->message( '<message-key>' )`



