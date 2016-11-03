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
namespace Plagiabot\Web\Controllers;

use Plagiabot\Web\Controllers\OAuthUserManager;
use Wikimedia\Slimapp\Controller;
use Wikimedia\Slimapp\Auth\AuthManager;
use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\Token;

class AuthHandler extends Controller {

	const REQEST_KEY = 'oauthreqtoken';

	/**
	 * @var Client $oauth
	 */
	protected $oauth;

	/**
	 * @var OAuthUserManager $manager
	 */
	protected $manager;

	protected $slim;

	/**
	 * @param \Slim\Slim $slim Slim application
	 */
	public function __construct( \Slim\Slim $slim = null ) {
		parent::__construct( $slim );
		$this->slim = $slim;
	}

	public function setOAuth( Client $oauth ) {
		$this->oauth = $oauth;
	}

	public function setUserManager( OAuthUserManager $manager ) {
		$this->manager = $manager;
	}

	protected function handleGet( $stage ) {
		switch ( $stage ) {
			case 'callback':
				$this->handleCallback();
				break;
			default:
				$this->handleInitiate();
				break;
		}
	}

	/**
	 * Initiate OAuth handshake and redirect user to OAuth server to authorize
	 * the app.
	 */
	protected function handleInitiate() {
		list( $next, $token ) = $this->oauth->initiate();
		$_SESSION[self::REQEST_KEY] = "{$token->key}:{$token->secret}";
		$this->redirect( $next );
	}

	/**
	 * Process the return result from a user authorizing our app.
	 */
	protected function handleCallback() {
		$next = false;
		if ( isset( $_SESSION[AuthManager::NEXTPAGE_SESSION_KEY] ) ) {
			$next = $_SESSION[AuthManager::NEXTPAGE_SESSION_KEY];
			$next = filter_var( $next, \FILTER_VALIDATE_URL, \FILTER_FLAG_PATH_REQUIRED );
		}
		if ( !isset( $_SESSION[self::REQEST_KEY] ) ) {
			$this->flash( 'error', 'Session request incomplete' );
			$this->redirect( $this->urlFor( 'root' ) );
		}
		list( $key, $secret ) = explode( ':', $_SESSION[self::REQEST_KEY] );
		unset( $_SESSION[self::REQEST_KEY] );
		$token = new Token( $key, $secret );
		$this->form->requireString( 'oauth_verifier' );
		$this->form->requireInArray( 'oauth_token', [ $key ] );
		if ( $this->form->validate( $_GET ) ) {
			$verifyCode = $this->form->get( 'oauth_verifier' );
			try {
				$accessToken = $this->oauth->complete( $token, $verifyCode );
				$user = $this->manager->getUserData( $accessToken );
				$this->authManager->login( $user );
				$this->flash(
					'info',
					'You are now successfully logged in as ' . $user->getName() .
					'. Please note that this tool is set up to credit users ' .
					'for their reviews. Your username will be assocated with ' .
					'your reviews, be publicly visible and retained indefinitely.'
				);
			} catch ( \Exception $e ) {
				$this->flash( 'error', 'Logging in attempt aborted. Error!' );
			}
			$this->redirect( $next ?: $this->urlFor( 'root' ) );
		} else {
			$this->flash( 'error', 'Failure' );
		}
		$this->redirect( $this->urlFor( 'root' ) );
	}
}
