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

use Wikimedia\Slimapp\Auth\UserData;
use MediaWiki\OAuthClient\Token;

class OAuthUser implements UserData {

	/**
	 * @var Token $token
	 */
	protected $token;

	/**
	 * @var object $attributes
	 */
	protected $attributes;


	public function __construct( Token $token, $attributes ) {
		$this->token = $token;
		$this->attributes = $attributes;
	}


	/**
	 * Get user's unique numeric id.
	 *
	 * @return int
	 */
	public function getId() {
		return $this->attributes->sub;
	}


	public function getName() {
		return $this->attributes->username;
	}


	/**
	 * Get user's password.
	 *
	 * @return string
	 */
	public function getPassword() {
		return null;
	}


	/**
	 * Is this user blocked from logging into the application?
	 *
	 * @return bool True if user should not be allowed to log in to the
	 *   application, false otherwise
	 */
	public function isBlocked() {
		return false;
	}
}