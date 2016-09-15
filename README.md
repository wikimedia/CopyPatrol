# CopyPatrol
This is a web interface for [Plagiabot's Copyright RC feed](https://en.wikipedia.org/wiki/User:EranBot/Copyright/rc).

[![Build Status](https://travis-ci.org/Niharika29/PlagiabotWeb.svg?branch=master)](https://travis-ci.org/Niharika29/PlagiabotWeb)

#### To test locally:
1. Run `composer install` and `composer update` after cloning the repository
2. Setup your .env file with the following params:
	* OAUTH_CONSUMER_TOKEN
	* OAUTH_SECRET_TOKEN
	* OAUTH_ENDPOINT
	* OAUTH_REDIR
	* OAUTH_CALLBACK
	* DB_DSN_PLAGIABOT
	* DB_DSN_WIKIPROJECT
	* DB_DSN_ENWIKI
	* DB_USER
	* DB_PASS
3. Rewrite your routing locally, if needed

This application makes of use [Wikimedia-slimapp](https://github.com/wikimedia/wikimedia-slimapp) library and uses Twig as its templating library.

##### To add a new translation message:
1. Add it to en.json
2. Call it in Twig as <code>{{ '<message-key>'|message }}</code>. If the message contains any html, you need to add the <code>|raw</code> param after 'message'.
3. To use a translation message in JS, add it as a JS var in templates/base.html. Then simply access it in JS.
4. To call it in PHP:
	<code>$this->i18nContext->message( '<message-key>' )</code>



