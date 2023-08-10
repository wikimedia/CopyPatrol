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

* PHP 7.4+
* [Symfony CLI](https://symfony.com/download#step-1-install-symfony-cli)
* Node using the version specified by [.nvmrc](.nvmrc)
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
Development through Docker is suggested if you have a different version of PHP locally
installed, or if you wish to keep an isolated installation of PHP 7.4 for CopyPatrol.
The base Docker image used is the `toolforge-php74-sssd-base` image from the Toolforge
Docker image registry, to ensure an environment as close to Toolforge as possible.

1. Copy [.env](.env) to [.env.local](.env.local) and fill in the appropriate details.
    1. Set `REPLICAS_HOST_*` and `TOOLSDB_HOST` to `127.0.0.1`
    2. Use the credentials in your `replica.my.cnf` file in the home directory of your
       Toolforge account for `REPLICAS_USERNAME`, `REPLICAS_PASSWORD`, as well as
       `TOOLSDB_USERNAME` and `TOOLSDB_PASSWORD`.
    3. If you need to test (un)reviewing CopyPatrol cases, `TOOLSDB_USERNAME` and `TOOLSDB_PASSWORD`
       need to be set to a user with an installation of the CopyPatrol database (`COPYPATROL_DB_NAME`).
    4. If you need to test OAuth, obtain tokens by registering a new consumer on Meta at
       [Special:OAuthConsumerRegistration](https://meta.wikimedia.org/wiki/Special:OAuthConsumerRegistration).
       Alternatively, you can set `LOGGED_IN_USER` to any value to simulate being that user
       after clicking on 'Login'.
    5. `ITHENTICATE_USERNAME` and `ITHENTICATE_PASSWORD` are not necessary unless you need
       to test the "iThenticate report" functionality.
2. Build the development image once and install Composer dependencies with the following
   ```bash
   # (optional) Prevent double-downloading when the build occurs.
   docker image pull docker-registry.tools.wmflabs.org/toolforge-php74-sssd-base:latest
   docker compose build
   # On Windows, use `%CD%` instead of `$(pwd)`.
   docker run --rm -ti -v $(pwd):/app wikimedia/copypatrol:development composer install
   ```
3. (*Windows only*) Set the `HOME` environment variable to your user profile directory.
   ```cmd
   setx HOME "%UserProfile%"
   set HOME "%UserProfile%"
   ```
   The first command sets `HOME` for future shells. The second command sets `HOME` for the current shell.
4. Open a new terminal and start the development container with
   ```bash
   docker compose up
   ```
   Starting the local development server will be delayed until the next
   step is finished.
5. Open up an SSH tunnel to access the databases on Toolforge.
   ```bash
   # Your SSH config at $HOME/.ssh will be used.
   # Your passphrase will be requested if your private key is protected.
   # If your Toolforge shell name is different from the default, append
   # your shell name after "ssh". (e.g. `... start ssh exampleuser`)
   docker compose exec copypatrol start ssh
   ```
   This terminal will stay open as long as SSH is connected. No successful
   connection message is shown, but Symfony will start immediately once the
   ports are open.

Changes to this folder will automatically be applied to the running Docker container. This includes
changes to `src` files, `.env.local`, etc. XDebug is set up to connect to the host machine
(the computer running the Docker container) on port 9003 upon request ([more info](https://xdebug.org/docs/step_debug)).

If you wish to use testing databases instead of the Plagiabot databases live on Toolforge, change
`COPYPATROL_DB_NAME`, and all related connection options. You will still need to connect to
the Replica DBs for revision information, so leave `REPLICAS_HOST_*` untouched and keep
tunneling the port for the Replica DB in step 5.

To make a **production-level** (Toolforge-like) build, run the following. XDebug and
other related components will be disabled.
```bash
docker build -f docker/Dockerfile . --target production -t wikimedia/copypatrol:latest
```
When using this image, bind an `.env.local` file to `/app/.env.local` for configuration.
This configuration file must also point to proper hosts. When using local ports, use
`host.docker.internal` for Windows, or `172.17.0.1` for other platforms.
```bash
docker run -ti -p 80:80 -v $(pwd)/.env.local:/app/.env.local wikimedia/copypatrol:latest
```

## Adding new languages

1. Make sure the language is supported by iThenticate. This list is available at https://www.ithenticate.com/resources
   under the "General" section of the FAQ. Look for "_Which international languages does iThenticate have content for in its database?_".
2. Make sure there is community consensus for CopyPatrol. This helps ensure they will **regularly** make use of CopyPatrol.
   The bot which powers the CopyPatrol feed is expensive in terms of the resources it uses.
   Any languages that are not regularly being used should be removed.
3. Make sure the corresponding `-wikipedia` message in [i18n/en.json](i18n/en.json) (and [qqq.json](i18n/qqq.json)
   exists and is translated in the desired language.
4. Update the `.copypatrol.ini` file accordingly (which is used by the bot),
   and add the new languages to the `APP_ENABLED_LANGS` variable in [.env](.env).

## Removing a language

1. In `.copypatrol.ini`, set the `enabled` key for the desired language to `false`,
   or remove the definition entirely.
2. Remove the language from the `APP_ENABLED_LANGS` variable in [.env](.env).
