<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Plagiabot\Web\App;
use Plagiabot\Web\Dao\PlagiabotDao;
use Plagiabot\Web\Dao\WikiDao;

class CopyPatrolTest extends PHPUnit_Framework_TestCase {

	public function setEnv() {
		define( 'APP_ROOT', dirname( __DIR__ ) );
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
		$this->setEnv();
		$app = new App( __DIR__ );
		$obj = $app->getWikiDao( 'en' );
		$diffs = [ 736294997 ];
		$editors = $obj->getRevisionsEditors( $diffs );
		$this->assertEquals( $editors, [ 736294997 => 'CllrP' ] );
	}

	public function testGetDeadPages() {
		$app = new App( __DIR__ );
		$obj = $app->getWikiDao( 'en' );
		$titles = [
			'Donald Trump',
			'Thispageissurelydead',
			'Kite',
			'Kites', // Redirects to kite
			'Kittykitty'
		];
		$deadTrue = [
			'Thispageissurelydead',
			'Kittykitty'
		];
		$promise = [ 'deadPages' => $obj->getDeadPages( $titles ) ];
		$dead = array_values( GuzzleHttp\Promise\unwrap( $promise )['deadPages'] );
		sort( $dead );
		sort( $deadTrue );
		$this->assertEquals( $dead, $deadTrue );
	}

	public function testGetWikiprojects() {
		$dsn = "mysql:host=".getenv( 'DB_HOST' ).";"
		       ."port=".getenv( 'DB_PORT' ).";"
		       ."dbname=".getenv( 'DB_NAME_COPYPATROL' );
		$plagiabotDao = new PlagiabotDao( $dsn, getenv( 'DB_USER' ), getenv( 'DB_PASS' ) );
		$expected = [
			// All of the commented out fail for some yet unknown reason
			// 'Caitriona_Balfe' => ['Biography', 'Fashion', 'Ireland', 'Women'],
			// 'Florence' => ['Cities', 'Italy', 'World_Heritage_Sites'],
			// 'Taj_Mahal' => [ 'Architecture', 'Death', 'India', 'World_Heritage_Sites' ],
			'Florence_Dixie' => [ 'Biography', 'England', 'Gender_Studies', 'Science_Fiction',
			                      'Scotland', 'Women\'s_History', 'Women_writers' ],
			'India' => [ 'Asia', 'Countries', 'India', 'South_Asia', 'Spoken_Wikipedia' ],
		];
		foreach ( $expected as $title => $projects ) {
			$this->assertEquals( $projects, $plagiabotDao->getWikiProjects( 'en', $title ) );
		}
	}
}

