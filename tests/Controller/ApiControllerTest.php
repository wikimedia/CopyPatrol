<?php

declare( strict_types=1 );

namespace App\Tests\Controller;

use App\Controller\ApiController;
use App\Repository\CopyPatrolRepository;
use App\Tests\SessionHelper;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \App\Controller\ApiController
 * @group integration
 */
class ApiControllerTest extends WebTestCase {

	use SessionHelper;

	protected ApiController $controller;
	protected CopyPatrolRepository $copyPatrolRepo;
	protected KernelBrowser $client;
	protected Request $request;

	protected function setUp(): void {
		parent::setUp();
		$this->client = static::createClient();
		$this->controller = new ApiController();
		$this->controller->setContainer( static::getContainer() );
		$this->copyPatrolRepo = new CopyPatrolRepository(
			static::getContainer()->get( 'cache.app' ),
			static::getContainer()->get( 'doctrine' )
		);
		$this->request = new Request( [] );
	}

	public function testFeedApiAction(): void {
		$response = $this->controller->feedApiAction( $this->request, $this->copyPatrolRepo, 'fr' );
		static::assertSame( JsonResponse::class, get_class( $response ) );
		static::assertSame( 200, $response->getStatusCode() );
	}

	public function testCaseApiAction(): void {
		$response = $this->controller->caseApiAction( $this->copyPatrolRepo, '9f1acf0a-808a-457d-bf4b-800ba86d1309' );
		static::assertSame( JsonResponse::class, get_class( $response ) );
		static::assertSame( 200, $response->getStatusCode() );
	}

}
