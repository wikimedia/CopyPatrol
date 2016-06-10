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
		$records = $this->dao->getPlagiarismRecords();
		foreach ( $records as $key => $record ) {
			$editor = $this->enwikiDao->getUserDetails( $record['diff'] );
			$records[$key]['diff_timestamp'] = $this->formatTimestamp( $record['diff_timestamp'] );
			$records[$key]['diff'] = $this->getDiffLink( $record['page_title'], $record['diff'] );
			$records[$key]['page_link'] = $this->getPageLink( $record['page_title'] );
			$records[$key]['turnitin_report'] = $this->getReportLink( $record['ithenticate_id'] );
			$records[$key]['copyvios'] = $this->getCopyvioUrls( $record['report'] );
			$records[$key]['editor'] = $editor['editor'];
			$records[$key]['editor_page'] = $this->getUserPage( $editor['editor'] );
			$records[$key]['editor_talk'] = $this->getUserTalk( $editor['editor'] );
			$records[$key]['editor_contribs'] = $this->getUserContribs( $editor['editor'] );
			$records[$key]['editcount'] = $editor['editcount'];
			$records[$key]['page_dead'] = $this->enwikiDao->isPageDead( $record['page_title'] );
			if ( $editor['editor'] ) {
				$records[$key]['editor_page_dead'] = $this->enwikiDao->isPageDead( 'User:' . $editor['editor'] );
				$records[$key]['editor_talk_dead'] = $this->enwikiDao->isPageDead( 'User_talk:' . $editor['editor'] );
			} else {
				$records[$key]['editor_page_dead'] = false;
				$records[$key]['editor_talk_dead'] = false;
			}
			$records[$key]['reviewed'] = $record['status_user'] ? true : false;
			if ( $records[$key]['reviewed'] ) {
				$records[$key]['reviewed_by_url'] = $this->getUserPage( $record['status_user'] );
				$records[$key]['review_timestamp'] = $this->formatTimestamp( $record['review_timestamp'] );
			}
			$records[$key]['wikiprojects'] = $this->wikiprojectDao->getWikiProjects( $record['page_title'] );
			$records[$key]['page_title'] = $this->removeUnderscores( $record['page_title'] );
			$cleanWikiprojects = array();
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
		return $this->wikipedia . '/w/index.php?title=' . urlencode( $page ) . '&diff=' . urlencode( $diff );
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
		return date( 'Y-m-d h:m', $datetime );
	}


	/**
	 * @param $title String to change underscores to spaces for
	 * @return string
	 */
	public function removeUnderscores( $title ) {
		return str_replace( '_', ' ', $title );
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
		return $this->wikipedia . '/wiki/Special:Contributions/' . urlencode( str_replace( ' ', '_', $user ) );
	}


	/**
	 * Get links to compare with
	 *
	 * @param $text string Blob from db
	 * @return array matched urls
	 */
	public function getCopyvioUrls( $text ) {
		preg_match_all( '#\bhttps?://[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/))#', $text, $match );
		$uniqueCopyvioUrls = array();
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