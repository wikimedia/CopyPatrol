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

use Wikimedia\Slimapp\Controller;

class CopyPatrol extends Controller {

	/**
	 * @var int $wikipedia String wikipedia url (enwiki by default)
	 */
	protected $wikipedia;

	/**
	 * @var $enwikiDao  \Wikimedia\Slimapp\Dao\ object for enwiki access
	 */
	protected $enwikiDao;

	/**
	 * @var $wikiprojectDao \Wikimedia\Slimapp\Dao\ object for wikiprojects access
	 */
	protected $wikiprojectDao;

	/**
	 * @param \Slim\Slim $slim Slim application
	 */
	public function __construct( \Slim\Slim $slim = null, $wiki = 'https://en.wikipedia.org' ) {
		parent::__construct( $slim );
		$this->wikipedia = $wiki;
	}

	/**
	 * @param mixed $enwikiDao
	 */
	public function setEnwikiDao( $enwikiDao ) {
		$this->enwikiDao = $enwikiDao;
	}

	/**
	 * @param mixed $wikiprojectDao
	 */
	public function setWikiprojectDao( $wikiprojectDao ) {
		$this->wikiprojectDao = $wikiprojectDao;
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
	 * page_dead: Bool. Is article page non-existent?
	 * editor_page_dead: Bool. Is editor page non-existent?
	 * editor_talk_dead: Bool. Is editor talk page non-existent?
	 */
	protected function handleGet() {
		$records = $this->getRecords();

		// nothing else needs to be done if there are no records
		if ( empty( $records ) ) {
			return $this->render( 'index.html' );
		}

		$diffIds = [];
		$pageTitles = [];

		// first build arrays of diff IDs and page titles so we can use them to make mass queries
		foreach ( $records as $record ) {
			$diffIds[] = $record['diff'];
			$pageTitles[] = $record['page_title'];
		}

		// get info for each revision (editor, editcount, etc)
		$revisions = $this->enwikiDao->getRevisionDetailsMulti( $diffIds );

		// build arrays for editors and user pages so we can make mass queries to the API
		$editors = [];
		$editorPages = [];
		$editorTalkPages = [];
		foreach ($revisions as $rev) {
			if ( isset( $rev['editor'] ) ) {
				$editors[] = $rev['editor'];
				$editorPages[] = 'User:' . $rev['editor'];
				$editorTalkPages[] = 'User_talk:' . $rev['editor'];
			}
		}

		// get edit counts of each editor
		$editCounts = $this->enwikiDao->getEditCounts( $editors );

		// find dead pages in chunks as we can only query for 50 pages max
		$deadPages = $this->enwikiDao->getDeadPages( $pageTitles );
		$deadEditorPages = $this->enwikiDao->getDeadPages( $editorPages );
		$deadEditorTalkPages = $this->enwikiDao->getDeadPages( $editorTalkPages );

		// now all external requests and database queries (except
		// WikiProjects) have been completed, let's loop through the records
		// once more to build the complete dataset to be rendered into view
		foreach ( $records as $key => $record ) {
			$records[$key]['diff_timestamp'] = $this->formatTimestamp( $record['diff_timestamp'] );
			$records[$key]['diff_link'] = $this->getDiffLink( $record['page_title'], $record['diff'] );
			$records[$key]['page_link'] = $this->getPageLink( $record['page_title'] );
			$records[$key]['history_link'] = $this->getHistoryLink( $record['page_title'] );
			$records[$key]['turnitin_report'] = $this->getReportLink( $record['ithenticate_id'] );
			$records[$key]['copyvios'] = $this->getCopyvioUrls( $record['report'] );
			$records[$key]['page_dead'] = in_array(
				$this->removeUnderscores( $record['page_title'] ), $deadPages
			);
			$editor = $revisions[$record['diff']]['editor'];
			if ( $editor ) {
				$records[$key]['editcount'] = $editCounts[$editor];
				$records[$key]['editor'] = $editor;
				$records[$key]['editor_page'] = $this->getUserPage( $editor );
				$records[$key]['editor_talk'] = $this->getUserTalk( $editor );
				$records[$key]['editor_contribs'] = $this->getUserContribs( $editor );
				$records[$key]['editor_page_dead'] = in_array( 'User:' . $editor, $deadEditorPages );
				$records[$key]['editor_talk_dead'] = in_array(
					'User talk:' . $editor, $deadEditorTalkPages
				);
			}
			if ( $records[$key]['status_user'] ) {
				$records[$key]['reviewed_by_url'] = $this->getUserPage( $record['status_user'] );
				$records[$key]['review_timestamp'] = $this->formatTimestamp( $record['review_timestamp'] );
			}
			$records[$key]['wikiprojects'] = $this->wikiprojectDao->getWikiProjects( $record['page_title'] );
			$records[$key]['page_title'] = $this->removeUnderscores( $record['page_title'] );
			$cleanWikiprojects = [];
			foreach ( $records[$key]['wikiprojects'] as $k => $wp ) {
				$wp = $this->removeUnderscores( $wp );
				$cleanWikiprojects[] = $wp;
			}
			$records[$key]['wikiprojects'] = $cleanWikiprojects;
		}
		$this->view->set( 'records', $records );
		$this->render( 'index.html' );
	}

	/**
	 * Get plagiarism records based on URL parameters and whether or not the user is logged in
	 * This function also sets view variables for the filters, which get rendered as radio options
	 *
	 * @param $lastId int Ithenticate ID of last record on page
	 * @return array collection of plagiarism records
	 */
	protected function getRecords() {
		$userData = $this->authManager->getUserData();
		$filterUser = $userData ? $userData->getName() : null;
		// Default to 'open'
		$filter = $this->request->get( 'filter' ) ? $this->request->get( 'filter' ) : 'open';
		$lastId = $this->request->get( 'lastId' ) ? $this->request->get( 'lastId' ) : 0;
		// set filter types and descriptions that will be rendered as checkboxes in the view
		$filterTypes = [
			'all' => 'All cases',
			'open' => 'Open cases',
			'reviewed' => 'Reviewed cases'
		];
		// add 'My reviews' to filter options if user is logged in
		if ( isset( $filterUser ) ) {
			$filterTypes['mine'] = 'My reviews';
		}
		// check user is logged in if filter requested is 'mine', if not, use 'open' by default
		if ( $filter === 'mine' ) {
			if ( !isset( $filterUser ) ) {
				$this->flashNow( 'warning', 'You must be logged in to view your own reviews.' );
				$filter = 'open';
			}
		} else {
			$filterTypeKeys = array_keys( $filterTypes ); // Check that the filter value was valid
			if ( !in_array( $filter, $filterTypeKeys ) ) {
				$this->flashNow(
					'error',
					'Invalid filter. Values must be one of: ' . join( $filterTypeKeys, ', ' )
				);
				$filter = 'open';  // Set to default
			}
		}
		// make this easier when working locally
		$numRecords = $_SERVER['HTTP_HOST'] === 'localhost' ? 50 : 50;
		// compile all options in an array
		$options = [
			'filter' => $filter,
			'last_id' => $lastId > 0 ? $lastId : null
		];
		if ( $filter === 'mine' && isset( $filterUser ) ) {
			$options['filter_user'] = $filterUser;
		}
		$this->view->set( 'filter', $filter );
		$this->view->set( 'filterTypes', $filterTypes );
		return $this->dao->getPlagiarismRecords( $numRecords, $options );
	}

	/**
	 * @param $page string Page title
	 * @return string url of wiki page on enwiki
	 */
	public function getPageLink( $page ) {
		return $this->wikipedia . '/wiki/' . urlencode( $page );
	}

	/**
	 * @param $page string Page title
	 * @param $diff string Diff id
	 * @return string link to diff
	 */
	public function getDiffLink( $page, $diff ) {
		return $this->wikipedia .
			'/w/index.php?title=' . urlencode( $page ) .
			'&diff=' . urlencode( $diff );
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
	 * @param $title string page title
	 * @return string the URL
	 */
	public function getHistoryLink( $title ) {
		return $this->wikipedia . '/wiki/' . urlencode( $title ) . '?action=history';
	}

	/**
	 * @param $user string User name
	 * @return string Talk page for a user on $this->wikipedia
	 */
	public function getUserTalk( $user ) {
		if ( !$user ) {
			return false;
		}
		return $this->wikipedia . '/wiki/User_talk:' . urlencode( str_replace( ' ', '_', $user ) );
	}

	/**
	 * @param $user string User name
	 * @return string User page for a user on $this->wikipedia
	 */
	public function getUserPage( $user ) {
		if ( !$user ) {
			return false;
		}
		return $this->wikipedia . '/wiki/User:' . urlencode( str_replace( ' ', '_', $user ) );
	}

	/**
	 * @param $user string User name
	 * @return string Contributions page for a user on $this->wikipedia
	 */
	public function getUserContribs( $user ) {
		if ( !$user ) {
			return false;
		}
		return $this->wikipedia . '/wiki/Special:Contributions/' .
			urlencode( str_replace( ' ', '_', $user ) );
	}

	/**
	 * Get links to compare with
	 *
	 * @param $text string Blob from db
	 * @return array matched urls
	 */
	public function getCopyvioUrls( $text ) {
		preg_match_all( '#\bhttps?://[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/))#', $text, $match );
		$uniqueCopyvioUrls = [];
		foreach ( $match[0] as $foundUrl ) {
			if ( !in_array( $foundUrl, $uniqueCopyvioUrls ) ) {
				// Determine if $value is a substring of an existing url, and if so, discard it
				// This is because of the way Plagiabot currently stores reports in its database
				// At some point, fix this in Plagiabot code instead of this hack here
				$isSubstring = false;
				foreach ( $uniqueCopyvioUrls as $u ) {
					if ( strpos( $u, $foundUrl ) !== false ) {
						$isSubstring = true;
					}
				}
				if ( $isSubstring === false ) {
					$uniqueCopyvioUrls[] = $foundUrl;
				}
			}
		}
		return $uniqueCopyvioUrls;
	}
}
