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
 * @copyright © 2016 Niharika Kohli and contributors.
 */
namespace Plagiabot\Web;

use Wikimedia\Slimapp\AbstractApp;
use Wikimedia\Slimapp\Config;
use Wikimedia\Slimapp\HeaderMiddleware;

class App extends AbstractApp {

	/**
	 * Apply settings to the Slim application.
	 *
	 * @param \Slim\Slim $slim Application
	 */
	protected function configureSlim( \Slim\Slim $slim ) {
		$slim->config( [
			'displayErrorDetails' => true,
			'debug' => true,
			'oauth.enable' => Config::getBool( 'USE_OAUTH', false ),
			'oauth.consumer_token' => Config::getStr( 'OAUTH_CONSUMER_TOKEN' ),
			'oauth.secret_token' => Config::getStr( 'OAUTH_SECRET_TOKEN' ),
			'oauth.endpoint' => Config::getStr( 'OAUTH_ENDPOINT' ),
			'oauth.redir' => Config::getStr( 'OAUTH_REDIR' ),
			'oauth.callback' => Config::getStr( 'OAUTH_CALLBACK' ),
			'db.dsnwp' => Config::getStr( 'DB_DSN_WIKIPROJECT' ),
			'db.dsnen' => Config::getStr( 'DB_DSN_ENWIKI' ),
			'db.dsnpl' => Config::getStr( 'DB_DSN_PLAGIABOT' ),
			'db.user' => Config::getStr( 'DB_USER' ),
			'db.pass' => Config::getStr( 'DB_PASS' ),
			'templates.path' => '../public_html/templates'
		] );
	}


	/**
	 * Configure inversion of control/dependency injection container.
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
			$conf->setConsumer( new \MediaWiki\OAuthClient\Consumer(
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
		// Plagiabot DAO
		$container->singleton( 'plagiabotDao', function ( $c ) {
			return new Dao\PlagiabotDao(
				$c->settings['db.dsnpl'],
				$c->settings['db.user'], $c->settings['db.pass']
			);
		} );
		// En.wikipedia DAO
		$container->singleton( 'enwikiDao', function ( $c ) {
			return new Dao\EnwikiDao(
				$c->settings['db.dsnen'],
				$c->settings['db.user'], $c->settings['db.pass']
			);
		} );
		// Wikiproject DAO
		$container->singleton( 'wikiprojectDao', function ( $c ) {
			return new Dao\WikiprojectDao(
				$c->settings['db.dsnwp'],
				$c->settings['db.user'], $c->settings['db.pass']
			);
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
		] );
		$view->parserExtensions = [
			new \Slim\Views\TwigExtension()
		];
	}


	/**
	 * Configure routes to be handled by application.
	 *
	 * @param \Slim\Slim $slim Application
	 */
	protected function configureRoutes( \Slim\Slim $slim ) {
		$slim->group( '/',
			function () use ( $slim ) {
				$slim->get( '/', function () use ( $slim ) {
					$page = new Controllers\CopyPatrol( $slim );
					$page->setDao( $slim->plagiabotDao );
					$page->setEnwikiDao( $slim->enwikiDao );
					$page->setWikiprojectDao( $slim->wikiprojectDao );
					$page();
				} )->name( 'home' );
			}
		);
	}


	/**
	 * Customize header middleware for app
	 *
	 * @return \Wikimedia\Slimapp\HeaderMiddleware
	 */
	protected function setHeaderMiddleware() {
		return new HeaderMiddleware( array(
			'Vary' => 'Cookie',
			'X-Frame-Options' => 'DENY',
			'Content-Security-Policy' =>
				"default-src 'self' *; " .
				"frame-src 'none'; " .
				"object-src 'none'; " .
				// Needed for css data:... sprites
				"img-src 'self' data:; " .
				// Needed for jQuery and Modernizr feature detection
				"style-src 'self' * 'unsafe-inline';" .
				"script-src 'self' * 'unsafe-exec' 'unsafe-inline'",
			// Don't forget to override this for any content that is not
			// actually HTML (e.g. json)
			'Content-Type' => 'text/html; charset=UTF-8',
		) );
	}

}