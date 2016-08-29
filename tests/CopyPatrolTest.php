<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Plagiabot\Web\Dao\EnwikiDao;
use Plagiabot\Web\Dao\PlagiabotDao;

class CopyPatrolTest extends PHPUnit_Framework_TestCase {

	public function testGetRevisionsEditors() {
		$obj = new EnwikiDao( getenv( 'DB_DSN_ENWIKI' ), getenv( 'DB_USER' ), getenv( 'DB_PASS' ) );
		$diffs = [736294997];
		$editors = $obj->getRevisionsEditors( $diffs );
		$this->assertEquals( $editors, [736294997 => 'CllrP'] );
	}

	public function testGetDeadPages() {
		$obj = new EnwikiDao( getenv( 'DB_DSN_ENWIKI' ), getenv( 'DB_USER' ), getenv( 'DB_PASS' ) );
		$titles = [
			'Donald Trump',
			'Thispageissurelydead',
			'Kite',
			'Kites', //Redirects to kite
			'Kittykitty',
			'()*&%*$#&'
		];
		$dead = $obj->getDeadPages( $titles );
		$this->assertEquals( $dead, ['Thispageissurelydead', 'Kittykitty', '()*&%*$#&'] );
	}

	public function testGetWikiprojects() {
		$obj = new PlagiabotDao( getenv( 'DB_DSN_PLAGIABOT' ), getenv( 'DB_USER' ), getenv( 'DB_PASS' ) );
		$title = 'Caitriona_Balfe';
		$projects = ['Biography', 'Fashion', 'Ireland', 'Women'];
		$expectedProjects = $obj->getWikiProjects( $title );
		$this->assertEquals( $expectedProjects, $projects );
	}
}

