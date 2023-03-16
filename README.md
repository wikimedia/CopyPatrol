# CopyPatrol
This is a web interface for [Plagiabot's Copyright RC feed](https://en.wikipedia.org/wiki/User:EranBot/Copyright/rc).

## To install locally
1. Clone the repository and run `composer install`.
2. Edit the `.env` file that was created by composer.
   1. Get Oauth tokens by registering a new consumer on Meta
      at [Special:OAuthConsumerRegistration](https://meta.wikimedia.org/wiki/Special:OAuthConsumerRegistration).
   2. To use Redis caching, also add `REDIS_HOST` and `REDIS_PORT`;
      without these, a local filesystem cache will be used.
3. Start a development server with `cd public_html && php -S localhost:8000`
4. Open up an SSH tunnel to access the databases on Toolforge (substitute your own username).<br>
   ```
   $ ssh -L 4711:enwiki.web.db.svc.eqiad.wmflabs:3306 -L 4712:tools.db.svc.eqiad.wmflabs:3306 USERNAME@login.toolforge.org -N
   ```
   In this example, `DB_REPLICA_PORT` would be `4711` and `DB_PORT` would be `4712`. Note that the above also only
   allows you to query English Wikipedia. You'll need to change the host accordingly to test other languages, such as
   `eswiki.web.db.svc.eqiad.wmflabs` if you need to test against Spanish Wikipedia.

This application makes of use the [Wikimedia-slimapp](https://github.com/wikimedia/wikimedia-slimapp) library and uses Twig as its templating engine.

## To add a new translation message:
1. Add it to en.json
2. Update the qqq.json documentation accordingly
3. Call it in Twig as `{{ '<message-key>'|message }}`. If the message contains any HTML, you'll need to append the `|raw` filter after `message`.
4. To use a translation message in JavaScript, add it as a global variable in `templates/base.html`. Then simply access it in the JS.
5. To get a message in PHP, use `$this->i18nContext->message( '<message-key>' )`

## To add a new language

To add a new language, follow these steps:

1. Make sure the language is supported by iThenticate. This list is available at http://www.ithenticate.com/products/faqs. Look for the "Which international languages does iThenticate have content for in its database?" section.
2. Make sure there is community consensus for CopyPatrol. This helps ensure they will **regularly** make use of CopyPatrol. EranBot, which powers the CopyPatrol feed, is expensive in terms of the resources it uses. Any languages that are not regularly being used should be removed.
3. Make sure the corresponding `-wikipedia` message in `public_html/i18n/en.json` (and qqq.json) exists and is translated in the desired language.
4. On Toolforge, `become community-tech-tools`, then `become eranbot`. Add the following to the crontab, replcaing `enwiki` and `-lang:en` accordingly:
```
*/10 * * * * jsub -N enwiki -mem 500m -l h_rt=4:05:00 -once -quiet -o /data/project/eranbot/outs python /data/project/eranbot/gitPlagiabot/plagiabot/plagiabot.py -lang:en -blacklist:User:EranBot/Copyright/Blacklist -live:on -reportlogger
```
5. Monitor the `.err` file (i.e. enwiki.err) for output. If looks similar to the other .err files, you know it's running properly. Once a copyvio is found and stored in the database, the feed for the new language in CopyPatrol should show up within 7 days (due to caching).

## To remove a language

1. Remove the entry from `eranbot`'s crontab.
2. Remove all relevant rows from the database. While logged in as eranbot, run:
```
sql local
MariaDB [(none)]> USE s51306__copyright_p;
MariaDB [s51306__copyright_p]> DELETE FROM copyright_diffs WHERE lang = 'xx'
```
