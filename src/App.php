<?php
/**
 * This file is part of CopyPatrol application
 * Copyright (C) 2016  Niharika Kohli and contributors
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option)
 * any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Niharika Kohli <nkohli@wikimedia.org>
 * @author Bryan Davis <bdavis@wikimedia.org>
 * @copyright © 2016 Niharika Kohli and contributors.
 */
namespace Plagiabot\Web;

use Plagiabot\Web\Controllers\AddReview;
use Plagiabot\Web\Controllers\AuthHandler;
use Plagiabot\Web\Controllers\CopyPatrol;
use Plagiabot\Web\Controllers\Leaderboard;
use Plagiabot\Web\Controllers\UndoReview;
use Plagiabot\Web\Dao\PlagiabotDao;
use Plagiabot\Web\Dao\WikiDao;
use Slim\Slim;
use Stash\Driver\FileSystem;
use Stash\Driver\Redis;
use Stash\Pool;
use Wikimedia\Slimapp\AbstractApp;
use Wikimedia\Slimapp\Config;
use Wikimedia\Slimapp\Auth\AuthManager;
use Less_Cache;
use Wikimedia\SimpleI18n\I18nContext;
use Wikimedia\SimpleI18n\JsonCache;

class App extends AbstractApp {

	/**
	 * Apply settings to the Slim application.
	 *
	 * @param Slim $slim Application
	 */
	protected function configureSlim( Slim $slim ) {
		$slim->config( [
			'displayErrorDetails' => true,
			'debug' => true,
			'oauth.enable' => Config::getBool( 'USE_OAUTH', false ),
			'oauth.consumer_token' => Config::getStr( 'OAUTH_CONSUMER_TOKEN' ),
			'oauth.secret_token' => Config::getStr( 'OAUTH_SECRET_TOKEN' ),
			'oauth.endpoint' => Config::getStr( 'OAUTH_ENDPOINT' ),
			'oauth.redir' => Config::getStr( 'OAUTH_REDIR' ),
			'oauth.callback' => Config::getStr( 'OAUTH_CALLBACK' ),
			'db.host' => Config::getStr( 'DB_HOST', 'localhost' ),
			'db.port' => Config::getStr( 'DB_PORT', '3306' ),
			'db.user' => Config::getStr( 'DB_USER' ),
			'db.pass' => Config::getStr( 'DB_PASS' ),
			'db.name.copypatrol' => Config::getStr( 'DB_NAME_COPYPATROL' ),
			'templates.path' => APP_ROOT . '/public_html/templates',
			'i18n.path' => APP_ROOT . '/public_html/i18n',
		] );
	}

	/**
	 * Get the WikiDao based on the given language.
	 * @param string $lang The language code of the required Wikipedia.
	 * @return WikiDao
	 */
	protected function getWikiDao( $lang ) {
		if ( $this->slim->wikiDao instanceof WikiDao ) {
			return $this->slim->wikiDao;
		}
		$this->slim->wikiDao = WikiDao::newFromLangCode(
			$lang,
			$this->slim->settings['db.host'],
			$this->slim->settings['db.port'],
			$this->slim->settings['db.user'],
			$this->slim->settings['db.pass'],
			$this->slim->log
		);
		return $this->slim->wikiDao;
	}

	/**
	 * Get the PlagiabotDao.
	 * @return PlagiabotDao
	 */
	protected function getPlagiabotDao() {
		if ( $this->slim->plagiabotDao instanceof PlagiabotDao ) {
			return $this->slim->plagiabotDao;
		}
		$dsn = "mysql:host=".$this->slim->settings['db.host'].";"
		       ."port=".$this->slim->settings['db.port'].";"
		       ."dbname=".$this->slim->settings['db.name.copypatrol'];
		$user = $this->slim->settings['db.user'];
		$password = $this->slim->settings['db.pass'];
		$this->slim->plagiabotDao = new PlagiabotDao( $dsn, $user, $password, $this->slim->log );
		return $this->slim->plagiabotDao;
	}

