<?php
/**
 * This file is part of CopyPatrol application
 * Copyright (C) 2016  Niharika Kohli and contributors
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option)
 * any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Niharika Kohli <nkohli@wikimedia.org>
 * @copyright © 2016 Niharika Kohli and contributors.
 */
namespace Plagiabot\Web\Dao;

use Wikimedia\Slimapp\Dao\AbstractDao;
use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\FluentRequest;
use GuzzleHttp;
use GuzzleHttp\Promise\Promise;

class WikiDao extends AbstractDao {

	/**
	 * The ID of the Drafts namespace.
	 */
	public const NS_ID_DRAFTS = 118;

	/**
	 * @var string $lang The language code of the Wikipedia in use.
	 */
	protected $lang;

	/**
	 * Create a new Wikipedia Data Access Object with a variable database name based on
	 * the provided language code.
	 * @param string $lang
	 * @param string $host
	 * @param string $port
	 * @param string $user
	 * @param string $password
	 * @param \Psr\Log\LoggerInterface|null $log
	 * @return WikiDao
	 */
	public static function newFromLangCode( $lang, $host, $port, $user, $password, $log = null ) {
		$dbName = $lang . 'wiki';
		// Replace wildcard in hostname with dbname for compatbility with new replicas. See T270407
		$host = str_replace( '*', $dbName, $host );
		$dsn = "mysql:host=$host;port=$port;dbname={$dbName}_p";
		$dao = new self( $dsn, $user, $password, $log );
		$dao->setLang( $lang );
		return $dao;
	}

	/**
	 * Set the language of the Wikipedia to which to connect to.
	 * @param string $lang The language code.
	 */
	public function setLang( $lang ) {
		$this->lang = $lang;
	}

	/**
	 * Get the language code of this Wikipedia.
	 * @return string The language code.
	 */
	public function getLang() {
		return $this->lang;
	}

	/**
	 * Get the base URL for the Wikipedia currently being used.
	 * @return string The HTTPS URL, with no trailing slash.
	 */
	public function getWikipediaUrl() {
		return "https://" . $this->getLang() . ".wikipedia.org";
	}

	/**
	 * @return MediawikiApi
	 */
	public function getMediawikiApi() {
		return new MediawikiApi( $this->getWikipediaUrl() . '/w/api.php' );
	}

	/**
	 * Get the editors of the given revisions
	 *
	 * @param array $diffs Revision IDs
	 * @return array Associative array by revids and editor as the value
	 */
	public function getRevisionsEditors( $diffs ) {
		// get the revisions synchronously
		$result = $this->apiQuery( [
			'revids' => implode( '|', $diffs ),
			'prop' => 'revisions',
			'rvprop' => 'user|timestamp|ids'
		] )['query'];

		$data = [];

		// Fill in nulls for deleted revisions.
		if ( isset( $result['badrevids'] ) ) {
			foreach ( $result['badrevids'] as $revision ) {
				$data[$revision['revid']] = null;
			}
		}

		// Get editors for non-deleted revisions.
		if ( isset( $result['pages'] ) ) {
			foreach ( $result['pages'] as $page ) {
				$revisions = $page['revisions'];

				if ( isset( $revisions ) ) {
					foreach ( $revisions as $revision ) {
						if ( isset( $revision['revid'] ) && isset( $revision['user'] ) ) {
							$data[$revision['revid']] = $revision['user'];
						}
					}
				}
			}
		}

		return $data;
	}

	/**
	 * Get edit counts of given users
	 * @param array $usernames The users to fetch edit counts for
	 * @return promise Resolves with associative array of usernames/edit counts
	 */
	public function getEditCounts( $usernames ) {
		// create empty promise so we can make multiple async calls in CopyPatrol controller
		$promise = new Promise();

		// make array of usernames unique and remove nulls
		$usernames = array_filter( array_unique( $usernames ) );

		$result = $this->apiQuery( [
			'list' => 'users',
			'ususers' => implode( '|', $usernames ),
			'usprop' => 'editcount'
		] )['query'];

		$editors = [];
		foreach ( $result['users'] as $index => $user ) {
			$editors[$user['name']] = isset( $user['editcount'] ) ? $user['editcount'] : 0;
		}

		$promise->resolve( $editors );
		return $promise;
	}

