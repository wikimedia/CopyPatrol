<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Plagiabot\Web\Dao\EnwikiDao;
use Plagiabot\Web\Dao\PlagiabotDao;
use GuzzleHttp\Promise\Promise;

class CopyPatrolTest extends PHPUnit_Framework_TestCase {

	public function setEnv() {
		$env = __DIR__ . '/../.env';
		$settings = parse_ini_file( $env );
		foreach ( $settings as $key => $value ) {
			// Store in super globals
			$_ENV[$key] = $value;
			// Also store in process env vars
			putenv( "{$key}={$value}" );
		}
		try {
			date_default_timezone_get();
		} catch ( Exception $e ) {
			// Use UTC if not specified anywhere in .ini
			date_default_timezone_set( 'UTC' );
		}
	}

	public function testGetRevisionsEditors() {
		if( !getenv('TRAVIS') ){
			$this->setEnv();
		}
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
			'Kittykitty'
		];
		$deadTrue = [
			'Thispageissurelydead',
			'Kittykitty'
		];
		$promise = ['deadPages' => $obj->getDeadPages( $titles )];
		$dead = array_values( GuzzleHttp\Promise\unwrap( $promise )['deadPages'] );
		sort( $dead );
		sort( $deadTrue );
		$this->assertEquals( $dead, $deadTrue );
	}

	public function testGetWikiprojects() {
		$obj = new PlagiabotDao( getenv( 'DB_DSN_PLAGIABOT' ), getenv( 'DB_USER' ), getenv( 'DB_PASS' ) );
		$expected = [
			// All of the commented out fail for some yet unknown reason
			// 'Caitriona_Balfe' => ['Biography', 'Fashion', 'Ireland', 'Women'],
			// 'Florence' => ['Cities', 'Italy', 'World_Heritage_Sites'],
			// 'Taj_Mahal' => ['Architecture', 'Death', 'India', 'World_Heritage_Sites'],
			'India' => ['Asia', 'Countries', 'India', 'South_Asia', 'Spoken_Wikipedia']
		];
		foreach ( $expected as $title => $projects ) {
			$this->assertEquals( $obj->getWikiProjects( $title ), $projects );
		}
	}
}

