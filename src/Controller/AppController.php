<?php

namespace App\Controller;

use App\Model\Record;
use App\Repository\CopyPatrolRepository;
use App\Repository\WikiRepository;
use DateTime;
use Doctrine\DBAL\Exception\DriverException;
use Exception;
use Krinkle\Intuition\Intuition;
use PhpXmlRpc\Client;
use PhpXmlRpc\Value;
use stdClass;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
// phpcs:ignore MediaWiki.Classes.UnusedUseStatement.UnusedUse
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AppController extends AbstractController {

	public const ITHENTICATE_V2_TIMESTAMP = '2023-07-15T12:00:00Z';
	public const ERROR_WRONG_USER = 'wrong_user';
	public const ERROR_NOT_LOGGED_IN = 'unauthorized';
	public const ERROR_DATABASE = 'database';
	public const ERROR_BLOCKED = 'blocked';
	public const ERROR_CODES = [
		self::ERROR_WRONG_USER => Response::HTTP_UNAUTHORIZED,
		self::ERROR_NOT_LOGGED_IN => Response::HTTP_UNAUTHORIZED,
		self::ERROR_DATABASE => Response::HTTP_INTERNAL_SERVER_ERROR,
		self::ERROR_BLOCKED => Response::HTTP_FORBIDDEN
	];

	/**
	 * @Route("/", name="home")
	 * @param Request $request
	 * @return Response
	 */
	public function index( Request $request ): Response {
		$requestedLang = $request->cookies->get( 'copypatrolLang', 'en' );
		return $this->redirectToRoute( 'feed', [ 'lang' => $requestedLang ] );
	}

	/**
	 * @Route("/{lang}", name="feed", requirements={"lang"="simple|\w{2}"})
	 * @param Request $request
	 * @param CopyPatrolRepository $copyPatrolRepo
	 * @param WikiRepository $wikiRepo
	 * @param string $lang
	 * @return Response
	 */
	public function feedAction(
		Request $request,
		CopyPatrolRepository $copyPatrolRepo,
		WikiRepository $wikiRepo,
		string $lang
	): Response {
		$wikiRepo->setLang( $lang );

		/** @var stdClass $currentUser */
		$currentUser = $request->getSession()->get( 'logged_in_user' );
		if ( $currentUser && $wikiRepo->isUserBlocked( $currentUser->username ) ) {
			return $this->render( 'blocked.html.twig', [ 'lang' => $lang, 'username' => $currentUser->username ] );
		}

		// Filtering options that we pass to the repository.
		$options = [
			'lang' => $lang,
			'filter' => $request->query->get( 'filter', CopyPatrolRepository::FILTER_OPEN ),
			'filter_user' => $request->query->get( 'filter' ) === CopyPatrolRepository::FILTER_REVIEWED
				// You can only filter by user for 'reviewed' cases.
				? $request->query->get( 'filterUser' )
				: null,
			'filter_page' => $request->query->get( 'filterPage' ) ?: null,
			'drafts' => $request->query->get( 'drafts' ) ?: false,
			// This refers to the revision ID.
			'revision' => $request->query->get( 'revision' ) ?: null,
			// This refers to the submission ID (aka iThenticate ID).
			'id' => $request->query->get( 'id' ) ?: null,
			// Refers to submission ID, used for OFFSET.
			'last_id' => $request->get( 'lastid', 0 ),
		];

		// Everything we need for the view, including the filtering options.
		$ret = array_merge( $options, [
			'permalink' => false,
			'filter_types' => CopyPatrolRepository::FILTER_TYPES,
			'drafts_exist' => $copyPatrolRepo->draftsExist( $lang ),
			'has_wikiprojects' => $wikiRepo->hasWikiProjects(),
		] );

		// if an ID is set, we want to show just that record
		if ( $options['id'] ) {
			$ret['permalink'] = true;
			$ret['filter'] = CopyPatrolRepository::FILTER_ALL;
			$ret['records'] = $copyPatrolRepo->getPlagiarismRecords( [ 'id' => $options['id'] ], 1 );

			// if the requested record is for a different language, redirect
			if ( $ret['records'] && $ret['records'][0]['lang'] !== $lang ) {
				return $this->redirectToRoute( 'feed', [
					'lang' => $ret['records'][0]['lang'],
					'id' => $options['id']
				] );
			}
		} else {
			$ret['records'] = $copyPatrolRepo->getPlagiarismRecords( $options );
		}

		// Transform to Record objects, so we can easily call methods in the view.
		$ret['records'] = $this->decorateRecords( $ret['records'], $wikiRepo );

		// Add the logged in user's rights to the response, so we can conditionally show links for sysops.
		$ret['user_rights'] = $currentUser ? $wikiRepo->getUserRights( $currentUser->username ) : [];

		return $this->render( 'feed.html.twig', $ret );
	}

	/**
	 * Returns a Record object, with additional data such as user edit counts, which pages are dead, and ORES scores.
	 *
	 * @param array $rows
	 * @param WikiRepository $wikiRepo
	 * @return Record[]
	 */
	public function decorateRecords( array $rows, WikiRepository $wikiRepo ): array {
		$revIds = [];
		$titlesByNs = [];
		$usernames = [];
		$newRows = [];

		// First build arrays of diff IDs, page titles, and usernames so we can use them to make mass queries.
		// We also restructure the $rows array to have just one row per diff_id, merging all the source URLs
		// into the 'sources' property.
		foreach ( $rows as $row ) {
			if ( !isset( $newRows[$row['diff_id']] ) ) {
				$newRows[$row['diff_id']] = $row;
			}
			$newRows[$row['diff_id']]['sources'][] = [
				'source_id' => $row['source_id'],
				'url' => $row['url'],
				'percent' => $row['percent'],
				'description' => $row['description'],
			];
			unset( $newRows[$row['diff_id']]['url'] );
			unset( $newRows[$row['diff_id']]['source_id'] );
			unset( $newRows[$row['diff_id']]['percent'] );
			$revIds[] = $row['rev_id'];
			$usernames[] = $row['rev_user_text'];
			$titlesByNs[$row['page_namespace']][] = $row['page_title'];
			$titlesByNs['2'][] = $row['rev_user_text'];
			$titlesByNs['3'][] = $row['rev_user_text'];
		}

		$editCounts = $wikiRepo->getEditCounts( array_unique( $usernames ) );
		$livePages = $wikiRepo->getLivePagesWithWikiProjects( $titlesByNs );
		$oresScores = $wikiRepo->getOresScores( array_unique( $revIds ) );
		$tagsAndComments = $wikiRepo->getRevisionMetadata( $revIds );

		// Create a Record object for each row.
		return array_map( static function ( $row ) use ( $editCounts, $livePages, $oresScores, $tagsAndComments ) {
			// $tagsAndComments aren't indexed by rev_id, so we need to locate the row first.
			$extraData = array_filter( $tagsAndComments, static function ( $data ) use ( $row ) {
				return $data['rev_id'] === $row['rev_id'];
			}, ARRAY_FILTER_USE_BOTH );

			if ( $extraData ) {
				$extraData = array_values( $extraData )[0];
				// Basic revision info all goes in the $row data.
				$row = array_merge( $row, $extraData );
			}

			return new Record(
				$row,
				$editCounts[$row['rev_user_text']] ?? null,
				isset( $livePages[$row['page_namespace']][$row['page_title']] ),
				isset( $livePages['2'][$row['rev_user_text']] ),
				isset( $livePages['3'][$row['rev_user_text']] ),
				$livePages[$row['page_namespace']][$row['page_title']] ?? [],
				$oresScores[$row['rev_id']]
			);
		}, $newRows );
	}

	/**
	 * @Route("/{lang}/leaderboard", name="leaderboard", requirements={"lang"="simple|\w{2}"})
	 * @param CopyPatrolRepository $repository
	 * @param string $lang
	 * @return Response
	 */
	public function leaderboardAction( CopyPatrolRepository $repository, string $lang ): Response {
		return $this->render( 'leaderboard.html.twig', [
			'lang' => $lang,
			'data' => $repository->getLeaderboardData( $lang ),
		] );
	}

	/**
	 * @Route("/{lang}/review_add/{submissionId}/{status}",
	 *     name="add_review",
	 *     requirements={"lang"="simple|\w{2}", "id"="\d+", "status"="\d"},
	 *     methods={"PUT"}
	 * )
	 * @param RequestStack $requestStack
	 * @param CopyPatrolRepository $copyPatrolRepo
	 * @param WikiRepository $wikiRepo
	 * @param string $lang
	 * @param string $submissionId
	 * @param int $status
	 * @return JsonResponse
	 */
	public function addReviewAction(
		RequestStack $requestStack,
		CopyPatrolRepository $copyPatrolRepo,
		WikiRepository $wikiRepo,
		string $lang,
		string $submissionId,
		int $status
	): JsonResponse {
		$wikiRepo->setLang( $lang );

		/** @var stdClass $currentUser */
		$currentUser = $requestStack->getSession()->get( 'logged_in_user' );
		if ( !$currentUser ) {
			return $this->getErrorResponse( self::ERROR_NOT_LOGGED_IN );
		}

		if ( $wikiRepo->isUserBlocked( $currentUser->username ) ) {
			return $this->getErrorResponse( self::ERROR_BLOCKED );
		}

		$existingRecord = $copyPatrolRepo->getRecordBySubmissionId( $submissionId );
		$timestamp = date( 'YmdHis' );

		try {
			$copyPatrolRepo->updateCopyvioAssessment( $submissionId, $status, $currentUser->username, $timestamp );
			$record = new Record( array_merge( $existingRecord, [
				'status' => $status,
				'status_user_text' => $currentUser->username,
				'status_timestamp' => $timestamp,
			] ) );
			return new JsonResponse( $record->getStatusJson(), Response::HTTP_OK );
		} catch ( DriverException $e ) {
			// Tools-db maintenance, most likely.
			return $this->getErrorResponse( self::ERROR_DATABASE );
		}
	}

	/**
	 * @Route("/{lang}/review_undo/{submissionId}",
	 *     name="undo_review",
	 *     requirements={"lang"="simple|\w{2}", "submissionId"="\d+|[a-z\d+\-]+"},
	 *     methods={"PUT"}
	 * )
	 * @param RequestStack $requestStack
	 * @param CopyPatrolRepository $copyPatrolRepo
	 * @param WikiRepository $wikiRepo
	 * @param string $lang
	 * @param string $submissionId
	 * @return JsonResponse
	 */
	public function undoReviewAction(
		RequestStack $requestStack,
		CopyPatrolRepository $copyPatrolRepo,
		WikiRepository $wikiRepo,
		string $lang,
		string $submissionId
	): JsonResponse {
		$wikiRepo->setLang( $lang );

		$currentUser = $requestStack->getSession()->get( 'logged_in_user' );
		if ( !$currentUser ) {
			return $this->getErrorResponse( self::ERROR_NOT_LOGGED_IN );
		}

		if ( $wikiRepo->isUserBlocked( $currentUser->username ) ) {
			return $this->getErrorResponse( self::ERROR_BLOCKED );
		}

		$existingRecord = $copyPatrolRepo->getRecordBySubmissionId( $submissionId );
		if ( $currentUser->username !== ( $existingRecord['status_user_text'] ?? null ) ) {
			return $this->getErrorResponse( self::ERROR_WRONG_USER );
		}

		try {
			$copyPatrolRepo->updateCopyvioAssessment( $submissionId, CopyPatrolRepository::STATUS_READY, null, null );
			return new JsonResponse( [], Response::HTTP_OK );
		} catch ( DriverException $e ) {
			return $this->getErrorResponse( self::ERROR_DATABASE );
		}
	}

	/**
	 * @Route("/ithenticate/{submissionId}", name="ithenticate", requirements={"submissionId"="\d+|[a-z\d+\-]+"})
	 * @param HttpClientInterface $httpClient
	 * @param RequestStack $requestStack
	 * @param CopyPatrolRepository $copyPatrolRepo
	 * @param Intuition $intuition
	 * @param string $iThenticateUser
	 * @param string $iThenticatePassword
	 * @param string $submissionId
	 * @return RedirectResponse
	 * @throws Exception
	 */
	public function iThenticateAction(
		HttpClientInterface $httpClient,
		RequestStack $requestStack,
		CopyPatrolRepository $copyPatrolRepo,
		Intuition $intuition,
		string $iThenticateUser,
		string $iThenticatePassword,
		string $submissionId
	): RedirectResponse {
		$record = $copyPatrolRepo->getRecordBySubmissionId( $submissionId );
		$v2DateTime = new DateTime( self::ITHENTICATE_V2_TIMESTAMP );
		if ( new DateTime( $record['rev_timestamp'] ) > $v2DateTime ) {
			// New system.
			$loggedInUser = $requestStack->getSession()->get( 'logged_in_user' )->username;
			if ( $loggedInUser === null ) {
				return $this->redirectToRoute( 'toolforge_login', [
					'callback' => $this->generateUrl( 'toolforge_oauth_callback', [
						'redirect' => $requestStack->getCurrentRequest()->getUri(),
					] )
				] );
			}
			return $this->redirectToTcaViewer( $httpClient, $loggedInUser, $intuition->getLang(), $submissionId );
		}

		$client = new Client( 'https://api.ithenticate.com/rpc' );
		$rid = new Value( $submissionId );
		$sid = new Value( $this->getSid( $client, $iThenticateUser, $iThenticatePassword ) );
		$response = $this->makeRpcRequest( $client, 'report.get', [
			'id' => $rid,
			'sid' => $sid,
		] )->scalarval();

		if ( $response['status']->scalarval() !== 200 ) {
			throw new Exception( 'Failed to retrieve Ithenticate report' );
		}

		return $this->redirect( $response['view_only_url']->scalarval() );
	}

	/**
	 * @param HttpClientInterface $client
	 * @param string $loggedInUser
	 * @param string $locale
	 * @param string $id
	 * @param int $retries
	 * @return RedirectResponse
	 */
	private function redirectToTcaViewer(
		HttpClientInterface $client,
		string $loggedInUser,
		string $locale,
		string $id,
		int $retries = 0
	): RedirectResponse {
		// Load config settings. This lives in .copypatrol.ini and is shared with the bot.
		$projectDir = $this->getParameter( 'kernel.project_dir' );
		$config = parse_ini_file( "$projectDir/.copypatrol.ini" );
		// Request the viewer URL from Turnitin.
		$response = $client->request(
			'POST',
			"https://{$config['domain']}/api/v1/submissions/$id/viewer-url",
			[
				'json' => [
					'viewer_user_id' => $loggedInUser,
					'locale' => $locale,
				],
				'headers' => [
					'Authorization' => "Bearer {$config['key']}",
					'From' => 'copypatrol@toolforge.org',
					'User-Agent' => "copypatrol/{$config['version']}",
					'X-Turnitin-Integration-Name' => 'CopyPatrol',
					'X-Turnitin-Integration-Version' => $config['version'],
				],
			]
		);

		if ( $response->getStatusCode() !== Response::HTTP_OK ) {
			if ( $retries > 5 ) {
				throw new HttpException(
					Response::HTTP_BAD_GATEWAY,
					'Failed to fetch URL from the Turnitin Core API'
				);
			}
			sleep( $retries + 1 );
			return $this->redirectToTcaViewer( $client, $loggedInUser, $locale, $id, $retries + 1 );
		}

		return $this->redirect( json_decode( $response->getContent() )->viewer_url );
	}

	/**
	 * ToolforgeBundle's logout apparently doesn't work :(
	 *
	 * @Route("/logout", name="logout")
	 * @param SessionInterface $session
	 * @return RedirectResponse
	 */
	public function logoutAction( SessionInterface $session ): RedirectResponse {
		$session->remove( 'logged_in_user' );
		$session->invalidate();
		return $this->redirectToRoute( 'home' );
	}

	/**
	 * @param string $errorCode On of the keys for self::ERROR_CODES
	 * @return JsonResponse
	 */
	private function getErrorResponse( string $errorCode ): JsonResponse {
		return new JsonResponse( [ 'error' => $errorCode ], self::ERROR_CODES[$errorCode] );
	}

	/**
	 * @param Client $client
	 * @param string $iThenticateUser
	 * @param string $iThenticatePassword
	 * @return string
	 */
	private function getSid( Client $client, string $iThenticateUser, string $iThenticatePassword ): string {
		$username = new Value( $iThenticateUser );
		$password = new Value( $iThenticatePassword );
		$response = $this->makeRpcRequest( $client, 'login', [
			'username' => $username,
			'password' => $password,
		] );
		return $response->scalarval()['sid']->scalarval();
	}

	/**
	 * @param Client $client
	 * @param string $method
	 * @param array $params
	 * @return Value
	 * @throws Exception
	 */
	private function makeRpcRequest( Client $client, string $method, array $params ): Value {
		$params = new Value( $params, 'struct' );
		$request = new \PhpXmlRpc\Request( $method, [ $params ] );
		$response = $client->send( $request );
		if ( $response->faultCode() ) {
			throw new Exception( $response->faultString() );
		}
		return $response->value();
	}
}