	/**
	 * Get editor details
	 *
	 * @param int $diff Diff revision ID
	 * @return array|false If editor exists, return array with params
	 *   'editor', 'editcount'. Else, false.
	 */
	public function getUserDetails( $diff ) {
		$query = self::concat(
			'SELECT u.user_editcount, u.user_name',
			'FROM revision r',
			'LEFT JOIN actor a ON r.rev_actor = a.actor_id',
			'LEFT JOIN user u ON a.actor_user = u.user_id',
			'WHERE r.rev_id = ?'
		);
		$data = [
			'editor' => false,
			'editcount' => false
		];
		$result = $this->fetch( $query, [ (int)$diff ] );
		if ( $result == false ) {
			return $data;
		} else {
			$data['editor'] = $result['user_name'];
			$data['editcount'] = $result['user_editcount'];
		}
		return $data;
	}

	/**
	 * Get block information about the user. This is used to determine whether
	 * their access to CopyPatrol should be denied.
	 * @param string $username
	 * @return array Query result.
	 */
	public function getBlockInfo( $username ) {
		$sql = "SELECT ipb_expiry
			FROM ipblocks
			WHERE ipb_user = (
				SELECT user_id
				FROM user
				WHERE user_name = :username
			)
			LIMIT 1";

		return $this->fetch(
			$sql, [ 'username' => $username ]
		);
	}

	/**
	 * Determine which of the given pages are dead
	 *
	 * @param array $titles Page titles
	 * @return Promise Resolves with an array of the pages that are dead
	 */
	public function getDeadPages( $titles ) {
		// create empty promise so we can make multiple async calls in CopyPatrol controller
		$promise = new Promise();

		if ( !$titles ) {
			return $promise->resolve( [] );
		}

		// first break out array into chunks of 50,
		// the max number of titles per request without bot authentication
		$chunks = array_chunk( $titles, 50 );

		// build array of promises so we can initiate API calls all at once
		$promises = array_map( function ( $chunk ) {
			return $this->apiQuery( [
				'titles' => implode( '|', $chunk )
			], true );
		}, $chunks );

		// wait for all promises to complete
		$results = GuzzleHttp\Promise\unwrap( $promises );
		$deadPages = [];

		foreach ( $results as $result ) {
			// Get list of normalized pages. This is to account for when querying for
			// User/User talk pages on non-English projects, since the namespaces titles are different
			$normalizedPages = [];
			if ( isset( $result['query']['normalized'] ) ) {
				foreach ( $result['query']['normalized'] as $mapping ) {
					$normalizedPages[$mapping['to']] = $mapping['from'];
				}
			}

			foreach ( $result['query']['pages'] as $page ) {
				if ( isset( $page['missing'] ) ) {
					// Please note that this returns a false positive when the
					// user account has a global User page and not a local one
					if ( isset( $normalizedPages[$page['title']] ) ) {
						$deadPages[] = $normalizedPages[$page['title']];
					} else {
						$deadPages[] = $page['title'];
					}
				}
			}
		}

		$promise->resolve( $deadPages );

		return $promise;
	}

	/**
	 * Get user whitelist
	 * @return array List of usernames
	 */
	public function getUserWhitelist() {
		$links = $this->apiQuery( [
			'prop' => 'links',
			'titles' => 'User:EranBot/Copyright/User_whitelist'
		] );
		if ( !isset( $links['query']['pages'][0]['links'] ) ) {
			return [];
		}
		// return array with just usernames as strings, without the 'User:' prefix
		return array_map( function ( $link ) {
			// Split on : and pick the second element to get the username
			// This is because for other wikis 'User:' may be different, but there was always be a colon
			return explode( ':', $link['title'] )[1];
		}, $links['query']['pages'][0]['links'] );
	}

	/**
	 * Wrapper to make simple API query for JSON and in formatversion 2
	 * @param string[] $params Params to add to the request
	 * @param bool $async Pass 'true' to make asynchronous
	 * @return GuzzleHttp\Promise\PromiseInterface|array Promise if $async is true,
	 *   otherwise the API result in the form of an array
	 */
	private function apiQuery( $params, $async = false ) {
		$factory = FluentRequest::factory()->setAction( 'query' )
			->setParam( 'formatversion', 2 )
			->setParam( 'format', 'json' );

		foreach ( $params as $param => $value ) {
			$factory->setParam( $param, $value );
		}

		if ( $async ) {
			return $this->getMediawikiApi()->getRequestAsync( $factory );
		} else {
			return $this->getMediawikiApi()->getRequest( $factory );
		}
	}
}
