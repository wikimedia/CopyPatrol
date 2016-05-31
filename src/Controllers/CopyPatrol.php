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

	protected $enwikiDao;

	protected $wikiprojectDao;


	/**
	 * @param \Slim\Slim $slim Slim application
	 */
	public function __construct( \Slim\Slim $slim = null ) {
		parent::__construct( $slim );
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


	protected function handleGet() {
		$records = $this->dao->getPlagiarismRecords( 5 );
		foreach ( $records as $record ) {
			$record['timestamp'] = '100';
		}
		$this->view->set( 'records', $records );
		$this->render( 'index.html' );
	}


	/**
	 * @param $page string Page title
	 * @return string url of wiki page on enwiki
	 */
	public function getPageLink( $page ) {
		return $this->wikipedia . '/wiki/' . $page;
	}


	/**
	 * @param $page string Page title
	 * @param $diff string Diff id
	 * @return string link to diff
	 */
	public function getDiffLink( $page, $diff ) {
		return $this->wikipedia . '/w/index.php?title=' . $page . '&diff=' . $diff;
	}


	/**
	 * @param $ithenticateId int Report id for Turnitin
	 * @return string Link to report
	 */
	public function getReportLink( $ithenticateId ) {
		return 'https://tools.wmflabs.org/eranbot/ithenticate.py?rid=' . $ithenticateId;
	}


	/**
	 * @param $datetime string Datetime of edit
	 * @return string Reformatted date
	 */
	public function formatTimestamp( $datetime ) {
		$datetime = strtotime( $datetime );
		return date( 'd-m-y h:m:s', $datetime );
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
		return $this->wikipedia . '/wiki/User_talk:' . str_replace( ' ', '_', $user );
	}


	/**
	 * @param $user string User name
	 * @return string User page for a user on $this->wikipedia
	 */
	public function getUserPage( $user ) {
		if ( !$user ) {
			return false;
		}
		return $this->wikipedia . '/wiki/User:' . str_replace( ' ', '_', $user );
	}


	/**
	 * @param $user string User name
	 * @return string Contributions page for a user on $this->wikipedia
	 */
	public function getUserContribs( $user ) {
		if ( !$user ) {
			return false;
		}
		return $this->wikipedia . '/wiki/Special:Contributions/' . str_replace( ' ', '_', $user );
	}


	/**
	 * Get links to compare with
	 *
	 * @param $text string Blob from db
	 * @return array matched urls
	 */
	public function getCopyvioUrls( $text ) {
		preg_match_all( '#\bhttps?://[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/))#', $text, $match );
		$urls = array();
		foreach ( $match[0] as $value ) {
			if ( !in_array( $value, $urls ) ) {
				// Determine if $value is a substring of an existing url, and if so, discard it
				// This is because of the way Plagiabot currently stores reports in its database
				// At some point, fix this in Plagiabot code instead of this hack here
				$flag = false;
				foreach ( $urls as $u ) {
					if ( strpos( $u, $value ) !== false ) {
						$flag = true;
					}
				}
				if ( $flag === false ) {
					$urls[] = $value;
				}
			}
		}
		return $urls;
	}
}