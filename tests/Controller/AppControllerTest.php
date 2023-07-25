<?php

declare( strict_types=1 );

namespace App\Tests\Controller;

use App\Controller\AppController;
use App\Model\Record;
use App\Repository\CopyPatrolRepository;
use App\Repository\WikiRepository;
use App\Tests\SessionHelper;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \App\Controller\AppController
 * @group integration
 */
class AppControllerTest extends WebTestCase {

	use SessionHelper;

	protected AppController $controller;
	protected CopyPatrolRepository $copyPatrolRepo;
	protected WikiRepository $wikiRepo;
	protected KernelBrowser $client;
	protected Request $request;

	protected function setUp(): void {
		parent::setUp();
		$this->client = static::createClient();
		$this->controller = new AppController();
		$this->controller->setContainer( static::getContainer() );
		$this->copyPatrolRepo = new CopyPatrolRepository(
			static::getContainer()->get( 'cache.app' ),
			static::getContainer()->get( 'doctrine' )
		);
		$this->wikiRepo = new WikiRepository(
			static::getContainer()->get( 'Wikimedia\ToolforgeBundle\Service\ReplicasClient' ),
			static::getContainer()->get( 'http_client' ),
			static::getContainer()->get( 'cache.app' ),
			''
		);
		$this->request = new Request( [] );
	}

	public function testIndex(): void {
		$this->request->cookies->set( 'copypatrolLang', 'fr' );
		$response = $this->controller->index( $this->request );
		static::assertSame( RedirectResponse::class, get_class( $response ) );
		static::assertTrue( $response->isRedirect() );
	}

	public function testDecorateRecords(): void {
		$this->wikiRepo->setLang( 'en' );
		$data = [
			'diff_id' => 14177,
			'project' => 'wikipedia',
			'lang' => 'en',
			'page_namespace' => 118,
			'page_title' => 'Dlandstudio',
			'rev_id' => 726110578,
			'rev_parent_id' => 0,
			'rev_timestamp' => '20160620030345',
			'rev_user_text' => 'Jessetwo',
			'submission_id' => '23324459',
			'status' => CopyPatrolRepository::STATUS_READY,
			'status_timestamp' => null,
			'status_user_text' => null,
		];
		$expectedRecord = new Record( array_merge( $data, [
			'sources' => [
				[
					'source_id' => 28691,
					'url' => 'http://www.bceq.org/wp-content/uploads/2012/02/WCS_NOAA_dl_110811.pdf',
					'percent' => 51.0,
				]
			]
		] ), 11, false, false, true );
		$data = array_merge( $data, [
			'source_id' => 28691,
			'url' => 'http://www.bceq.org/wp-content/uploads/2012/02/WCS_NOAA_dl_110811.pdf',
			'percent' => 51.0,
		] );
		$records = $this->controller->decorateRecords( [ $data ], $this->wikiRepo );
		static::assertEquals( $expectedRecord, $records[14177] );
	}
}
