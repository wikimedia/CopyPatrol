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

use DateInterval;
use NumberFormatter;
use Plagiabot\Web\Controllers\AddReview;
use Plagiabot\Web\Controllers\AuthHandler;
use Plagiabot\Web\Controllers\CopyPatrol;
use Plagiabot\Web\Controllers\Ithenticate;
use Plagiabot\Web\Controllers\Leaderboard;
use Plagiabot\Web\Controllers\UndoReview;
use Plagiabot\Web\Dao\PlagiabotDao;
use Plagiabot\Web\Dao\WikiDao;
use Slim\Slim;
use Locale;
use Slim\View;
use Slim\Views\TwigExtension;
use Twig_SimpleFilter;
use Stash\Driver\FileSystem;
use Stash\Driver\Redis;
use Stash\Pool;
use Wikimedia\SimpleI18n\TwigExtension as SimpleI18nTwigExtension;
use Wikimedia\Slimapp\AbstractApp;
use Wikimedia\Slimapp\Config;
use Wikimedia\Slimapp\Auth\AuthManager;
use Less_Cache;
use Wikimedia\SimpleI18n\I18nContext;
use Wikimedia\SimpleI18n\JsonCache;

class App extends AbstractApp {

	/**
	 * Get codes of all languages supported by CopyPatrol. This list is retrieved from the
	 * copyright_diffs database table, and cached for a week.
	 * @return string[] The 2-letter language codes.
	 */
	public function getSupportedLanguages() {
		// Use the cached list if possible.
		$cacheItem = $this->slim->cache->getItem( 'supported-languages' );
		if ( $cacheItem->isHit() ) {
			return $cacheItem->get();
		}

		// Otherwise get the list from the database, and cache it for a week (Period 7 Days).
		$langs = $this->getPlagiabotDao()->getLanguages();
		$cacheItem->expiresAfter( new DateInterval( 'P7D' ) );
		$cacheItem->set( $langs );
		$this->slim->cache->save( $cacheItem );
		return $langs;
	}

	/**
	 * Apply settings to the Slim application.
	 *
	 * @param Slim $slim Application
	 */
	protected function configureSlim( Slim $slim ) {
		$debug = ( Config::getStr( 'LOG_LEVEL' ) === 'debug' );
		$slim->config( [
			'displayErrorDetails' => $debug,
			'debug' => $debug,
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
			'db.replica.host' => Config::getStr( 'DB_REPLICA_HOST', 'localhost' ),
			'db.replica.port' => Config::getStr( 'DB_REPLICA_PORT', '3306' ),
			'db.replica.user' => Config::getStr( 'DB_REPLICA_USER' ),
			'db.replica.pass' => Config::getStr( 'DB_REPLICA_PASS' ),
			'db.name.copypatrol' => Config::getStr( 'DB_NAME_COPYPATROL' ),
			'templates.path' => APP_ROOT . '/public_html/templates',
			'i18n.path' => APP_ROOT . '/public_html/i18n',
			'ithenticate.user' => Config::getStr( 'ITHENTICATE_USER' ),
			'ithenticate.pass' => Config::getStr( 'ITHENTICATE_PASS' ),
		] );
	}

	/**
	 * Get the WikiDao based on the given language.
	 * @param string $lang The language code of the required Wikipedia.
	 * @return WikiDao
	 */
	public function getWikiDao( $lang ) {
		if ( $this->slim->wikiDao instanceof WikiDao ) {
			return $this->slim->wikiDao;
		}
		$this->slim->wikiDao = WikiDao::newFromLangCode(
			$lang,
			$this->slim->settings['db.replica.host'],
			$this->slim->settings['db.replica.port'],
			$this->slim->settings['db.replica.user'],
			$this->slim->settings['db.replica.pass'],
			$this->slim->log
		);
		return $this->slim->wikiDao;
	}

