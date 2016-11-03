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

use Plagiabot\Web\Dao\PlagiabotDao;
use Plagiabot\Web\Dao\WikiDao;
use Wikimedia\Slimapp\Controller;
use GuzzleHttp;

class CopyPatrol extends Controller {

	/**
	 * @var WikiDao The DAO for Wikipedia data access.
	 */
	protected $wikiDao;

	/**
	 * @var PlagiabotDao The DAO for the CopyPatrol database.
	 */
	protected $dao;

	/**
	 * @param WikiDao $wikiDao
	 */
	public function setWikiDao( WikiDao $wikiDao ) {
		$this->wikiDao = $wikiDao;
	}

	/**
	 * Get the ORES scores URL for the current Wikipedia.
	 *
	 * @param array $revs
	 * @return string The ORES URL.
	 */
	public function oresScoresUrl( array $revs ) {
		$wikiCode = $this->wikiDao->getLang().'wiki';
		$baseUrl = "https://ores.wikimedia.org/v2/scores/$wikiCode/damaging/?revids=";
		$scoresUrl = $baseUrl . implode( '|', $revs );
		return $scoresUrl;
	}

	/**
	 * Get ORES scores for given revisions. This requires that ORES support the 'damaging' model
	 * for the current Wikipedia.
	 * @link https://ores.wikimedia.org/v2/#!/scoring/get_v2_scores
	 *
	 * @param string[] $revisions The page revisions to retrieve scores for.
	 * @return string[]|boolean The ORES scores, or false if they can't be retrieved.
	 */
	public function oresScores( array $revisions ) {
		$data = file_get_contents( $this->oresScoresUrl( $revisions ) );
		$data = json_decode( $data, true );
		if ( !array_key_exists( 'scores', $data ) ) {
			// ORES is not supported for this Wikipedia (or is down).
			return false;
		}
		$wikiCode = $this->wikiDao->getLang().'wiki';
		$data = $data['scores'][$wikiCode]['damaging']['scores'];
		$scores = [];
		foreach ( $data as $revId => $value ) {
			if ( array_key_exists( 'error', $value ) ) {
				// Revision not found
				$scores[$revId] = null;
			} else {
				$scores[$revId] = isset( $value['probability'] ) ? $value['probability']['true'] : null;
			}
		}
		return $scores;
	}

