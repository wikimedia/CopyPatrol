<?php

declare( strict_types=1 );

namespace App\Repository;

use DateInterval;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * A CopyPatrolRepository is responsible for all database interaction with the CopyPatrol database.
 * It should do only minimal post-processing of that data.
 * @codeCoverageIgnore This only runs queries, hence shouldn't need to be tested directly.
 */
class CopyPatrolRepository {

	// Status constants
	public const STATUS_READY = 0;
	public const STATUS_FIXED = 1;
	public const STATUS_NO_ACTION = 2;

	// Filter constants
	public const FILTER_ALL = 'all';
	public const FILTER_OPEN = 'open';
	public const FILTER_REVIEWED = 'reviewed';
	public const FILTER_TYPES = [
		self::FILTER_ALL,
		self::FILTER_OPEN,
		self::FILTER_REVIEWED
	];

	protected CacheItemPoolInterface $cache;
	protected Connection $client;

	/**
	 * @param CacheItemPoolInterface $cache
	 * @param ManagerRegistry $managerRegistry
	 */
	public function __construct( CacheItemPoolInterface $cache, ManagerRegistry $managerRegistry ) {
		$this->cache = $cache;
		$this->client = $managerRegistry->getConnection( 'toolforge_trove' );
	}

	/**
	 * Fetch $limit records from the CopyPatrol database.
	 *
	 * @param array $options filter and filter user options, should look like:
	 *   string 'filter' Filter to show a certain status: 'all', 'open', or 'reviewed'
	 *   string 'filter_user' Filter SQL to only return records reviewed by given user
	 *   string 'filter_page' Search string (page title)
	 *   boolean 'drafts' returns only records that are in the Draft namespace
	 *   integer 'last_id' offset of where to start fetching records, going by 'diff_id'
	 *   integer 'id' exact submission_id of a record. This will override all other filter options
	 *   string 'lang' The language code of the Wikipedia to query for
	 * @param int $limit Number of records asked for
	 * @return array CopyPatrol db records.
	 */
	public function getPlagiarismRecords( array $options = [], int $limit = 50 ): array {
		$outerQuery = $this->client->createQueryBuilder();
		$diffsQb = $this->client->createQueryBuilder()
			->select(
				// There's two submission_id columns so we need to specify all the columns that we want.
				'diff_id', 'project', 'lang', 'page_namespace', 'page_title', 'rev_id', 'rev_parent_id',
				'rev_timestamp', 'rev_user_text', 'd.submission_id', 'status', 'status_timestamp',
				'status_user_text',
			)
			->from( 'diffs', 'd' )
			->orderBy( 'diff_id', 'DESC' )
			->setMaxResults( $limit );

		if ( $options['id'] ) {
			// if given an exact submission ID, don't allow any other filter options
			$diffsQb->where( 'd.submission_id = :id' );
			$outerQuery->setParameter( 'id', $options['id'] );
		} elseif ( $options['revision'] ) {
			// Same situation for diff IDs, except we still want the language.
			$diffsQb->where( 'rev_id = :rev_id' );
			$outerQuery->setParameter( 'rev_id', $options['revision'], ParameterType::INTEGER );
		} else {
			// Ensures only valid filters are used
			switch ( $options['filter'] ) {
				case self::FILTER_ALL:
					$diffsQb->andWhere( "status >= " . self::STATUS_READY );
					break;
				case self::FILTER_REVIEWED:
					$diffsQb->andWhere( "status > " . self::STATUS_READY );
					break;
				case self::FILTER_OPEN:
					$diffsQb->andWhere( "status = " . self::STATUS_READY );
					break;
			}
			// search filters
			if ( $options['filter_page'] ) {
				$diffsQb->andWhere( "page_title LIKE CONCAT('%', :filterPage, '%')" );
				$outerQuery->setParameter( 'filterPage', str_replace( ' ', '_', $options['filter_page'] ) );
			}
			// allow filtering by user and status
			if ( $options['filter_user'] ) {
				$diffsQb->andWhere( "status_user_text = :filterUser" );
				$outerQuery->setParameter( 'filterUser', $options['filter_user'] );
			}
			// see if this is a load more click
			if ( $options['last_id'] ) {
				$diffsQb->andWhere( "diff_id < :lastId" );
				$outerQuery->setParameter( 'lastId', $options['last_id'], ParameterType::INTEGER );
			}
			// filtering to draft namespace
			if ( $options['drafts'] ) {
				$diffsQb->andWhere( "page_namespace = " . WikiRepository::NS_ID_DRAFTS );
			}

			// Only fetch entries from the required language Wikipedia.
			$diffsQb->andWhere( "lang = :lang" );
			$outerQuery->setParameter( 'lang', $options['lang'] );

			// show only records after June 20, 2016; See phab:T138317
			$diffsQb->andWhere( "rev_timestamp > 20160620000000" );
		}

		return $outerQuery->select( '*', 'source_id', 'url', 'percent', 'description' )
			->from( '(' . $diffsQb->getSQL() . ')', 'a' )
			->join( 'a', 'report_sources', 's', 'a.submission_id = s.submission_id' )
			->executeQuery()
			->fetchAllAssociative();
	}

