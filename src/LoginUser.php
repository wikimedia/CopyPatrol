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

use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Client;

/**
 * Class Login
 *
 * @package Plagiabot\Web
 */
class LoginUser {

	/**
	 * @var $endpoint String url endpoint
	 */
	private $endpoint;

	/**
	 * @var $redirect String url redirect
	 */
	private $redirect;

	/**
	 * @var $consumerKey String Consumer key for the app
	 */
	private $consumerKey;

	/**
	 * @var $secretKey String Secret key for the app
	 */
	private $secretKey;

	private $ident;

	private $token;


	/**
	 * LoginUser constructor.
	 */
	public function __construct() {
		$config = parse_ini_file( '../credentials.ini' );
		$this->endpoint = 'https://en.wikipedia.org/w/index.php?title=Special:OAuth';
		$this->redirect = 'https://en.wikipedia.org/wiki/Special:OAuth?';
//		$this->redirect = 'https://localhost/PlagiabotWeb/public_html/index.php';
		$this->consumerKey = $config['consumerKey'];
		$this->secretKey = $config['secretKey'];
	}


	public function execute() {
		$conf = new ClientConfig( $this->endpoint );
		$conf->setRedirUrl( $this->redirect );
		$conf->setConsumer( new Consumer( $this->consumerKey, $this->secretKey ) );
		$client = new Client( $conf );
		$client->setCallback( 'http://localhost/PlagiabotWeb/public_html/oath_callback.php' );
		// Step 1 = Get a request token
		list( $next, $token ) = $client->initiate();
		$this->token = $token;
		echo (string)$next;
		return $next;
//		$ident = $client->identify( $accessToken );
		// Do a simple API call
//		echo "Getting user info: ";
//		echo $client->makeOAuthCall(
//			$accessToken,
//			'https://en.wikipedia.org/wiki/api.php?action=query&meta=userinfo&uiprop=rights&format=json'
//		);
	}


	public function getAccessToken() {
		$verifyCode = $_GET['oauth_verifier'];
		$accessToken = $client->complete( $this->token, $verifyCode );
		$this->ident = $client->identify( $accessToken );
	}


	public function getUserName() {
		return "Authenticated user {$ident->username}\n";
	}
}