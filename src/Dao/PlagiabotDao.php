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
	 *   string 'wikiprojects' pipe-separated list of wikiprojects
	 *   string 'wikiLang' The language code of the Wikipedia to query for
	 * @return array|false Data for plagiabot db records or false if no data
	 *   is not returned
	 */
	public function getPlagiarismRecords( $n = 50, $options ) {
		$filters = [];
		$filterSql = '';
		$wikiprojectsSql = '';
		$lastId = isset( $options['last_id'] ) ? $options['last_id'] : null;
		$filter = isset( $options['filter'] ) ? $options['filter'] : 'all';
		$filterUser = isset( $options['filter_user'] ) ? $options['filter_user'] : null;
		$wikiprojects = isset( $options['wikiprojects'] ) ? $options['wikiprojects'] : null;
		$wikiLang = isset( $options['wikiLang'] ) ? $options['wikiLang'] : 'en';
		$preparedParams = [];

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
		if ( $filterUser ) {
			$filters[] = "status_user = '$filterUser'";
		}
		// see if this is a load more click
		if ( $lastId ) {
			$filters[] = "ithenticate_id < '$lastId'";
		}
		// filtering to draft namespace
		if ( isset( $options['drafts'] ) ) {
			$filters[] = 'page_ns = 118';
		}

		// set up SQL to return pages in given WikiProjects if requested
		if ( $wikiprojects ) {
			// All spaces are underscores in the database
			$wikiprojects = array_map( function ( $wp ) {
				return str_replace( ' ', '_', $wp );
			}, explode( '|', $wikiprojects ) );

			$wikiprojectsSql = self::concat(
				'INNER JOIN wikiprojects',
				'ON wp_page_title = page_title'
			);

			// set up prepared params
			$bindKeys = array_slice( range( 'a', 'z' ), 0, count( $wikiprojects ) );
			$preparedParams = array_combine( $bindKeys, $wikiprojects );
			$bindParams = implode( ', ', $this::makeBindParams( $bindKeys ) );

			$filters[] = "wp_project IN ($bindParams)";
		}

		// Only fetch entries from the required language Wikipedia.
		$filters[] = "lang = :lang";
		$preparedParams['lang'] = $wikiLang;

		// show only records after June 20, 2016; See phab:T138317
		$filters[] = "diff_timestamp > 20160620000000";

		// construct necessary SQL based on filters
		if ( !empty( $filters ) ) {
			$filterSql = self::buildWhere( $filters );
		}

		$sql = self::concat(
			'SELECT * FROM copyright_diffs',
			$wikiprojectsSql,
			$filterSql,
			'GROUP BY id',
			'ORDER BY diff_timestamp DESC',
			'LIMIT ' . $n
		);

		return $this->fetchAll( $sql, $preparedParams );
	}

	/**
	 * Get the top reviewers over the past last 7 days, 30 days, and all-time
	 * @param string $lang The language code of the Wikipedia in use.
	 * @return array Associative array of leaderboard data
	 */
	public function getLeaderboardData( $lang ) {
		$lastWeek = $this->fetchAll(
			$this->getLeaderboardSql( '7', $lang ), [ 'lang' => $lang ]
		);
		$lastMonth = $this->fetchAll(
			$this->getLeaderboardSql( '30', $lang ), [ 'lang' => $lang ]
		);
		$allTime = $this->fetchAll(
			$this->getLeaderboardSql( null, $lang ), [ 'lang' => $lang ]
		);
		return [
			'last-week' => $lastWeek,
			'last-month' => $lastMonth,
			'all-time' => $allTime
		];
	}

	/**
	 * Get SQL for leaderboard
	 * @param integer $offset Number of days from present to query for. Leave null for all-time
	 * @param string $lang The language code.
	 * @return string the SQL
	 */
	private function getLeaderboardSql( $offset = null, $lang = 'en' ) {
		return self::concat(
			'SELECT status_user AS \'user\', COUNT(*) as \'count\'',
			'FROM copyright_diffs',
			'WHERE status_user IS NOT NULL',
			'AND status_user != "Community Tech bot"',
			$offset ? 'AND review_timestamp > ADDDATE(CURRENT_DATE, -' . $offset . ')' : '',
			'AND lang = :lang',
			'GROUP BY status_user',
			'ORDER BY COUNT(*) DESC',
			'LIMIT 10'
		);
	}

	/**
	 * Get the names of WikiProjects that a particular Wikipedia page belongs to.
	 * @param string $lang The language code.
	 * @param string $title Page title. If null, all projects of $lang will be returned.
	 * @return string Alphabetical list of WikiProjects
	 */
	public function getWikiProjects( $lang, $title = null ) {
		$whereTitle = '';
		$params = [ 'lang' => $lang ];
		if ( !is_null( $title ) ) {
			$whereTitle = 'AND wp_page_title = :title';
			$params['title'] = $title;
		}
		$query = self::concat(
			'SELECT wp_project FROM wikiprojects',
			'WHERE  wp_lang = :lang',
			$whereTitle,
			'ORDER BY wp_project ASC'
		);

		// extract out the WikiProject names from the result set
		$wikiprojects = array_column(
			$this->fetchAll( $query, $params ),
			'wp_project'
		);

		return $wikiprojects;
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

	/**
	 * Get the list of Wikipedias (i.e. languages) that are currently supported by CopyPatrol.
	 * @return string[] Language codes.
	 */
	public function getLanguages() {
		$sql = "SELECT `lang` FROM `copyright_diffs` GROUP BY `lang`";
		$langs = $this->fetchAll( $sql );
		$languages = [];
		foreach ( $langs as $l ) {
			$languages[] = $l['lang'];
		}
		return $languages;
	}

	/**
	 * Find out whether a particular language has any drafts.
	 * @param string $lang The Wikipedia language code
	 * @return boolean
	 */
	public function draftsExist( $lang = 'en' ) {
		$sql = 'SELECT COUNT(*) AS total FROM copyright_diffs WHERE page_ns = :ns AND lang = :lang';
		$results = $this->fetchAll( $sql, [ 'ns' => WikiDao::NS_ID_DRAFTS, 'lang' => $lang ] );
		return $results[0]['total'] > 0;
	}
}
