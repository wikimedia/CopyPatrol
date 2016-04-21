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
	 * Plagiabot constructor.
	 * @param $db array Database credentials
	 */
	public function __construct( $db ) {
		$this->linkPlagiabot = mysqli_connect( 'enwiki.labsdb', $db['user'], $db['password'], 's51306__copyright_p' );
		$this->linkProjects = mysqli_connect( 'labsdb1004.eqiad.wmnet', $db['user'], $db['password'], 's52475__wpx_p' );
	}


	/**
	 * Driver function for the class
	 * @return array Data to be rendered in html view
	 */
	public function run() {
		$viewData = $this->getPlagiarismRecords();
		foreach ( $viewData as $v ) {
			$v['projects'] = $this->getWikiProjects( $v['page'] );
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
				$result[] = $row['pi_project'];
			}
		}
		return $result;
	}


	/**
	 * @param int $n Number of records asked for
	 * @return array Data for plagiabot db records
	 */
	public function getPlagiarismRecords( $n = 30 ) {
		$query = 'SELECT * FROM copyright_diffs ORDER BY diff_timestamp DESC LIMIT ' . $n;
		if ( isset( $this->linkPlagiabot ) ) {
			$result = mysqli_query( $this->linkPlagiabot, $query );
			if ( $result->num_rows > 0 ) {
				$data = array();
				while ( $row = mysqli_fetch_assoc( $result ) ) {
					$data[]['diff'] = $row['diff'];
					$data[]['project'] = $row['lang'] . $row['project'];
					$data[]['timestamp'] = $row['diff_timestamp'];
					$data[]['page'] = $row['page_title'];
					$data[]['turnitin_report'] = $row['report'];
//					$data[ 'turnitin_report' ] = $this->getReportLink( $row[ 'ithenticate_id' ] );
				}
				return $data;
			}
		}
		// If there were no results or a connection error
		return false;
	}


	/**
	 * @param $ithenticate_id int Report id for Turnitin
	 * @return string Link to report
	 */
	public function getReportLink( $ithenticate_id ) {
		return 'https://tools.wmflabs.org/eranbot/ithenticate.py?rid=' . $ithenticate_id;
	}
}