	/**
	 * Fetch the count of records from the CopyPatrol database, up to $limit.
	 *
	 * @param array $options filter and filter user options, should look like:
	 *   string 'filter' Filter to show a certain status: 'all', 'open', or 'reviewed'
	 *   string 'filter_user' Filter SQL to only return records reviewed by given user
	 *   string 'filter_page' Search string (page title)
	 *   boolean 'drafts' returns only records that are in the Draft namespace
	 *   integer 'last_id' offset of where to start fetching records, going by 'diff_id'
	 *   integer 'id' exact submission_id of a record. This will override all other filter options
	 *   string 'lang' The language code of the Wikipedia to query for
	 * @param int $limit Number of records asked for
	 * @param string $cacheExpiry Cache expiry time (default 15 minutes)
	 * @return int Count of CopyPatrol db records.
	 */
	public function getPlagiarismRecordsCount(
		array $options = [],
		int $limit = 200,
		string $cacheExpiry = 'PT15M'
	): int {
		// If the count is already cached, return it
		if ( $this->cache->hasItem( md5( serialize( $options ) . $limit ) ) ) {
			return $this->cache->getItem( md5( serialize( $options ) . $limit ) )->get();
		}

		// Else, calculate the count and cache it
		$count = count( $this->getPlagiarismRecords( $options, $limit ) );
		$cacheItem = $this->cache
			->getItem( md5( serialize( $options ) . $limit ) )
			->set( $count )
			// cache for 15 minutes
			->expiresAfter( new DateInterval( $cacheExpiry ) );
		$this->cache->save( $cacheItem );
		return $this->cache->getItem( md5( serialize( $options ) . $limit ) )->get();
	}

	/**
	 * Get the top reviewers over the past last 7 days, 30 days, and all-time.
	 *
	 * @param string $lang The language code of the Wikipedia in use.
	 * @return array Associative array of leaderboard data
	 */
	public function getLeaderboardData( string $lang ): array {
		$results = $this->client->executeCacheQuery(
			'(' . $this->getLeaderboardSql( 7, 'last-week' )
				. ') UNION (' .
				$this->getLeaderboardSql( 30, 'last-month' )
				. ') UNION (' .
				$this->getLeaderboardSql( null, 'all-time' ) . ')',
			[ 'lang' => $lang ],
			[],
			// Cache for 20 minutes
			new QueryCacheProfile( 60 * 20, "$lang-leaderboard", $this->cache )
		);
		$ret = [
			'last-week' => [],
			'last-month' => [],
			'all-time' => [],
		];
		foreach ( $results->fetchAllAssociative() as $result ) {
			$ret[$result['key']][] = $result;
		}
		return $ret;
	}

	/**
	 * Get SQL for the leaderboard.
	 *
	 * @param int|null $offset Number of days from present to query for. Leave null for all-time
	 * @param string $key
	 * @return string the SQL
	 */
	private function getLeaderboardSql( ?int $offset, string $key ): string {
		return "SELECT status_user_text AS `user`, COUNT(*) as `count`, '$key' AS `key`
			FROM diffs
			WHERE status_user_text IS NOT NULL
			AND status_user_text != 'Community Tech bot' " .
			( $offset ? 'AND status_timestamp > ADDDATE(CURRENT_DATE, -' . $offset . ')' : '' ) .
			" AND lang = :lang
			GROUP BY status_user_text
			ORDER BY COUNT(*) DESC
			LIMIT 10";
	}

	/**
	 * Update a record in the CopyPatrol database.
	 *
	 * @param string $submissionId Submission ID of the report
	 * @param string|null $value Value of the state saved by user
	 * @param string|null $user The reviewer's username
	 * @param string|null $timestamp Timestamp of when the review took place
	 * @return int|string Number of effected rows.
	 */
	public function updateCopyvioAssessment(
		string $submissionId,
		?string $value,
		?string $user,
		?string $timestamp
	) {
		return $this->client->update( 'diffs', [
			'status' => $value,
			'status_user_text' => $user,
			'status_timestamp' => $timestamp,
		], [ 'submission_id' => $submissionId ] );
	}

	/**
	 * Get a particular record by submission ID.
	 *
	 * @param string $submissionId ID of record.
	 * @return array Query result.
	 * @todo Return a Record object instead of an array.
	 */
	public function getRecordBySubmissionId( string $submissionId ) {
		$sql = "SELECT * FROM diffs WHERE submission_id = :id";
		return $this->client->fetchAssociative( $sql, [
			'id' => $submissionId
		] );
	}

	/**
	 * Find out whether a particular language has any drafts.
	 *
	 * @param string $lang The Wikipedia language code
	 * @return bool
	 */
	public function draftsExist( string $lang = 'en' ) {
		return $this->cache->get( "$lang-drafts", function ( ItemInterface $item ) use ( $lang ) {
			$item->expiresAfter( new \DateInterval( 'P7D' ) );
			$sql = 'SELECT 1 FROM diffs WHERE page_namespace = :ns AND lang = :lang';
			return (bool)$this->client->fetchOne( $sql, [ 'ns' => WikiRepository::NS_ID_DRAFTS, 'lang' => $lang ] );
		} );
	}

	/**
	 * Check if a copyvio has occurred in the past $offset hours.
	 * This is used to monitor if the bot has gone down.
	 *
	 * @see https://phabricator.wikimedia.org/T262767
	 * @param string $lang Language code
	 * @param int $offset Number of hours
	 * @return bool
	 */
	public function hasActivity( string $lang, int $offset ) {
		$sql = 'SELECT 1 FROM diffs WHERE lang = :lang ' .
			'AND rev_timestamp > DATE_SUB(NOW(), INTERVAL :offset HOUR)';
		return (bool)$this->client->fetchOne( $sql, [ 'lang' => $lang, 'offset' => $offset ] );
	}
}
