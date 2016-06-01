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
namespace Plagiabot\Web\Dao;

use Wikimedia\Slimapp\Dao\AbstractDao;

class EnwikiDao extends AbstractDao {

	/**
	 * @var int $wikipedia
	 */
	protected $wikipedia;


	/**
	 * @param string $dsn PDO data source name
	 * @param string $user Database user
	 * @param string $pass Database password
	 * @param int|bool $uid Authenticated user
	 * @param array $settings Configuration settings
	 * @param LoggerInterface $logger Log channel
	 */
	public function __construct( $dsn, $user, $pass, $wiki = 'https://en.wikipedia.org', $settings = null, $logger = null
	) {
		parent::__construct( $dsn, $user, $pass, $logger );
		$this->wikipedia = $wiki;
	}


	/**
	 * Get editor details
	 *
	 * @param $diff int Diff revision ID
	 * @return array|false If editor exists, return array with params 'editor', 'editcount'. Else, false.
	 */
	public function getUserDetails( $diff ) {
		$query = self::concat(
			'SELECT r.rev_id, r.rev_user, r.rev_user_text, u.user_editcount, u.user_name',
			'FROM revision r',
			'JOIN user u ON r.rev_user = u.user_id',
			'WHERE r.rev_id = ?'
		);
		$data = array(
			'editor' => false,
			'editcount' => false
		);
		$result = $this->fetch( $query, array( (int)$diff ) );
		if ( $result == false ) {
			return $data;
		} else {
			$data['editor'] = $result['rev_user_text'];
			$data['editcount'] = $result['user_editcount'];
		}
		return $data;
	}


	/**
	 * We do an API query here because testing proved API query to be faster for looking up deleted page titles
	 *
	 * @param $title string Page title
	 * @return true|false depending on page dead or alive
	 */
	public function checkDeadLink( $title ) {
		if ( !$title ) {
			return false;
		}
		$url = $this->wikipedia . '/w/api.php?action=query&format=json&titles=' . urlencode( $title ) . '&formatversion=2';
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
		$result = curl_exec( $ch );
		$json = json_decode( $result );
		foreach ( $json->query->pages as $p ) {
			if ( isset( $p->missing ) ) {
				// Please note that this returns a false positive when the user account has a global User page and
				// not a local one
				return true;
			} else {
				return false;
			}
		}
		return true;
	}
}