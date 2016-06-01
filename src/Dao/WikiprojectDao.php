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

class WikiprojectDao extends AbstractDao {

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
	 * @param $title string Page title
	 * @return array Wikiprojects for a given page title on enwiki
	 */
	public function getWikiProjects( $title ) {
		$query = self::concat(
			'SELECT * FROM projectindex',
			'WHERE pi_page = ?'
		);
		$result = $this->fetchAll( $query, array( 'Talk:' . $title ) );
		$data = array();
		if ( $result ) {
			foreach ( $result as $r ) {
				// Skip projects without 'Wikipoject' in title as they are partnership-based Wikiprojects
				if ( stripos( $r['pi_project'], 'Wikipedia:WikiProject_' ) !== false ) {
					// Remove "Wikipedia:Wikiproject_" part from the string before use
					$project = substr( $r['pi_project'], 22 );
					// Remove subprojects
					if ( stripos( $project, '/' ) !== false ) {
						$project = substr( $project, 0, stripos( $project, '/' ) );
					}
					$data[$project] = true;
				}
			}
		} else {
			return array();
		}
		$data = array_keys( $data );
		// Return alphabetized list
		sort( $data );
		return $data;
	}
}