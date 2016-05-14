<?php
namespace Plagiabot\Web;

/**
 * @author Niharika Kohli
 * Main class for the project
 */
class Plagiabot {

	/**
	 * @var $linkPlagiabot \mysqli connection link for getting Plagiabot records
	 */
	private $linkPlagiabot;
	/**
	 * @var $linkProjects \mysqli connection link for getting WikiProjects
	 */
	private $linkProjects;
	/**
	 * @var $linkEnwiki \mysqli connection link for getting revision details
	 */
	private $linkEnwiki;
	/**
	 * @var $wikipedia string wikipedia project for the class instance
	 * @todo Make this customizable in future as bot runs on different wikis
	 */
	public $wikipedia;


	/**
	 * Plagiabot constructor.
	 * @param $db array Database credentials
	 */
	public function __construct( $db ) {
		$this->linkPlagiabot = mysqli_connect( 'enwiki.labsdb', $db['user'], $db['password'], 's51306__copyright_p' );
		$this->linkEnwiki = mysqli_connect( 'enwiki.labsdb', $db['user'], $db['password'], 'enwiki_p' );
		$this->linkProjects = mysqli_connect( 'labsdb1004.eqiad.wmnet', $db['user'], $db['password'], 's52475__wpx_p' );
		$this->wikipedia = 'https://en.wikipedia.org';
	}


	/**
	 * Driver function for the class
	 * @return array Data to be rendered in html view
	 */
	public function run() {
		$viewData = $this->getPlagiarismRecords();
		if ( $viewData === false ) {
			return false;
		}
		foreach ( $viewData as $k => $value ) {
			$viewData[$k]['wikiprojects'] = $this->getWikiProjects( $value['page'] );
			$viewData[$k]['page'] = $this->removeUnderscores( $value['page'] );
			$viewData[$k]['copvios'] = $this->getCopyvioUrls( $value['report'] );
		}
		return $viewData;
	}


	/**
	 * @param $title string Page title
	 * @return array Wikiprojects for a given page title on enwiki
	 */
	public function getWikiProjects( $title ) {
		$query = "SELECT * FROM projectindex WHERE pi_page = 'Talk:" . $title . "'";
		$r = mysqli_query( $this->linkProjects, $query );
		$result = array();
		if ( $r->num_rows > 0 ) {
			while ( $row = mysqli_fetch_assoc( $r ) ) {
				// Skip projects without 'Wikipoject' in title as they are partnership-based Wikiprojects
				if ( stripos( $row['pi_project'], 'Wikipedia:WikiProject_' ) !== false ) {
					// Remove "Wikipedia:Wikiproject_" part from the string before use
					$project = substr( $row['pi_project'], 22 );
					// Remove subprojects
					if ( stripos( $project, '/' ) !== false ) {
						$project = substr( $project, 0, stripos( $project, '/' ) );
					}
					// Replace underscores by spaces
					$project = ( string )$this->removeUnderscores( $project );
					$result[$project] = true;
				}
			}
		}
		$result = array_keys( $result );
		// Return alphabetized list
		sort( $result );
		return $result;
	}


	/**
	 * @param int $n Number of records asked for
	 * @return array|false Data for plagiabot db records or false if no data is not returned
	 */
	public function getPlagiarismRecords( $n = 50 ) {
		$query = 'SELECT * FROM copyright_diffs ORDER BY diff_timestamp DESC LIMIT ' . $n;
		if ( $this->linkPlagiabot ) {
			$result = mysqli_query( $this->linkPlagiabot, $query );
			if ( $result == false ) {
				return false;
			}
			$data = array();
			if ( $result->num_rows > 0 ) {
				$cnt = 0;
				while ( $row = mysqli_fetch_assoc( $result ) ) {
					$data[$cnt]['diff'] = $this->getDiffLink( $row['page_title'], $row['diff'] );
					$data[$cnt]['timestamp'] = $this->formatTimestamp( $row['diff_timestamp'] );
					$data[$cnt]['page_link'] = $this->getPageLink( $row['page_title'] );
					$data[$cnt]['page'] = $row['page_title'];
					$data[$cnt]['turnitin_report'] = $this->getReportLink( $row['ithenticate_id'] );
					$data[$cnt]['ithenticate_id'] = $row['ithenticate_id'];
					$data[$cnt]['status'] = $row['status'];
					$data[$cnt]['report'] = $row['report'];
					$data[$cnt]['copyvios'] = $this->getCopyvioUrls( $row['report'] );
					$userDetails = $this->getUserDetails( $row['diff'] );
					$data[$cnt]['editor'] = $userDetails['editor'];
					$data[$cnt]['editor_page'] = $this->getUserPage( $userDetails['editor'] );
					$data[$cnt]['editor_talk'] = $this->getUserTalk( $userDetails['editor'] );
					$data[$cnt]['editor_contribs'] = $this->getUserContribs( $userDetails['editor'] );
					$data[$cnt]['editcount'] = $userDetails['editcount'];
					$cnt++;
				}
			}
			return $data;
		}
		// If there was a connection error
		return false;
	}


	/**
	 * @param $page string Page title
	 * @return string url of wiki page on enwiki
	 */
	public function getPageLink( $page ) {
//		$this->checkDeadLink( $page );
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
	 * Get links to compare with
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


	/**
	 * @param $title String to change underscores to spaces for
	 * @return string
	 */
	public
	function removeUnderscores( $title ) {
		return str_replace( '_', ' ', $title );
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


	/**
	 * @param $diff int Diff revision ID
	 */
	public function getUserDetails( $diff ) {
		$query = "SELECT r.rev_id, r.rev_user, r.rev_user_text, u.user_editcount, u.user_name
				  FROM revision r
				  JOIN user u ON r.rev_user = u.user_id
				  WHERE r.rev_id = " . (int)$diff;
		$data = array(
			'editor' => false,
			'editcount' => false
		);
		$result = mysqli_query( $this->linkEnwiki, $query );
		if ( $result == false ) {
			return $data;
		} elseif ( $result->num_rows > 0 ) {
			while ( $row = mysqli_fetch_assoc( $result ) ) {
				$data['editor'] = $row['rev_user_text'];
				$data['editcount'] = $row['user_editcount'];
			}
		}
		return $data;
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

//
//	/**
//	 * We do an API query here because testing proved API query to be faster for looking up deleted page titles
//	 * @param $title string Page title
//	 * @return true|false depending on page dead or alive
//	 */
//	public function checkDeadLink( $title ) {
//		$url = $this->wikipedia . '/w/api.php?action=query&format=json&titles=' . $title . '&formatversion=2';
//		$ch = curl_init();
//		curl_setopt( $ch, CURLOPT_URL, $url );
//		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
//		$result = curl_exec( $ch );
//		$json = json_decode( $result );
////		if ( $json->query->pages->missing ) {
////			echo 'Woot!';
////		}
//		var_dump( $json->query->pages );
//	}
}

