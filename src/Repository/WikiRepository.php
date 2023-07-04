<?php

declare( strict_types=1 );

namespace App\Repository;

use DateInterval;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;
use Wikimedia\ToolforgeBundle\Service\ReplicasClient;

/**
 * A WikiRepository is responsible for all database queries and API requests to the wikis.
 * It should only do minimal post-processing of that data.
 * @codeCoverageIgnore This only runs queries, hence shouldn't need to be tested directly.
 */
class WikiRepository {

	/** The ID of the Drafts namespace. */
	public const NS_ID_DRAFTS = 118;

	protected CacheItemPoolInterface $cache;
	protected HttpClientInterface $httpClient;
	protected ReplicasClient $replicasClient;
	protected string $lang;

	/**
	 * @param ReplicasClient $replicasClient
	 * @param HttpClientInterface $httpClient
	 * @param CacheItemPoolInterface $cache
	 */
	public function __construct(
		ReplicasClient $replicasClient,
		HttpClientInterface $httpClient,
		CacheItemPoolInterface $cache
	) {
		$this->replicasClient = $replicasClient;
		$this->httpClient = $httpClient;
		$this->cache = $cache;
	}

	/**
	 * Set the language of the Wikipedia to which to connect to.
	 *
	 * @param string $lang The language code.
	 */
	public function setLang( string $lang ): void {
		$this->lang = $lang;
	}

	/**
	 * Get the language code of this Wikipedia.
	 *
	 * @return string The language code.
	 */
	public function getLang(): string {
		return $this->lang;
	}

	/**
	 * Get edit counts of given users
	 *
	 * @param string[] $usernames The users to fetch edit counts for
	 * @return array Edit counts keyed by username.
	 */
	public function getEditCounts( array $usernames ): array {
		if ( !$usernames ) {
			return [];
		}
		$sql = "SELECT user_name, user_editcount
				FROM {$this->getDb()}.user
				WHERE user_name IN (:usernames)";
		return $this->executeQuery( $sql, [ 'usernames' => $usernames ], [ 'usernames' => ArrayParameterType::STRING ] )
			->fetchAllKeyValue();
	}

	/**
	 * Check if the user is currently blocked. This is used to determine whether
	 * their access to CopyPatrol should be denied.
	 *
	 * @param string $username
	 * @return bool
	 */
	public function isUserBlocked( string $username ): bool {
		$sql = "SELECT 1
			FROM {$this->getDb()}.ipblocks
			WHERE ipb_address = :username
			LIMIT 1";

		return (bool)$this->executeQuery( $sql, [ 'username' => $username ] )
			->fetchOne();
	}

	/**
	 * Determine which of the given pages exists (should not be a red link). For mainspace pages on supported
	 * Wikipedias, a JOIN is done on page_assessments so we can get the WikiProjects back all in one query.
	 *
	 * @param string[][] $titlesByNs Page titles, keyed by namespace.
	 * @return string[] Pages that are live, keyed by namespace, then page title with WikiProjects as values.
	 */
	public function getLivePagesWithWikiProjects( array $titlesByNs ): array {
		if ( !$titlesByNs ) {
			return [];
		}
		$qb = $this->getConnection()->createQueryBuilder()
			->select( [ 'p.page_namespace', 'p.page_title' ] )
			->from( "{$this->getDb()}.page", 'p' );

		if ( $this->hasWikiProjects() ) {
			// Also query for page assessments.
			$qb->addSelect( 'pap.pap_project_title' );
			$qb->leftJoin(
				'p',
				"{$this->getDb()}.page_assessments",
				'pa',
				'page_id = pa_page_id'
			)->leftJoin(
				'pa',
				"{$this->getDb()}.page_assessments_projects",
				'pap',
				'pa_project_id = pap_project_id AND pap_parent_id IS NULL'
			);
		}

		foreach ( $titlesByNs as $nsId => $titles ) {
			$titles = array_unique( $titles );
			$qb->orWhere( "(
				page_namespace = :ns_$nsId
				AND page_title IN (:titles_$nsId)
			)" );
			$qb->setParameter( "ns_$nsId", $nsId );
			$qb->setParameter( "titles_$nsId", $titles, ArrayParameterType::STRING );
		}

		$ret = [];
		$result = $qb->executeQuery();
		while ( $row = $result->fetchAssociative() ) {
			$ret[$row['page_namespace']][$row['page_title']][] = $row['pap_project_title'] ?? null;
		}
		return $ret;
	}

