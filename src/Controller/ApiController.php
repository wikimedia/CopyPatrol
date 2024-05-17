<?php

namespace App\Controller;

use App\Model\Record;
use App\Repository\CopyPatrolRepository;
use App\Repository\WikiRepository;
//phpcs:ignore MediaWiki.Classes.UnusedUseStatement.UnusedUse
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
//phpcs:ignore MediaWiki.Classes.UnusedUseStatement.UnusedUse
use Symfony\Component\Routing\Annotation\Route;

//phpcs:disable MediaWiki.Commenting.DocComment.SpacingDocTag
class ApiController extends AppController {

	/**
	 * Get a list of plagiarism cases.
	 * @Route("/api/feed/{lang}", methods={"GET"}, name="api_feed", requirements={"lang"="simple|\w{2}"})
	 * @OA\Tag(name="API")
	 * @OA\Parameter(ref="#/components/parameters/lang")
	 * @OA\Parameter(
	 *     name="filter",
	 *     in="query",
	 *     description="Filter the results by status",
	 *     required=false,
	 *     schema=@OA\Schema(type="string", enum={"all", "open", "reviewed"})
	 * )
	 * @OA\Parameter(
	 *     name="filterUser",
	 *     in="query",
	 *     description="Filter the results by reviewer",
	 *     required=false,
	 *     schema=@OA\Schema(type="string")
	 * )
	 * @OA\Parameter(
	 *     name="filterPage",
	 *     in="query",
	 *     description="Filter by page title. This is a **case-sensitive** substring match.",
	 *     required=false,
	 *     schema=@OA\Schema(type="string")
	 * )
	 * @OA\Parameter(
	 *     name="drafts",
	 *     in="query",
	 *     description="Include only drafts in the results",
	 *     required=false,
	 *     schema=@OA\Schema(type="boolean")
	 * )
	 * @OA\Parameter(
	 *     name="last_id",
	 *     in="query",
	 *     description="Get only cases with a diff_id greater than this value",
	 *     required=false,
	 *     schema=@OA\Schema(type="integer")
	 * )
	 * @OA\Response(
	 *     response=200,
	 *     description="Returns a list of plagiarism cases",
	 *     @OA\JsonContent(
	 *         type="array",
	 *         @OA\Items(ref="#/components/schemas/Case")
	 *     )
	 * )
	 * @OA\Response(response=404, description="The requested language is unsupported")
	 * @OA\Response(response=500, ref="#/components/responses/500")
	 * @param Request $request
	 * @param CopyPatrolRepository $copyPatrolRepo
	 * @param WikiRepository $wikiRepo
	 * @param string $lang
	 * @return JsonResponse
	 */
	public function feedApiAction(
		Request $request,
		CopyPatrolRepository $copyPatrolRepo,
		WikiRepository $wikiRepo,
		string $lang
	): JsonResponse {
		$wikiRepo->setLang( $lang );
		$options = $this->getFeedActionOptions( $request, $lang );
		$rows = $copyPatrolRepo->getPlagiarismRecords( $options );
		$records = array_values( array_map( static function ( Record $record ) {
			return $record->toArray();
		}, $this->decorateRecords( $rows, $wikiRepo ) ) );
		return new JsonResponse( $records );
	}

	/**
	 * Get information about a specific plagiarism case.
	 * @Route(
	 *     "/api/case/{submissionId}",
	 *     methods={"GET"},
	 *     name="api_case",
	 *     requirements={"lang"="simple|\w{2}", "submissionId"="\d+|[a-z\d+\-]+"}
	 * )
	 * @OA\Tag(name="API")
	 * @OA\Parameter(
	 *     name="submissionId",
	 *     in="path",
	 *     description="The submission UUID of the case. For very old cases, this may an integer ID.",
	 *     required=true,
	 *     schema=@OA\Schema(type="uuid")
	 * )
	 * @OA\Response(
	 *     response=200,
	 *     description="Returns information about a specific plagiarism case",
	 *     @OA\JsonContent(ref="#/components/schemas/Case")
	 * )
	 * @OA\Response(response=404, description="The requested case does not exist")
	 * @OA\Response(response=500, ref="#/components/responses/500")
	 * @param CopyPatrolRepository $copyPatrolRepo
	 * @param WikiRepository $wikiRepo
	 * @param string $submissionId
	 * @return JsonResponse
	 */
	public function caseApiAction(
		CopyPatrolRepository $copyPatrolRepo,
		WikiRepository $wikiRepo,
		string $submissionId
	): JsonResponse {
		// FIXME: make this work with the older integer submission IDs
		$row = $copyPatrolRepo->getPlagiarismRecords( [
			'id' => $submissionId,
		] );
		if ( !$row ) {
			return new JsonResponse(
				[ 'error' => 'The requested case does not exist' ],
				Response::HTTP_NOT_FOUND
			);
		}
		$wikiRepo->setLang( $row[0]['lang'] );
		$record = array_values( $this->decorateRecords( [ $row[0] ], $wikiRepo ) )[ 0 ];
		return new JsonResponse( $record->toArray() );
	}
}