	/**
	 * Get the PlagiabotDao.
	 * @return PlagiabotDao
	 */
	public function getPlagiabotDao() {
		if ( $this->slim->plagiabotDao instanceof PlagiabotDao ) {
			return $this->slim->plagiabotDao;
		}
		$dsn = "mysql:host=" . $this->slim->settings['db.host'] . ";"
			   . "port=" . $this->slim->settings['db.port'] . ";"
			   . "dbname=" . $this->slim->settings['db.name.copypatrol'];
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
		$container->singleton( 'cache', function ( $c ) {
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
	 * @param View $view Default view
	 */
	protected function configureView( View $view ) {
		$view->replace( [
			'app' => $this->slim,
			'i18nCtx' => $this->slim->i18nContext,
			'supportedLanguages' => $this->getSupportedLanguages(),
		] );
		$view->parserExtensions = [
			new TwigExtension(),
			new SimpleI18nTwigExtension( $this->slim->i18nContext )
		];
		// If the intl PHP extension is installed, customise the number formatting.
		if ( class_exists( NumberFormatter::class ) ) {
			// Set number formatting for Twig, based on the Accept header or SimpleI18N.
			$lang = $locale = $this->slim->i18nContext->getCurrentLanguage();
			if ( isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
				$httpLocale = Locale::acceptFromHttp( $_SERVER['HTTP_ACCEPT_LANGUAGE'] );
				// Only use the HTTP locale if it's a more specific form of the current language.
				$locale = ( substr( $httpLocale, 0, strlen( $lang ) ) === $lang ) ? $httpLocale : $lang;
			}
			$formatter = new NumberFormatter( $locale, NumberFormatter::DECIMAL );
			// Get separator symbols (include decimal as it is required and we might use it at some point)
			$decimal = $formatter->getSymbol( NumberFormatter::DECIMAL_SEPARATOR_SYMBOL );
			$thousands = $formatter->getSymbol( NumberFormatter::GROUPING_SEPARATOR_SYMBOL );
			// Set Twig defaults, first argument is number of decimal places to show (we don't want any)
			$twig = $this->slim->view->getEnvironment();
			$twig->getExtension( 'core' )->setNumberFormat( 0, $decimal, $thousands );
		}
		// add URL decoder filter to Twig
		$filter = new Twig_SimpleFilter( 'url_decode', function ( $string ) {
			return urldecode( $string );
		} );
		$twig = $this->slim->view->getEnvironment();
		$twig->addFilter( $filter );
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
				// Give the view the current route name, for inter-language linking.
				$slim->view->set( 'currentRoute', $slim->router->getCurrentRoute()->getName() );
			},
			'require-auth' => function () use ( $slim ) {
				if ( !$slim->authManager->isAuthenticated() ) {
					echo json_encode( [ 'error' => 'Unauthorized' ] );
					$slim->stop();
				}
			},
		];

		// Root route.
		$slim->get( '/', $middleware['inject-user'],
			$middleware['set-environment'],
			function () use ( $slim ) {
				// See if we have a cookie indicating last version used
				if ( isset( $_COOKIE['copypatrolLang'] ) ) {
					$slim->redirectTo( 'home', [ 'wikiLang' => $_COOKIE['copypatrolLang'] ] );
				} else {
					// If no cookie, check if we support i18nContext's default language
					$lang = $slim->i18nContext->getCurrentLanguage();
					if ( in_array( $lang, $this->getSupportedLanguages() ) ) {
						$slim->redirectTo( 'home', [ 'wikiLang' => $lang ] );
					}
					// We don't support i18nContext's current language, so redirect to en version
					$slim->redirectTo( 'home', [ 'wikiLang' => 'en' ] );
				}
			}
		)->name( 'root' );

		// Language-based routes.
		$slim->group( '/:wikiLang', $middleware['inject-user'],
			$middleware['set-environment'],
			function () use ( $slim, $middleware ) {
				$routeConditions = [
					'wikiLang' => '(' . implode( '|', $this->getSupportedLanguages() ) . ')',
				];
				$slim->get( '/',
					function ( $wikiLang ) use ( $slim ) {
						$page = new CopyPatrol( $slim );
						// Cookie persists 30 days.
						setcookie( 'copypatrolLang', $wikiLang, time() + ( 86400 * 30 ) );
						$page->setDao( $this->getPlagiabotDao() );
						$page->setWikiDao( $this->getWikiDao( $wikiLang ) );
						$page();
					}
				)->name( 'home' )->setConditions( $routeConditions );
				$slim->get( '/leaderboard',
					function ( $wikiLang ) use ( $slim ) {
						$leaderboard = new Leaderboard( $slim );
						$leaderboard->setDao( $this->getPlagiabotDao() );
						$leaderboard->setWikiDao( $this->getWikiDao( $wikiLang ) );
						$leaderboard();
					}
				)->name( 'leaderboard' )->setConditions( $routeConditions );
				$slim->get( '/loadmore',
					function ( $wikiLang ) use ( $slim ) {
						$page = new CopyPatrol( $slim );
						$page->setDao( $this->getPlagiabotDao() );
						$page->setWikiDao( $this->getWikiDao( $wikiLang ) );
						$page();
					}
				)->name( 'loadmore' )->setConditions( $routeConditions );
				$slim->get( '/review/add', $middleware['require-auth'],
					function ( $wikiLang ) use ( $slim ) {
						// First make sure they aren't blocked from CopyPatrol.
						$this->checkIfBlocked( $slim, $wikiLang );

						$page = new AddReview( $slim );
						$page->setWikiDao( $this->getWikiDao( $wikiLang ) );
						$page->setDao( $this->getPlagiabotDao() );
						$page();
					}
				)->name( 'add_review' );
				$slim->get( '/review/undo', $middleware['require-auth'],
					function ( $wikiLang ) use ( $slim ) {
						// First make sure they aren't blocked from CopyPatrol.
						$this->checkIfBlocked( $slim, $wikiLang );

						$page = new UndoReview( $slim );
						$page->setWikiDao( $this->getWikiDao( $wikiLang ) );
						$page->setDao( $this->getPlagiabotDao() );
						$page();
					}
				)->name( 'undo_review' );
			} );

		// Stylesheet route.
		$slim->get( '/index.css',
			function () use ( $slim ) {
				// Compile LESS if need be, otherwise serve cached asset
				// Cached files get automatically deleted if they are over a week old
				// The value for the .less key defines the root of assets within the Less
				// e.g. /copypatrol/ makes url('image.gif') route to /copypatrol/image.gif
				$rootUri = $slim->request->getRootUri();
				$lessFiles = [ APP_ROOT . '/src/Less/index.less' => $rootUri . '/' ];
				$options = [ 'cache_dir' => APP_ROOT . '/cache/less' ];
				$cssFileName = Less_Cache::Get( $lessFiles, $options );
				$slim->response->headers->set( 'Content-Type', 'text/css' );
				$slim->response->setBody( file_get_contents( APP_ROOT . '/cache/less/' . $cssFileName ) );
			}
		)->name( 'index.css' );

		// Authentication routes.
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
			}
		);
		$slim->get( '/logout', $middleware['inject-user'],
			$middleware['set-environment'],
			function () use ( $slim ) {
				$slim->authManager->logout();
				$slim->redirect( $slim->urlFor( 'root' ) );
			}
		)->name( 'logout' );