	/**
	 * Pre-prepare our class objects for use, with the appropriate parameters
	 * They can now be accessed directly as, for example, $slim->oauthClient
	 * anywhere which has access to the $slim object
	 *
	 * @param \Slim\Helper\Set $container IOC container
	 */
	protected function configureIoc( \Slim\Helper\Set $container ) {
		// OAuth Config
		$container->singleton( 'oauthConfig', function ( $c ) {
			$conf = new \MediaWiki\OAuthClient\ClientConfig(
				$c->settings['oauth.endpoint']
			);
			$conf->setRedirUrl( $c->settings['oauth.redir'] );
			$conf->setConsumer(
				new \MediaWiki\OAuthClient\Consumer(
					$c->settings['oauth.consumer_token'],
					$c->settings['oauth.secret_token']
				) );
			return $conf;
		} );
		// OAuth Client
		$container->singleton( 'oauthClient', function ( $c ) {
			$client = new \MediaWiki\OAuthClient\Client(
				$c->oauthConfig,
				$c->log
			);
			$client->setCallback( $c->settings['oauth.callback'] );
			return $client;
		} );
		// User manager
		$container->singleton( 'userManager', function ( $c ) {
			return new Controllers\OAuthUserManager(
				$c->oauthClient,
				$c->log
			);
		} );
		// Authentication manager
		$container->singleton( 'authManager', function ( $c ) {
			return new AuthManager( $c->userManager );
		} );
		// Tell SimpleI18n where to find the messages
		$container->singleton( 'i18nCache', function ( $c ) {
			return new JsonCache(
				$c->settings['i18n.path'], $c->log
			);
		} );
		// Tell SimpleI18n which language to use
		$container->singleton( 'i18nContext', function ( $c ) {
			return new I18nContext(
				$c->i18nCache, $c->settings['i18n.default'], $c->log
			);
		} );
		// Set up cache (Redis if config is provided, otherwise local filesystem).
		$container->singleton( 'cache', function( $c ) {
			$cache = new Pool();
			if ( Config::getStr( 'REDIS_HOST' ) ) {
				$driver = new Redis( [
					'servers' => [
						'server_1' => [
							'server' => Config::getStr( 'REDIS_HOST' ),
							'port' => Config::getStr( 'REDIS_PORT' ),
						],
					]
				] );
			} else {
				$driver = new FileSystem( [ 'path' => APP_ROOT . '/cache' ] );
			}
			$cache->setDriver( $driver );
			return $cache;
		} );
	}

	/**
	 * Configure view behavior.
	 *
	 * @param \Slim\View $view Default view
	 */
	protected function configureView( \Slim\View $view ) {
		$view->replace( [
			'app' => $this->slim,
			'i18nCtx' => $this->slim->i18nContext
		] );
		$view->parserExtensions = [
			new \Slim\Views\TwigExtension(),
			new \Wikimedia\SimpleI18n\TwigExtension( $this->slim->i18nContext )
		];
	}

