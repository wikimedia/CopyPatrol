CopyPatrol
==========

A tool that allows you to see recent Wikipedia edits that are flagged as possible
[copyright violations](https://en.wikipedia.org/wiki/Wikipedia:Copyright_violations).

* User documentation: https://meta.wikimedia.org/wiki/Special:MyLanguage/CopyPatrol
* Issue tracker: https://phabricator.wikimedia.org/tag/copypatrol/
* Frontend source code (this repo): https://github.com/wikimedia/CopyPatrol
* Bot source code: https://github.com/JJMC89/copypatrol-backend

## Installing manually

### Prerequisites

* PHP 8.1+
* [Symfony CLI](https://symfony.com/download#step-1-install-symfony-cli)
* Node using the version specified by [.nvmrc](.nvmrc)
  * Consider using [Fresh](https://www.mediawiki.org/wiki/Fresh) for Node dependencies.
* [Toolforge access](https://wikitech.wikimedia.org/wiki/Help:Toolforge/Quickstart)

This application makes use of the [Symfony framework](https://symfony.com/) and
the [ToolforgeBundle](https://github.com/wikimedia/ToolforgeBundle). A [bot](https://meta.wikimedia.org/wiki/User:CopyPatrolBot) is used
to continually query recent changes against the Turnitin Core API (TCA), record possible copyright
violations in a user database, and CopyPatrol then reads from that database. Unless you need to work
on the [bot code](https://github.com/JJMC89/copypatrol-backend), there's no reason to bother with bot
integration, and instead connect to the existing user database on Toolforge (more on this below).

### Instructions

1. Copy [.env](.env) to [.env.local](.env.local) and fill in the appropriate details.
    1. Use the credentials in your `replica.my.cnf` file in the home directory of your
       Toolforge account for `REPLICAS_USERNAME`, `REPLICAS_PASSWORD`.
    2. Set the `TROVE_*` variables to that of the installation of the CopyPatrol database
       (`COPYPATROL_DB_NAME`).
    3. If you need to test OAuth, obtain tokens by registering a new consumer on Meta at
       [Special:OAuthConsumerRegistration](https://meta.wikimedia.org/wiki/Special:OAuthConsumerRegistration).
       Alternatively, you can set `LOGGED_IN_USER` to any value to simulate being that user
       after clicking on 'Login'.
    4. If you need to test the "iThenticate report" functionality, set `TCA_DOMAIN` and `TCA_KEY`.
       Reports older than `AppController::ITHENTICATE_V2_TIMESTAMP` need to connect to the older
       iThenticate API, using the credentials set by `ITHENTICATE_USERNAME` and `ITHENTICATE_PASSWORD`.
2. Run `composer install`
3. Open up an SSH tunnel to access the databases on Toolforge. This assumes you have left
   the `REPLICAS_HOST_*` and `REPLICAS_PORT_*` variables at their defaults.
   ```bash
   symfony console toolforge:ssh --trove=copypatrol-dev-db-01
   ```
4. Start the Symfony web server with `symfony serve`
5. Visit the URL provided by the Symfony CLI to access the application.

### Asset changes

Assets are compiled using Webpack Encore. The compiled assets **must** be committed to the repository.

* Run `npm run dev` to compile assets for development and watch for changes.
* Run `npm run build` to compile assets for production.

### Running tests

* Run `composer test` to run the unit tests and PHP CodeSniffer.
* Run `npm test` to run the linting tests for JavaScript and CSS.

## Installing using Docker

_A new Docker image needs to be created following the move to Wikimedia VPS._
_You can use the manual installation instructions above in the meantime._

## Adding new languages

1. Make sure the language is supported by iThenticate. This list is available at https://www.ithenticate.com/resources
   under the "General" section of the FAQ. Look for "_Which international languages does iThenticate have content for in its database?_".
2. Make sure there is community consensus for CopyPatrol. This helps ensure they will **regularly** make use of CopyPatrol.
   The bot which powers the CopyPatrol feed is expensive in terms of the resources it uses.
   Any languages that are not regularly being used should be removed.
3. Make sure the corresponding `-wikipedia` message in [i18n/en.json](i18n/en.json) (and [qqq.json](i18n/qqq.json)
   exists and is translated in the desired language.
4. Update the `.copypatrol.ini` file in the [bot repository](https://github.com/JJMC89/copypatrol-backend)
   and add the new languages to the `APP_ENABLED_LANGS` variable in [.env](.env).

## Removing a language

1. In `.copypatrol.ini` of the [bot repository](https://github.com/JJMC89/copypatrol-backend), set the `enabled` key
   for the desired language to `false`, or remove the definition entirely.
2. Remove the language from the `APP_ENABLED_LANGS` variable in [.env](.env).

## Deployment

The `master` branch is automatically deployed to the [staging environment](https://copypatrol-test.wmcloud.org/)
when changes are pushed. The [production instance](https://copypatrol.wmcloud.org) is updated when a new
version is tagged:

1. Update the `APP_VERSION` in [.env](.env) to the new version, using the [CalVer](https://calver.org/) format.
2. Push the changes to the `master` branch.
3. [Create a new release](https://github.com/wikimedia/CopyPatrol/releases/new) on GitHub with the same tag.

The production instance should be updated within 10 minutes.
