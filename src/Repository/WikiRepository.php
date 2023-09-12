<?php

declare( strict_types=1 );

namespace App\Repository;

use DateInterval;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Cache\ItemInterface;
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
		$ret = $this->executeQuery( $sql,
			[ 'usernames' => $usernames ],
			[ 'usernames' => ArrayParameterType::STRING ]
		);
		if ( $ret->rowCount() > 0 ) {
			return $ret->fetchAllKeyValue();
		}
		return [];
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
			FROM {$this->getDb()}.ipblocks_ipindex
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
	 * Get edit summaries, tags, and edit sizes for the given revisions.
	 *
	 * @param int[] $revIds
	 * @return array Each with keys 'rev_id', 'rev_deleted', 'length_change', 'comment' and 'tags'
	 */
	public function getRevisionMetadata( array $revIds ): array {
		if ( !$revIds ) {
			return [];
		}
		$qb = $this->getConnection()->createQueryBuilder()
			->select( [
				'revs.rev_id',
				'revs.rev_deleted',
				'(CAST(revs.rev_len AS SIGNED) - IFNULL(parentrevs.rev_len, 0)) AS `length_change`',
				'comment_text AS `comment`',
				"(
					SELECT GROUP_CONCAT(ctd_name, ',')
					FROM {$this->getDb()}.change_tag
					JOIN {$this->getDb()}.change_tag_def ON ct_tag_id = ctd_id
					WHERE ct_rev_id = revs.rev_id
				 ) AS `tags`",
			] )
			->from( "{$this->getDb()}.revision", 'revs' )
			->leftJoin( 'revs', "{$this->getDb()}.revision", 'parentrevs', 'revs.rev_parent_id = parentrevs.rev_id' )
			->leftJoin( 'revs', "{$this->getDb()}.comment", 'comment', 'revs.rev_comment_id = comment.comment_id' )
			->where( 'revs.rev_id IN (:revIds)' )
			->setParameter( 'revIds', $revIds, ArrayParameterType::INTEGER );

		$results = $qb->executeQuery()->fetchAllAssociative();

		// Fetch localized tag labels.
		$tagNames = [];
		foreach ( $results as $result ) {
			if ( isset( $result['tags'] ) ) {
				$tagNames = array_merge( $tagNames, array_filter( explode( ',', $result['tags'] ) ) );
			}
		}
		$tagLabels = $this->getTagsLabels( array_unique( $tagNames ) );

		// Loop through again, adding in the tag labels.
		foreach ( $results as $index => $result ) {
			if ( isset( $result['tags'] ) ) {
				$tagNames = array_filter( explode( ',', $result['tags'] ) );
				$results[$index]['tags'] = $tagNames;
				$results[$index]['tags_labels'] = array_filter(
					array_map( static function ( $tagName ) use ( $tagLabels ) {
						return $tagLabels[$tagName];
					}, $tagNames )
				);
			}
		}

		return $results;
	}

	/**
	 * Get the labels for the given tags, as defined by their on-wiki interface messages.
	 *
	 * @param array $tagNames
	 * @return array
	 */
	private function getTagsLabels( array $tagNames ): array {
		$cacheKey = $this->getLang() . '-tags';

		// Loop through to determine which messages we don't already have in cache.
		$messages = [];
		if ( $this->cache->hasItem( $cacheKey ) ) {
			$messages = $this->cache->getItem( $cacheKey )->get();
			foreach ( $tagNames as $index => $tagName ) {
				if ( isset( $messages[$tagName] ) ) {
					unset( $tagNames[$index] );
				}
			}
		}

		if ( !$tagNames ) {
			return $messages;
		}

		$lang = $this->getLang();
		$tagsToQuery = array_map( static function ( $tagName ) {
			return "Tag-$tagName";
		}, array_splice( $tagNames, 0, 50 ) );
		$response = $this->httpClient->request(
			'GET',
			"https://$lang.wikipedia.org/w/api.php",
			[
				'query' => [
					'action' => 'query',
					'meta' => 'allmessages',
					'ammessages' => implode( '|', $tagsToQuery ),
					'amenableparser' => 1,
					'amincludelocal' => 1,
					'formatversion' => 2,
					'format' => 'json',
				]
			]
		)->toArray( false );

		if ( $response ) {
			foreach ( $response['query']['allmessages'] as $message ) {
				$tagName = preg_replace( '/^Tag\-/', '', $message['name'] );
				$messages[$tagName] = $message['content'] ?? $tagName;
				// Fill in blank values for hidden tags.
				$messages[$tagName] = $messages[$tagName] === '-' ? '' : $messages[$tagName];
			}
			// Cache for a week.
			$cacheItem = $this->cache
				->getItem( $cacheKey )
				->set( $messages )
				->expiresAfter( new DateInterval( 'P7D' ) );
			$this->cache->save( $cacheItem );
		}

		if ( $tagNames ) {
			return $this->getTagsLabels( $tagNames );
		}

		return $messages;
	}

	/**
	 * Get the damage scores for the given revision IDs.
	 *
	 * @param int[] $revIds
	 * @return array
	 */
	public function getDamageScores( array $revIds ): array {
		if ( !$revIds ) {
			return [];
		}

		$dbName = "{$this->getLang()}wiki";

		$ret = [];
		$responses = [];
		foreach ( $revIds as $revId ) {
			if ( $this->cache->hasItem( "$dbName-damage-score-$revId" ) ) {
				$ret[$revId] = $this->cache->getItem( "$dbName-damage-score-$revId" )->get();
			} else {
				$responses[] = $this->httpClient->request(
					'POST',
					"https://api.wikimedia.org/service/lw/inference/v1/models/$dbName-damaging:predict",
					[ 'json' => [ 'rev_id' => $revId ] ]
				);
			}
		}

		foreach ( $responses as $response ) {
			$data = $response->toArray( false );
			$revId = array_keys( $data[$dbName]['scores'] ?? [] )[0] ?? null;
			if ( $revId ) {
				$ret[$revId] = $data[$dbName]['scores'][$revId]['damaging']['score']['probability']['true'] ?? null;
				$cacheItem = $this->cache->getItem( "$dbName-damage-score-$revId" )
					->set( $ret[$revId] )
					->expiresAfter( new DateInterval( 'PT10M' ) );
				$this->cache->saveDeferred( $cacheItem );
			}
		}
		$this->cache->commit();

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
	 * Get the rights (aka permissions, NOT user groups) for the given user.
	 *
	 * @param string $username
	 * @return array
	 */
	public function getUserRights( string $username ): array {
		$cacheKey = "user-rights-" . sha1( $username );
		return $this->cache->get( $cacheKey, function ( ItemInterface $item ) use ( $username ) {
			$item->expiresAfter( new DateInterval( 'P1D' ) );
			$lang = $this->getLang();
			$response = $this->httpClient->request(
				'GET',
				"https://$lang.wikipedia.org/w/api.php",
				[
					'query' => [
						'action' => 'query',
						'list' => 'users',
						'usprop' => 'rights',
						'ususers' => $username,
						'formatversion' => 2,
						'format' => 'json',
					]
				]
			)->toArray( false );
			return $response['query']['users'][0]['rights'] ?? [];
		} );
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
