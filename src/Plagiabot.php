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
	 *
	 * @param $db array Database credentials
	 */
	public function __construct( $db ) {
		$this->linkPlagiabot = mysqli_connect( 'enwiki.labsdb', $db['user'], $db['password'], 's51306__copyright_p' );
		$this->linkEnwiki = mysqli_connect( 'enwiki.labsdb', $db['user'], $db['password'], 'enwiki_p' );
		$this->linkProjects = mysqli_connect( 'labsdb1004.eqiad.wmnet', $db['user'], $db['password'], 's52475__wpx_p', 3309 );
		$this->wikipedia = 'https://en.wikipedia.org';
	}


	/**
	 * Driver function for the class
	 *
	 * @return array Data to be rendered in html view
	 */
	public function run() {
		$viewData = $this->getPlagiarismRecords( 1 );
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
					$data[$cnt]['page_dead'] = $this->checkDeadLink( $row['page_title'] );
					$data[$cnt]['user_page_dead'] = $this->checkDeadLink( 'User:' . $userDetails['editor'] );
					$data[$cnt]['user_talk_dead'] = $this->checkDeadLink( 'User_talk:' . $userDetails['editor'] );
					$cnt++;
				}
			}
			return $data;
		}
		// If there was a connection error
		return false;
	}







}