	/**
	 * Configure routes to be handled by application.
	 *
	 * @param Slim $slim Application
	 */
	protected function configureRoutes( Slim $slim ) {
		$middleware = [
			'must-revalidate' => function () use ( $slim ) {
				$slim->response->headers->set(
					'Cache-Control', 'private, must-revalidate, max-age=0'
				);
				$slim->response->headers->set(
					'Expires', 'Thu, 01 Jan 1970 00:00:00 GMT'
				);
			},
			'inject-user' => function () use ( $slim ) {
				$user = $slim->authManager->getUserData();
				$slim->view->set( 'user', $user );
			},
			'set-environment' => function () use ( $slim ) {
				// determine if we are on the staging environment, so we can show a banner in the view
				$rootUri = $slim->request->getRootUri();
				$slim->view->set( 'staging', strpos( $rootUri, 'plagiabot' ) );
			},
			'require-auth' => function () use ( $slim ) {
				if ( !$slim->authManager->isAuthenticated() ) {
					echo json_encode( [ 'error' => 'Unauthorized' ] );
					$slim->stop();
				}
			},
			'trailing-slash' => function () use ( $slim ) {
				// Remove trailing slashes
				if ( substr( $_SERVER['REQUEST_URI'], -1 ) === '/' ) {
					$uri = rtrim( $_SERVER['REQUEST_URI'], '/' );
					$slim->redirect( $uri );
				}
			},
			'twig-number-format' => function () use ( $slim ) {
				// Set number formatting for Twig's number_format based on Intuition locale or HTTP header
				// First get user's locale
				$locale = ( isset( $_COOKIE['TsIntuition_userlang'] ) ) ?
					$_COOKIE['TsIntuition_userlang'] :
					$_SERVER['HTTP_ACCEPT_LANGUAGE'];
				$formatter = new \NumberFormatter( $locale, \NumberFormatter::DECIMAL );
				// Get separator symbols (include decimal as it is required and we might use it at some point)
				$decimal = $formatter->getSymbol( \NumberFormatter::DECIMAL_SEPARATOR_SYMBOL );
				$thousands = $formatter->getSymbol( \NumberFormatter::GROUPING_SEPARATOR_SYMBOL );
				// Set Twig defaults, first argument is number of decimal places to show (we don't want any)
				$twig = $slim->view->getEnvironment();
				$twig->getExtension( 'core' )->setNumberFormat( 0, $decimal, $thousands );
			}
		];
		$routeConditions = [
			'wikiLang' => '([a-z]{1,3})'
		];
		$slim->group( '/', $middleware['trailing-slash'],
				$middleware['inject-user'], $middleware['set-environment'],
			function () use ( $slim, $middleware, $routeConditions ) {
				$slim->get( '', function() use ( $slim ) {
					// Redirect root-URL requests to English.
					$currentLang = $slim->i18nContext->getCurrentLanguage();
					$slim->redirectTo( 'home', [ 'wikiLang' => $currentLang ] );
				} )->name( 'root' );
				$slim->get( ':wikiLang',
					function ( $wikiLang ) use ( $slim ) {
						$page = new CopyPatrol( $slim );
						$page->setDao( $this->getPlagiabotDao() );
						$page->setWikiDao( $this->getWikiDao( $wikiLang ) );
						$page();
					} )->name( 'home' )->setConditions( $routeConditions );
				$slim->get( 'logout', function () use ( $slim ) {
					$slim->authManager->logout();
					$slim->redirect( $slim->urlFor( 'home' ) );
				} )->name( 'logout' );
				$slim->get( ':wikiLang/loadmore', function ( $wikiLang ) use ( $slim ) {
					$page = new Controllers\CopyPatrol( $slim );
					$page->setDao( $this->getPlagiabotDao() );
					$page->setWikiDao( $this->getWikiDao( $wikiLang ) );
					$page();
				} )->name( 'loadmore' )->setConditions( $routeConditions );
				$slim->get( 'index.css', function () use ( $slim ) {
					// Compile LESS if need be, otherwise serve cached asset
					// Cached files get automatically deleted if they are over a week old
					// The value for the .less key defines the root of assets within the Less
					//   e.g. /copypatrol/ makes url('image.gif') route to /copypatrol/image.gif
					$rootUri = $slim->request->getRootUri();
					$lessFiles = [ APP_ROOT . '/src/Less/index.less' => $rootUri . '/' ];
					$options = [ 'cache_dir' => APP_ROOT . '/cache/less' ];
					$cssFileName = Less_Cache::Get( $lessFiles, $options );
					$slim->response->headers->set( 'Content-Type', 'text/css' );
					$slim->response->setBody( file_get_contents( APP_ROOT . '/cache/less/' . $cssFileName ) );
				} )->name( 'index.css' );
			} );
		$slim->group( '/review/', $middleware['require-auth'],
			function () use ( $slim ) {
				$slim->get( 'add', function () use ( $slim ) {
					$page = new AddReview( $slim );
					$page->setDao( $this->getPlagiabotDao() );
					$page();
				} )->name( 'add_review' );
				$slim->get( 'undo', function () use ( $slim ) {
					$page = new UndoReview( $slim );
					$page->setDao( $this->getPlagiabotDao() );
					$page();
				} )->name( 'undo_review' );
			} );
		$slim->group( '/oauth/',
			function () use ( $slim ) {
				$slim->get( '', function () use ( $slim ) {
					$page = new AuthHandler( $slim );
					$page->setOAuth( $slim->oauthClient );
					$page( 'init' );
				} )->name( 'oauth_init' );
				$slim->get( 'callback', function () use ( $slim ) {
					$page = new AuthHandler( $slim );
					$page->setOAuth( $slim->oauthClient );
					$page->setUserManager( $slim->userManager );
					$page( 'callback' );
				} )->name( 'oauth_callback' );
			} );
		$slim->get( '/:wikiLang/leaderboard', $middleware['inject-user'],
			function ( $wikiLang ) use ( $slim ) {
				$leaderboard = new Leaderboard( $slim );
				// $leaderboard->setLang( $lang );
				$leaderboard->setDao( $this->getPlagiabotDao() );
				$this->getWikiDao( $wikiLang );
				// $leaderboard->setDao( $this->getPlagiabotDao() );
				$leaderboard();
			} )->name( 'leaderboard' )->setConditions( $routeConditions );
	}

	/**
	 * Customize header middleware for app
	 *
	 * @return string[]
	 */
	protected function configureHeaderMiddleware() {
		return [
			'Vary' => 'Cookie',
			'X-Frame-Options' => 'DENY',
			'Content-Security-Policy' =>
				"default-src 'self' *; " .
				"frame-src 'self'; " .
				"object-src 'self'; " .
				// Needed for css data:... sprites
				"img-src 'self' data:; " .
				// Needed for jQuery and Modernizr feature detection
				"style-src 'self' * 'unsafe-inline';" .
				"script-src 'self' * 'unsafe-exec' 'unsafe-inline'",
			// Don't forget to override this for any content that is not
			// actually HTML (e.g. json)
			'Content-Type' => 'text/html; charset=UTF-8',
		];
	}

}
