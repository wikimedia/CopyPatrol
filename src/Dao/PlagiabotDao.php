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

class PlagiabotDao extends AbstractDao {

	/**
	 * @var int $wikipedia String wikipedia url (enwiki by default)
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
	 * @param int $n Number of records asked for
	 * @return array|false Data for plagiabot db records or false if no data is not returned
	 */
	public function getPlagiarismRecords( $n = 50 ) {
		$limit = 'LIMIT ' . $n;
		$sql = self::concat(
			'SELECT * FROM copyright_diffs',
			'ORDER BY diff_timestamp DESC',
			$limit
		);
		return $this->fetchAll( $sql );
	}


	/**
	 * @param $value string Value of the state saved by user
	 * @param $ithenticateId int Ithenticate ID of the report
	 * @return true|false depending on query success/fail
	 */
	public function insertCopyvioAssessment( $ithenticateId, $value ) {
//		$query = "UPDATE copyright_diffs SET status='" . $value . "' WHERE ithenticate_id='" . $ithenticateId . "'";
//		if ( $this->linkPlagiabot ) {
//			$result = mysqli_query( $this->linkPlagiabot, $query );
//			return $result;
//		}
		return true;
	}

}