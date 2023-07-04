CopyPatrol
==========

A tool that allows you to see recent Wikipedia edits that are flagged as possible
[copyright violations](https://en.wikipedia.org/wiki/Wikipedia:Copyright_violations).

* User documentation: https://meta.wikimedia.org/wiki/Special:MyLanguage/CopyPatrol
* Issue tracker: https://phabricator.wikimedia.org/tag/copypatrol/
* Source code: https://gitlab.wikimedia.org/repos/commtech/copypatrol

## Installing manually

### Prerequisites

* PHP 7.4+
* [Symfony CLI](https://symfony.com/download#step-1-install-symfony-cli)
* Node using the version specified by [.nvmrc](.nvmrc)
* [Toolforge access](https://wikitech.wikimedia.org/wiki/Help:Toolforge/Quickstart)

This application makes use of the [Symfony framework](https://symfony.com/) and
the [ToolforgeBundle](https://github.com/wikimedia/ToolforgeBundle).

### Instructions

1. Copy [.env](.env) to [.env.local](.env.local) and fill in the appropriate details.
    1. Use the credentials in your `replica.my.cnf` file in the home directory of your
       Toolforge account for `REPLICAS_USERNAME`, `REPLICAS_PASSWORD`, as well as
       `TOOLSDB_USERNAME` and `TOOLSDB_PASSWORD`.
    2. If you need to test (un)reviewing CopyPatrol cases, `TOOLSDB_USERNAME` and `TOOLSDB_PASSWORD`
       need to be set to a user with an installation of the CopyPatrol database (`COPYPATROL_DB_NAME`).
    3. If you need to test OAuth, obtain tokens by registering a new consumer on Meta at
       [Special:OAuthConsumerRegistration](https://meta.wikimedia.org/wiki/Special:OAuthConsumerRegistration).
       Alternatively, you can set `LOGGED_IN_USER` to any value to simulate being that user
       after clicking on 'Login'.
    4. `ITHENTICATE_USERNAME` and `ITHENTICATE_PASSWORD` are not necessary unless you need
       to test the "iThenticate report" functionality.
2. Run `composer install`
3. Open up an SSH tunnel to access the databases on Toolforge. This assumes you have left
   the `REPLICAS_HOST_*` and `REPLICAS_PORT_*` variables at their defaults.
   ```bash
   symfony console toolforge:ssh --toolsdb
   ```
4. Start the Symfony web server with `symfony serve`

## Installing using Docker

_TODO: Update any of these instructions as necessary following the Symfony migration._

1. Build the Docker image for CopyPatrol.
   ```bash
   docker-compose -f docker-compose.dev.yml build
   ```
2. Install Composer packages using the PHP version used by the Docker image. Be sure that your current/present working directory is this repository.
   ```bash
   # The `wikimedia/copypatrol-development` image is generated in the first step.
   docker run --rm -it -v $(pwd):/app wikimedia/copypatrol-development composer install
   ```
   On Windows, use the following command instead:
   ```bash
   docker run --rm -it -v %CD%:/app wikimedia/copypatrol-development composer install
   ```
3. Edit the `.env.local` file that was created by composer.
    1. Use the credentials in your `replica.my.cnf` file in the home directory of your
       Toolforge account for `REPLICAS_USERNAME`, `REPLICAS_PASSWORD`, as well as
       `TOOLSDB_USERNAME` and `TOOLSDB_PASSWORD`.
    2. If you need to test (un)reviewing CopyPatrol cases, `TOOLSDB_USERNAME` and `TOOLSDB_PASSWORD`
       need to be set to a user with an installation of the CopyPatrol database (`COPYPATROL_DB_NAME`).
    3. If you need to test OAuth, obtain tokens by registering a new consumer on Meta at
       [Special:OAuthConsumerRegistration](https://meta.wikimedia.org/wiki/Special:OAuthConsumerRegistration).
       Alternatively, you can set `LOGGED_IN_USER` to any value to simulate being that user
       after clicking on 'Login'.
    4. `ITHENTICATE_USERNAME` and `ITHENTICATE_PASSWORD` are not necessary unless you need
       to test the "iThenticate report" functionality.
4. Open up an SSH tunnel to access the databases on Toolforge. This assumes you have left
   the `REPLICAS_HOST_*` and `REPLICAS_PORT_*` variables at their defaults.
   ```bash
   symfony console toolforge:ssh --bind-address=0.0.0.0 --toolsdb
   ```
5. Run the Docker Compose file. CopyPatrol will be accessible on http://localhost:80
   ```bash
   docker-compose -f docker-compose.dev.yml up
   ```

Changes to this folder will automatically be applied to the running Docker container. This includes
changes to `src` files, `.env.local`, etc. XDebug is set up to connect to the host machine (the computer
running the Docker container) on request, see the `Dockerfile` for the specific configuration values.

If you wish to use testing databases instead of the Plagiabot databases live on Toolforge, change `COPYPATROL_DB_NAME`,
and all related connection options. You will still need to connect to the Replica DBs for revision
information, so leave `REPLICAS_HOST_*` untouched and keep tunneling the port for the Replica DB in step 4.

To make a **production-level** build, run `docker build --target production -t wikimedia/copypatrol:latest`.
XDebug and other related components will be disabled.

## Adding new languages

1. Make sure the language is supported by iThenticate. This list is available at https://www.ithenticate.com/resources
   under the "General" section of the FAQ. Look for "_Which international languages does iThenticate have content for in its database?_".
2. Make sure there is community consensus for CopyPatrol. This helps ensure they will **regularly** make use of CopyPatrol.
   The bot which powers the CopyPatrol feed is expensive in terms of the resources it uses.
   Any languages that are not regularly being used should be removed.
3. Make sure the corresponding `-wikipedia` message in [i18n/en.json](i18n/en.json) (and [qqq.json](i18n/qqq.json)
   exists and is translated in the desired language.
4. Add the language code to `APP_ENABLED_LANGS` in the [.env](.env) files.
5. _We now use https://github.com/JJMC89/copypatrol-backend as the bot backend; technical instructions TBD_

## Removing a language

1. Remove the language from `APP_ENABLED_LANGS`.
2. _TBD_: Remove the job from CopyPatrolBot.