		// Activity check route.
		$slim->get( '/activity_check',
			function () use ( $slim ) {
				$dao = $this->getPlagiabotDao();
				$lang = htmlspecialchars( $slim->request->get( 'lang', 'en' ) );
				$offset = (int)$slim->request->get( 'offset', 4 );
				if ( !$dao->hasActivity( $lang, $offset ) ) {
					$slim->halt( 500, "No activity for $lang in the past $offset hours" );
				}
				// Defaults to 200 status code
			}
		)->name( 'activity_check' );

		// Ithenticate route.
		$slim->get( '/ithenticate/:rid',
			function ( $rid ) use ( $slim ) {
				$page = new Ithenticate( $slim, $rid );
				$page();
			}
		)->name( 'ithenticate' )->setConditions( [
			'rid' => "\d+"
		] );
	}

	/**
	 * Quick check to see if a user is blocked. This is ran at the top of the
	 * review actions. If the user is blocked, the 'Blocked' error is rendered.
	 * @param  Slim $slim
	 * @param  string $wikiLang
	 */
	private function checkIfBlocked( Slim $slim, $wikiLang ) {
		$userData = $slim->authManager->getUserData();

		if ( !$userData ) {
			// They aren't logged in.
			return false;
		}

		$username = $userData->getName();
		$wikiDao = $this->getWikiDao( $wikiLang );
		$blockInfo = $wikiDao->getBlockInfo( $username );

		if ( $blockInfo ) {
			echo json_encode( [ 'error' => 'Blocked' ] );
			$slim->stop();
		}
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
