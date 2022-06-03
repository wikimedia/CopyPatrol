<?php

namespace Plagiabot\Web\Controllers;

use Exception;
use Slim\Slim;
use Wikimedia\Slimapp\Controller;
use PhpXmlRpc\Value;
use PhpXmlRpc\Request;
use PhpXmlRpc\Client;

/**
 * The Ithenticate controller receives the report ID from CopyPatrol,
 * and redirects the user to the view-only URL of the Ithenticate report.
 */
class Ithenticate extends Controller {

	/** @var Client */
	protected $client;

	/** @var string */
	protected $rid;

	/**
	 * Ithenticate constructor.
	 * @param Slim $slim
	 * @param string $rid
	 */
	public function __construct( Slim $slim, string $rid ) {
		parent::__construct( $slim );
		$this->client = new Client( 'https://api.ithenticate.com/rpc' );
		$this->rid = $rid;
	}

	/**
	 * Handle GET route for /ithenticate
	 * @return void
	 * @throws Exception
	 */
	protected function handleGet() {
		$rid = new Value( $this->rid );

		$sid = new Value( $this->getSid() );
		$response = $this->makeRequest( 'report.get', [
			'id' => $rid,
			'sid' => $sid,
		] )->scalarval();

		if ( $response['status']->scalarval() !== 200 ) {
			$this->slim->error( new Exception( 'Failed to retrieve Ithenticate report' ) );
		}

		$this->slim->redirect( $response['view_only_url']->scalarval() );
	}

	private function getSid(): string {
		$username = new Value( $this->slim->config( 'ithenticate.user' ) );
		$password = new Value( $this->slim->config( 'ithenticate.pass' ) );
		$response = $this->makeRequest( 'login', [
			'username' => $username,
			'password' => $password,
		] );
		return $response->scalarval()['sid']->scalarval();
	}

	private function makeRequest( string $method, array $params ): Value {
		$params = new Value( $params, 'struct' );
		$request = new Request( $method, [ $params ] );
		$response = $this->client->send( $request );
		if ( $response->faultCode() ) {
			throw new Exception( $response->faultString() );
		}
		return $response->value();
	}
}