	/**
	 * Handle GET route for app
	 *
	 * @param $lastId int Ithenticate ID of the last record displayed on the
	 *   page; needed for Load More calls
	 * @return array with following params:
	 * page_title: Page with copyvio edit
	 * page_link: Link to page
	 * diff_timestamp: Timestamp of edit
	 * turnitin_report: Link to turnitin report
	 * status: 'fp' or 'tp'
	 * report: Report blob from plagiabot db
	 * copyvios: List of copyvio urls
	 * editor: Username of editor
	 * editor_page: Link to editor user page
	 * editor_talk: Link to editor talk page
	 * editor_contribs: Link to editor Special:Contributions
	 * editcount: Edit count of user
	 * editor_page_dead: Bool. Is editor page non-existent?
	 * editor_talk_dead: Bool. Is editor talk page non-existent?
	 */
	protected function handleGet() {
		$records = $this->getRecords();
		$userWhitelist = [];
		// $userWhitelist = $this->getUserWhitelist();
		// nothing else needs to be done if there are no records
		if ( empty( $records ) ) {
			return $this->render( 'index.html' );
		}
		$diffIds = [];
		$pageTitles = [];
		$usernames = [];
		// first build arrays of diff IDs and page titles so we can use them to make mass queries
		foreach ( $records as $record ) {
			$diffIds[] = $record['diff'];
			// make sure drafts have the namespace prefix
			if ( $record['page_ns'] == 118 ) {
				$record['page_title'] = 'Draft:' . $record['page_title'];
			}
			$pageTitles[] = $record['page_title'];
		}
		// get an associative array with the revision ID as the key and editor as the value
		// this makes it easier to access what we need when looping through the copyvio records
		$editors = $this->wikiDao->getRevisionsEditors( $diffIds );
		foreach ( $editors as $editor ) {
			// add username to usernames array so we can fetch their edit counts all at once
			$usernames[] = $editor;
			// push necessary titles to $pageTitles so we can mass-query if they are dead
			$pageTitles[] = 'User:' . $editor;
			$pageTitles[] = 'User talk:' . $editor;
		}
		// Asynchronously get edit counts of users,
		// and all dead pages so we can colour them red in the view
		$promises = [
			'editCounts' => $this->wikiDao->getEditCounts( $usernames ),
			'deadPages' => $this->wikiDao->getDeadPages( $pageTitles )
		];
		$asyncResults = GuzzleHttp\Promise\unwrap( $promises );
		$editCounts = $asyncResults['editCounts'];
		$deadPages = $asyncResults['deadPages'];
		// Get ORES scores for edits
		$oresScores = $this->oresScores( $diffIds );
		// now all external requests and database queries (except
		// WikiProjects) have been completed, let's loop through the records
		// once more to build the complete dataset to be rendered into view
		foreach ( $records as $key => $record ) {
			$editor = null;
			if ( isset( $record['diff'] ) && isset( $editors[$record['diff']] ) ) {
				$editor = $editors[$record['diff']];
			}

			// mark it as reviewed by our bot and skip if editor is in user whitelist
			if ( in_array( $editor, $userWhitelist ) && $this->getFilter() === 'open' ) {
				$this->autoReview( $record['ithenticate_id'] );
				unset( $records[$key] );
				continue;
			}
			if ( $record['page_ns'] == 118 ) {
				$record['page_title'] = 'Draft:' . $record['page_title'];
			}
			$pageDead = in_array(
				$this->removeUnderscores( $record['page_title'] ), $deadPages
			);
			// if the page is dead, mark it as reviewed by our bot and skip to next record
			if ( $pageDead && $this->getFilter() === 'open' ) {
				$this->autoReview( $record['ithenticate_id'] );
				unset( $records[$key] );
				continue;
			} else {
				$records[$key]['page_dead'] = $pageDead;
			}
			$records[$key]['diff_timestamp'] = $this->formatTimestamp( $record['diff_timestamp'] );
			$records[$key]['diff_link'] = $this->getDiffLink( $record['page_title'], $record['diff'] );
			$records[$key]['page_link'] = $this->getPageLink( $record['page_title'] );
			$records[$key]['history_link'] = $this->getHistoryLink( $record['page_title'] );
			$records[$key]['turnitin_report'] = $this->getReportLink( $record['ithenticate_id'] );
			$records[$key]['copyvios'] = $this->getSources( $record['report'] );
			if ( $editor ) {
				$records[$key]['editcount'] = $editCounts[$editor];
				$records[$key]['editor'] = $editor;
				$records[$key]['editor_page'] = $this->getUserPage( $editor );
				$records[$key]['editor_talk'] = $this->getUserTalk( $editor );
				$records[$key]['editor_contribs'] = $this->getUserContribs( $editor );
				$records[$key]['editor_page_dead'] = in_array( 'User:' . $editor, $deadPages );
				$records[$key]['editor_talk_dead'] = in_array( 'User talk:' . $editor, $deadPages );
			} else {
				$records[$key]['editor_page_dead'] = false;
				$records[$key]['editor_talk_dead'] = false;
			}
			if ( $records[$key]['status_user'] ) {
				$records[$key]['reviewed_by_url'] = $this->getUserPage( $record['status_user'] );
				$records[$key]['review_timestamp'] = $this->formatTimestamp( $record['review_timestamp'] );
			}
			$records[$key]['wikiprojects'] = $this->dao->getWikiProjects(
				$this->wikiDao->getLang(), $record['page_title']
			);
			$records[$key]['page_title'] = $this->removeUnderscores( $record['page_title'] );
			$cleanWikiprojects = [];
			foreach ( $records[$key]['wikiprojects'] as $k => $wp ) {
				$wp = $this->removeUnderscores( $wp );
				$cleanWikiprojects[] = $wp;
			}
			$records[$key]['wikiprojects'] = $cleanWikiprojects;
			if ( $oresScores[$record['diff']] && $oresScores[$record['diff']] > 0.427 ) {
				$value = $oresScores[$record['diff']] * 100;
				$records[$key]['oresScore'] = number_format( $value, 2 );
			}
		}
		$this->view->set( 'records', $records );

		$this->render( 'index.html' );
	}

	/**
	 * Get the current user's username, or null if they are logged out
	 *
	 * @return boolean true or false
	 */
	protected function getUsername() {
		static $username = null;
		if ( $username === null ) {
			$userData = $this->authManager->getUserData();
			$username = $userData ? $userData->getName() : null;
		}
		return $username;
	}

