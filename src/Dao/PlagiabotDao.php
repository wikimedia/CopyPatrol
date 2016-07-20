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
	 * @param string $wiki Wikipedia URL
	 * @param array $settings Configuration settings
	 * @param LoggerInterface $logger Log channel
	 */
	public function __construct(
		$dsn, $user, $pass,
		$wiki = 'https://en.wikipedia.org', $settings = null, $logger = null
	) {
		parent::__construct( $dsn, $user, $pass, $logger );
		$this->wikipedia = $wiki;
	}

	/**
	 * @param int $n Number of records asked for
	 * @param array $options filter and filter user options, should look like:
	 *   string 'filter' Filter SQL to show a certian status, one of 'all',
	 *     'open', 'reviewed' or 'mine'
	 *   string 'filter_user' Filter SQL to only return records reviewed by
	 *     given user
	 *   boolean 'drafts' (any non-blank value), returns only records that
	 *   	 are in the Draft namespace
	 *   integer 'last_id' offset of where to start fetching records, going by
	 *     'ithenticate_id'
	 * @return array|false Data for plagiabot db records or false if no data
	 *   is not returned
	 */
	public function getPlagiarismRecords( $n = 50, $options ) {
		$filters = [];
		$filterSql = '';
		$lastId = isset( $options['last_id'] ) ? $options['last_id'] : null;
		$filter = isset( $options['filter'] ) ? $options['filter'] : 'all';
		$filterUser = isset( $options['filter_user'] ) ? $options['filter_user'] : null;
		// ensures only valid filters are used
		switch ( $filter ) {
			case 'reviewed':
				$filters[] = "status IS NOT NULL";
				break;
			case 'open':
				$filters[] = "status IS NULL";
				break;
		}
		// allow filtering by user and status
		if ( isset( $filterUser ) ) {
			$filters[] = "status_user = '$filterUser'";
		}
		// see if this is a load more click
		if ( isset( $lastId ) ) {
			$filters[] = "ithenticate_id < '$lastId'";
		}
		// filtering to draft namespace
		if ( isset( $options['drafts'] ) ) {
			$filters[] = 'page_ns = 118';
		}
		// construct necessary SQL based on filters
		if ( !empty( $filters ) ) {
			$filterSql = 'WHERE ' . join( ' AND ', $filters );
		}
		$sql = self::concat(
			'SELECT * FROM copyright_diffs',
			$filterSql,
			'GROUP BY id',
			'ORDER BY diff_timestamp DESC',
			'LIMIT ' . $n
		);
		return $this->fetchAll( $sql );
	}

	/**
	 * Get the top reviewers over the past last 7 days, 30 days, and all-time
	 * @return array Associative array of leaderboard data
	 */
	public function getLeaderboardData() {
		$lastWeek = $this->fetchAll(
			$this->getLeaderboardSql( '7' )
		);

		$lastMonth = $this->fetchAll(
			$this->getLeaderboardSql( '30' )
		);

		$allTime = $this->fetchAll(
			$this->getLeaderboardSql()
		);

		return [
			'last-week' => $lastWeek,
			'last-month' => $lastMonth,
			'all-time' => $allTime
		];
	}

	/**
	 * Get SQL for leaderboard
	 * @param $offset number of days from present to query for. Leave null for all-time
	 * @return string the SQL
	 */
	private function getLeaderboardSql( $offset = null ) {
		return self::concat(
			'SELECT status_user AS \'user\', COUNT(*) as \'count\'',
			'FROM copyright_diffs',
			'WHERE status_user IS NOT NULL',
			'AND status_user != "Community Tech bot"',
			$offset ? 'AND review_timestamp > ADDDATE(CURRENT_DATE, -' . $offset . ')' : '',
			'GROUP BY status_user',
			'ORDER BY COUNT(*) DESC',
			'LIMIT 5'
		);
	}

	/**
	 * @param $ithenticateId int Ithenticate ID of the report
	 * @param $value string Value of the state saved by user
	 * @param $user string the reviewer's username
	 * @param $timestamp date timestamp of when the review took place
	 * @return true|false depending on query success/fail
	 */
	public function insertCopyvioAssessment( $ithenticateId, $value, $user, $timestamp ) {
		$sql = self::concat(
			'UPDATE copyright_diffs',
			'SET status = :status, status_user = :status_user, review_timestamp = :review_timestamp',
			'WHERE ithenticate_id = :id'
		);
		return $this->update( $sql, [
			'status' => $value,
			'status_user' => $user,
			'review_timestamp' => $timestamp,
			'id' => $ithenticateId
		] );
	}

	/**
	 * Get a particular record by ithenticate ID
	 *
	 * @param $ithenticateId int ID get record for
	 */
	public function getRecordById( $ithenticateId ) {
		$sql = self::concat(
			'SELECT * FROM copyright_diffs',
			'WHERE ithenticate_id = :id'
		);
		return $this->fetch( $sql, [
			'id' => (int)$ithenticateId
		] );
	}
}
