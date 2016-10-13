# CopyPatrol
This is a web interface for [Plagiabot's Copyright RC feed](https://en.wikipedia.org/wiki/User:EranBot/Copyright/rc).

[![Build Status](https://travis-ci.org/wikimedia/CopyPatrol.svg?branch=master)](https://travis-ci.org/wikimedia/CopyPatrol)

## To install locally
1. Clone the repository and run `composer install`.
2. Edit the `.env` file that was created by composer.
   1. Get Oauth tokens by registering a new consumer on Meta
      at [Special:OAuthConsumerRegistration](https://meta.wikimedia.org/wiki/Special:OAuthConsumerRegistration).
   2. To use Redis caching, also add `REDIS_HOST` and `REDIS_PORT`;
      without these, a local filesystem cache will be used
      (in the `cache/` directory, which you may have to create);
5. Rewrite your routing, if needed.
   For Lighttpd, use this in your `.lighttpd.conf`:
   ```
   url.rewrite-if-not-file += ( "(.*)" => "/copypatrol/index.php/$0" )
   ```
   Or for Apache, this (in `.htaccess` at the root of the project):
   ```
   DirectorySlash Off
   RewriteEngine On
   RewriteCond %{REQUEST_FILENAME} !-f
   RewriteRule ^public_html(.*)$ public_html/index.php$1 [L]
   ```
6. Open up an SSH tunnel to access the databases on Tool Labs (substitute your own username).
   ```
   $ ssh -L 4711:enwiki.labsdb:3306 YOU@tools-login.wmflabs.org -N 
   ```
7. Set up a local copy of the 'copyright' database (substitute your own usernames):
   ```
   $ mysqldump -u LABSYOU -p -P 4711 -h 127.0.0.1 --skip-lock-tables s51306__copyright_p > copyright.sql
   $ mysql -u LOCALYOU -p -e "CREATE DATABASE copypatrol_dev;"
   $ mysql -u LOCALYOU -p copypatrol_dev < copyright.sql
   ```
   Use `--skip-create-options` for the dump if you're restoring to MySQL rather than MariaDB.

This application makes of use the [Wikimedia-slimapp](https://github.com/wikimedia/wikimedia-slimapp) library and uses Twig as its templating engine.

## To add a new translation message:
1. Add it to en.json
2. Update the qqq.json documentation accordingly
3. Call it in Twig as `{{ '<message-key>'|message }}`. If the message contains any HTML, you'll need to append the `|raw` filter after `message`.
4. To use a translation message in JavaScript, add it as a global variable in `templates/base.html`. Then simply access it in the JS.
5. To get a message in PHP, use `$this->i18nContext->message( '<message-key>' )`



