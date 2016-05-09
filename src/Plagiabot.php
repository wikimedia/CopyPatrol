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
				// Remove "Wikipedia:Wikiproject_" part from the string before use
				$project = substr( $row['pi_project'], 22 );
				// Replace underscores by spaces
				$result[] = $this->removeUnderscores( $project );
			}
		}
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
		return date( 'd-m-y h:m', $datetime );
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
				$urls[] = $value;
			}
		}
		return $urls;
	}


	/**
	 * @param $title String to change underscores to spaces for
	 * @return string
	 */
	public function removeUnderscores( $title ) {
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
}