	/**
	 * Mark the given record as reviewed by Community Tech bot
	 *
	 * @param int $ithenticateId ID of record to review
	 */
	private function autoReview( $ithenticateId ) {
		$this->dao->insertCopyvioAssessment(
			$ithenticateId,
			'false',
			'Community Tech bot',
			gmdate( 'c' )
		);
	}

	/**
	 * Get the current requested filter, or return the default.
	 * Also throws flash messages if the requested filters are invalid.
	 *
	 * @return string the filter, one of 'all', 'open', 'reviewed' or 'mine'
	 */
	private function getFilter() {
		static $filter = null;
		// return if already set
		if ( $filter ) {
			return $filter;
		}
		// Default to 'open'
		$filter = $this->request->get( 'filter' ) ? $this->request->get( 'filter' ) : 'open';
		// check user is logged in if filter requested is 'mine', if not, use 'open' by default
		if ( $filter === 'mine' && !$this->getUsername() ) {
			$this->flashNow( 'warning', 'You must be logged in to view your own reviews.' );
			$filter = 'open';
		} else {
			$filterTypeKeys = $this->getFilterTypes(); // Check that the filter value was valid.
			if ( !in_array( $filter, $filterTypeKeys ) ) {
				$this->flashNow(
					'error',
					'Invalid filter. Values must be one of: ' . join( ', ', $filterTypeKeys )
				);
				$filter = 'open';  // Set to default
			}
		}
		return $filter;
	}

	/**
	 * Get the currently-available filter types.
	 *
	 * @return string[] Array by filter codes.
	 */
	protected function getFilterTypes() {
		static $filterTypes = null;
		if ( $filterTypes === null ) {
			$filterTypes = [ 'all', 'open', 'reviewed' ];
			// Add 'My reviews' to filter options if user is logged in.
			if ( $this->getUsername() ) {
				$filterTypes[] = 'mine';
			}
		}
		return $filterTypes;
	}

	/**
	 * Get cached user watchlist or re-fetch from wiki page and update redis
	 *
	 * @return array Usernames
	 */
	private function getUserWhitelist() {
		static $whitelist = null;
		// Don't re-fetch the whitelist over and over.
		if ( $whitelist !== null ) {
			return $whitelist;
		}
		// Get whitelist from the cache if possible.
		$cacheKey = 'copypatrol_user_whitelist';
		$cacheItem = $this->cache->getItem( $cacheKey );
		if ( $cacheItem->isHit() ) {
			$whitelist = $cacheItem->get( $cacheKey );
		} else {
			// It doesn't exist or it expired, so fetch from wiki page.
			$whitelist = $this->wikiDao->getUserWhitelist();
			// Store in the cache for 2 hours.
			$cacheItem->set( $whitelist )->expiresAfter( 2 * 60 * 60 );
			$this->cache->save( $cacheItem );
		}
		if ( $whitelist ) {
			return $whitelist;
		} else {
			return [];
		}
	}

	/**
	 * Get plagiarism records based on URL parameters and whether or not the user is logged in
	 * This function also sets view variables for the filters, which get rendered as radio options
	 *
	 * @param $lastId int Ithenticate ID of last record on page
	 * @return array collection of plagiarism records
	 */
	protected function getRecords() {
		$filter = $this->getFilter();
		$filterUser = $this->getUsername();
		$lastId = $this->request->get( 'lastId' ) ?: 0;
		$drafts = $this->request->get( 'drafts' ) ? '1' : null;
		$wikiprojects = null; // for the server and use in SQL
		$wikiprojectsArray = []; // for the clientside and showing <option>s in Select2 control
		// account for empty URL param, e.g. wikiprojects= (no value set)
		if ( $this->request->get( 'wikiprojects' ) && $this->request->get( 'wikiprojects' ) !== '' ) {
			// WikiProjects are submitted like 'Medicine|Military_History|Something'
			// This is to be URL and Select2-friendly, and use the
			//   standard pipe-separated titles we see in MediaWiki
			$wikiprojects = $this->request->get( 'wikiprojects' );
			$wikiprojectsArray = explode( '|', $wikiprojects );
		}
		// make this easier when working locally
		$numRecords = $_SERVER['HTTP_HOST'] === 'localhost' ? 10 : 50;
		// compile all options in an array
		$options = [
			'filter' => $filter,
			'last_id' => $lastId > 0 ? $lastId : null,
			'drafts' => $drafts,
			'wikiprojects' => $wikiprojects
		];
		// filter by current user if they are logged and the filter is 'mine'
		if ( $filter === 'mine' && isset( $filterUser ) ) {
			$options['filter_user'] = $filterUser;
		}
		// Set the language for the records and the view.
		$options['wikiLang'] = $this->wikiDao->getLang();
		$this->view->set( 'wikiLang', $this->wikiDao->getLang() );

		$this->view->set( 'filter', $filter );
		$this->view->set( 'drafts', $drafts );
		$this->view->set( 'draftsExist', $this->dao->draftsExist( $this->wikiDao->getLang() ) );
		$this->view->set( 'wikiprojects', $wikiprojects );
		$this->view->set( 'wikiprojectsArray', $wikiprojectsArray );
		$this->view->set( 'filterTypes', $this->getFilterTypes() );
		return $this->dao->getPlagiarismRecords( $numRecords, $options );
	}