	/**
	 * Get the ORES damage scores for the given revision IDs.
	 *
	 * @param array $revIds
	 * @return array
	 */
	public function getOresScores( array $revIds ): array {
		if ( !$revIds ) {
			return [];
		}
		$cacheKey = sha1( $this->getLang() . serialize( $revIds ) );
		if ( $this->cache->hasItem( $cacheKey ) ) {
			return $this->cache->getItem( $cacheKey )->get();
		}

		$dbName = "{$this->getLang()}wiki";
		$scores = $this->httpClient->request(
			'GET',
			"https://ores.wikimedia.org/v2/scores/$dbName/damaging/",
			[ 'query' => [ 'revids' => implode( '|', $revIds ) ] ]
		)->toArray( false );

		if ( !$scores ) {
			return [];
		}

		$ret = [];
		foreach ( $scores['scores'][$dbName]['damaging']['scores'] as $revId => $data ) {
			$ret[$revId] = $data['probability']['true'] ?? null;
		}

		// Cache for 10 minutes.
		$cacheItem = $this->cache
			->getItem( $cacheKey )
			->set( $ret )
			->expiresAfter( new DateInterval( 'PT10M' ) );
		$this->cache->save( $cacheItem );
		return $ret;
	}

	/**
	 * Return whether the language associated with this WikiRepository instance support PageAssessments.
	 *
	 * @see https://www.mediawiki.org/wiki/Extension:PageAssessments
	 * @return bool
	 */
	public function hasWikiProjects(): bool {
		return in_array( $this->getLang(), $this->fetchProjectsWithPageAssessments() );
	}

	/**
	 * @return array
	 */
	private function fetchProjectsWithPageAssessments(): array {
		$cacheKey = 'projects_with_assessments';
		if ( $this->cache->hasItem( $cacheKey ) ) {
			return $this->cache->getItem( $cacheKey )->get();
		}

		try {
			$ret = $this->httpClient->request( 'GET', 'https://xtools.wmcloud.org/api/project/assessments' );
		} catch ( Throwable $e ) {
			// Silently ignore; will be re-attempted on next request.
			return [];
		}

		$projectLangs = array_map( static function ( $domain ) {
			return explode( '.', $domain )[0];
		}, $ret->toArray()['projects'] );

		// Cache for a month.
		$cacheItem = $this->cache
			->getItem( $cacheKey )
			->set( $projectLangs )
			->expiresAfter( new DateInterval( 'P1M' ) );
		$this->cache->save( $cacheItem );
		return $projectLangs;
	}

	/**
	 * @param string $sql
	 * @param array $params
	 * @param array $types
	 * @return Result
	 */
	private function executeQuery( string $sql, array $params = [], array $types = [] ): Result {
		// Cache all queries for 10 minutes.
		$cacheKey = sha1( $this->getLang() . serialize( func_get_args() ) );
		$cacheProfile = new QueryCacheProfile( 60 * 10, $cacheKey, $this->cache );
		return $this->getConnection()->executeQuery( $sql, $params, $types, $cacheProfile );
	}

	/**
	 * @return Connection
	 */
	private function getConnection(): Connection {
		return $this->replicasClient->getConnection( "{$this->getLang()}wiki", false );
	}

	/**
	 * @return string
	 */
	private function getDb(): string {
		return "{$this->getLang()}wiki_p";
	}
}
