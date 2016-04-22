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
		foreach ( $viewData as $k => $value ) {
			$viewData[$k]['wikiprojects'] = $this->getWikiProjects( $value['page'] );
			$value['page'] = $this->removeUnderscores( $value['page'] );
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
	 * @return array Data for plagiabot db records
	 */
	public function getPlagiarismRecords( $n = 20 ) {
		$query = 'SELECT * FROM copyright_diffs ORDER BY diff_timestamp DESC LIMIT ' . $n;
		if ( isset( $this->linkPlagiabot ) ) {
			$result = mysqli_query( $this->linkPlagiabot, $query );
			if ( $result->num_rows > 0 ) {
				$data = array();
				$cnt = 0;
				while ( $row = mysqli_fetch_assoc( $result ) ) {
					$data[$cnt]['diff'] = $this->getDiffLink( $row['page_title'], $row['diff'] );
					$data[$cnt]['timestamp'] = $this->formatTimestamp( $row['diff_timestamp'] );
					$data[$cnt]['page_link'] = $this->getPageLink( $row['page_title'] );
					// Replace underscores with spaces
					$data[$cnt]['page'] = $row['page_title'];
					$data[$cnt]['turnitin_report'] = $this->getReportLink( $row['ithenticate_id'] );
					$cnt++;
				}
				return $data;
			}
		}
		// If there were no results or a connection error
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
	 * @param $ithenticate_id int Report id for Turnitin
	 * @return string Link to report
	 */
	public function getReportLink( $ithenticate_id ) {
		return 'https://tools.wmflabs.org/eranbot/ithenticate.py?rid=' . $ithenticate_id;
	}


	/**
	 * @param $datetime string Datetime of edit
	 * @return string Reformatted date
	 */
	public function formatTimestamp( $datetime ) {
		$datetime = strtotime( $datetime );
		return date( 'd-m-y', $datetime );
	}


	/**
	 * @param $title String to change underscores to spaces for
	 * @return string
	 */
	private function removeUnderscores( $title ) {
		return str_replace( '_', ' ', $title );
	}
}