	/**
	 * @param $page string Page title
	 * @return string URL of wiki page on Wikipedia.
	 */
	public function getPageLink( $page ) {
		return $this->wikiDao->getWikipediaUrl() . '/wiki/' . urlencode( $page );
	}

	/**
	 * @param $page string Page title
	 * @param $diff string Diff id
	 * @return string URL of the diff page on Wikipedia.
	 */
	public function getDiffLink( $page, $diff ) {
		return $this->wikiDao->getWikipediaUrl()
		       . '/w/index.php?title=' . urlencode( $page )
		       . '&diff=' . urlencode( $diff );
	}

	/**
	 * @param $ithenticateId int Report id for Turnitin
	 * @return string Link to report
	 */
	public function getReportLink( $ithenticateId ) {
		return 'https://tools.wmflabs.org/eranbot/ithenticate.py?rid=' . urlencode( $ithenticateId );
	}

	/**
	 * @param $datetime string Datetime of edit
	 * @return string Reformatted date
	 */
	public function formatTimestamp( $datetime ) {
		$datetime = strtotime( $datetime );
		return date( 'Y-m-d H:i', $datetime );
	}

	/**
	 * @param $title String to change underscores to spaces for
	 * @return string
	 */
	public function removeUnderscores( $title ) {
		return str_replace( '_', ' ', $title );
	}

	/**
	 * Get URL for revision history of given page
	 *
	 * @param $title string page title
	 * @return string the URL
	 */
	public function getHistoryLink( $title ) {
		return $this->wikiDao->getWikipediaUrl() . '/wiki/'
		       . urlencode( $title ) . '?action=history';
	}

	/**
	 * @param $user string User name
	 * @return string Talk page for a user on $this->wikipedia
	 */
	public function getUserTalk( $user ) {
		if ( !$user ) {
			return false;
		}
		$username = str_replace( ' ', '_', $user );
		return $this->wikiDao->getWikipediaUrl() . '/wiki/User_talk:' . urlencode( $username );
	}

	/**
	 * @param $user string User name
	 * @return string User page for a user on $this->wikipedia
	 */
	public function getUserPage( $user ) {
		if ( !$user ) {
			return false;
		}
		$username = str_replace( ' ', '_', $user );
		return $this->wikiDao->getWikipediaUrl() . '/wiki/User:' . urlencode( $username );
	}

	/**
	 * @param $user string User name
	 * @return string Contributions page for a user on $this->wikipedia
	 */
	public function getUserContribs( $user ) {
		if ( !$user ) {
			return false;
		}
		return $this->wikiDao->getWikipediaUrl() . '/wiki/Special:Contributions/'
		       . urlencode( str_replace( ' ', '_', $user ) );
	}

	/**
	 * Get URLs and scores for the copyvio sources
	 *
	 * @param $text string Blob from db
	 * @return array Associative array with URLs and scores
	 */
	public function getSources( $text ) {
		// matches '[new line] ... (digits followed by %) (digits) ... (the URL)[word break]'
		preg_match_all( '#\n\*.*?(\d+%)\s+(\d+).*?\b(https?://[^\s()<>]+)\b#', $text, $matches );
		// sometimes no URLs are given at all, or they are invalid. If so just return empty array
		if ( !$matches[0] ) {
			return [];
		}
		$sources = [];
		// $matches is an array containing an array of percentages, counts and urls
		// Here we collect them so that each index in $sources represents a single entity
		foreach ( $matches[1] as $index => $percentage ) {
			$sources[] = [
				'percentage' => $percentage,
				'count' => $matches[2][$index],
				'url' => $matches[3][$index]
			];
		}
		return $sources;
	}
}
