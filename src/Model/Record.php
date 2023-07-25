<?php

namespace App\Model;

use App\Repository\WikiRepository;
use DateTime;

class Record {

	/** @var float Only surface ORES scores above this threshold. */
	public const ORES_THRESHOLD = 0.427;

	protected array $data;
	protected string $username;
	protected ?int $editCount;
	protected bool $pageExists;
	protected bool $userPageExists;
	protected bool $userTalkExists;
	protected array $wikiProjects;
	protected ?float $oresScore;

	/**
	 * @param array $row From the CopyPatrol database.
	 * @param int|null $editCount
	 * @param bool $pageExists
	 * @param bool $userPageExists
	 * @param bool $userTalkExists
	 * @param array $wikiProjects
	 * @param float|null $oresScore
	 */
	public function __construct(
		array $row,
		int $editCount = null,
		bool $pageExists = false,
		bool $userPageExists = false,
		bool $userTalkExists = false,
		array $wikiProjects = [],
		float $oresScore = null
	) {
		$this->data = $row;
		$this->editCount = $editCount;
		$this->pageExists = $pageExists;
		$this->userPageExists = $userPageExists;
		$this->userTalkExists = $userTalkExists;
		// Remove any null values.
		$this->wikiProjects = array_filter( $wikiProjects );
		$this->oresScore = $oresScore;
	}

	/** REPORT ATTRIBUTES */

	/**
	 * Get the submission ID.
	 *
	 * @return int
	 */
	public function getSubmissionId(): int {
		return $this->data['submission_id'];
	}

	/**
	 * Get the copyvio sources associated with the edit.
	 *
	 * @return array
	 */
	public function getSources(): array {
		return $this->data['sources'];
	}

	/** PAGE / REVISION */

	/**
	 * Get the page title.
	 *
	 * @param bool $underscored Whether to include underscores (we do for links, but not for display).
	 * @return string
	 */
	public function getPageTitle( bool $underscored = false ): string {
		$nsName = $this->data['page_namespace'] === WikiRepository::NS_ID_DRAFTS ? 'Draft:' : '';
		$pageTitle = $nsName . $this->data['page_title'];
		if ( !$underscored ) {
			// Remove underscores for display purposes.
			$pageTitle = str_replace( '_', ' ', $pageTitle );
		}
		return $pageTitle;
	}

	/**
	 * Get the URL to the page.
	 *
	 * @return string
	 */
	public function getPageUrl(): string {
		return $this->getUrl( $this->getPageTitle( true ) );
	}

	/**
	 * Is the page dead (i.e. nonexistent or deleted)?
	 *
	 * @return bool
	 */
	public function isPageDead(): bool {
		return !$this->pageExists;
	}

	/**
	 * Get the URL to the revision history of the page.
	 *
	 * @return string
	 */
	public function getPageHistoryUrl(): string {
		return $this->getUrl( 'Special:PageHistory/' . $this->getPageTitle( true ) );
	}

	/**
	 * Get the URL to the diff page for the revision.
	 *
	 * @return string
	 */
	public function getDiffUrl(): string {
		return $this->getUrl( 'Special:Diff/' . $this->data['rev_id'] );
	}

	/**
	 * Get the timestamp of the diff
	 *
	 * @return string
	 */
	public function getDiffTimestamp(): string {
		return $this->formatTimestamp( $this->data['rev_timestamp'] );
	}

	/**
	 * Get the WikiProjects associated with the page.
	 *
	 * @return array
	 */
	public function getWikiProjects(): array {
		return $this->wikiProjects;
	}

	/**
	 * Get the ORES score associated with the edit, if applicable.
	 *
	 * @return float|null
	 */
	public function getOresScore(): ?float {
		if ( $this->oresScore < self::ORES_THRESHOLD ) {
			return null;
		}
		return round( $this->oresScore * 100, 2 );
	}

	/**
	 * Get the ID of the revision.
	 *
	 * @return int
	 */
	public function getRevId(): int {
		return $this->data['rev_id'];
	}

	/**
	 * Get the parent ID of the revision.
	 *
	 * @return int
	 */
	public function getRevParentId(): int {
		return $this->data['rev_parent_id'];
	}

	/** EDITOR / USER */

	/**
	 * Get the username of the editor.
	 *
	 * @return string
	 */
	public function getEditor(): string {
		return $this->data['rev_user_text'];
	}

	/**
	 * Get the URL to the userpage of the editor.
	 *
	 * @return string
	 */
	public function getUserPageUrl(): string {
		return $this->getUrl( 'User:' . $this->data['rev_user_text'] );
	}

	/**
	 * Is the user page dead? (i.e. nonexistent or deleted)
	 *
	 * @return bool
	 */
	public function isUserPageDead(): bool {
		return !$this->userPageExists;
	}

	/**
	 * Get the edit count of the user.
	 *
	 * @return int|null
	 */
	public function getEditCount(): ?int {
		return $this->editCount;
	}

	/**
	 * Get the URL to the user's talk page.
	 *
	 * @return string
	 */
	public function getUserTalkPageUrl(): string {
		return $this->getUrl( 'User_talk:' . $this->data['rev_user_text'] );
	}

	/**
	 * Is the user talk page dead? (i.e. nonexistent or deleted)
	 *
	 * @return bool
	 */
	public function isUserTalkPageDead(): bool {
		return !$this->userTalkExists;
	}

	/**
	 * Get the URL to the user's contributions.
	 *
	 * @return string
	 */
	public function getUserContribsUrl(): string {
		return $this->getUrl( 'Special:Contribs/' . $this->data['rev_user_text'] );
	}

	/** REVIEW ATTRIBUTES */

	/**
	 * Get the status of the report.
	 *
	 * @return int On the CopyPatrolRepository::STATUS_ constants.
	 */
	public function getStatus(): int {
		return $this->data['status'];
	}

	/**
	 * Get the username of who made the last status change.
	 *
	 * @return string|null
	 */
	public function getStatusUser(): ?string {
		return $this->data['status_user_text'] ?? null;
	}

	/**
	 * Get the timestamp of the last status change.
	 *
	 * @return string|null
	 */
	public function getStatusTimestamp(): ?string {
		return $this->formatTimestamp( $this->data['status_timestamp'] ?? null );
	}

	/**
	 * Get the URL to the userpage of who did the review.
	 *
	 * @return string|null
	 */
	public function getReviewedByUrl(): ?string {
		return $this->getStatusUser()
			? $this->getUrl( 'User:' . $this->getStatusUser() )
			: null;
	}

	/** UTIL */

	/**
	 * @param string $target
	 * @return string
	 */
	private function getUrl( string $target ): string {
		return "https://{$this->data['lang']}.{$this->data['project']}.org/wiki/$target";
	}

	/**
	 * Format a timestamp in ISO 8601.
	 *
	 * @param string|null $timestamp
	 * @return string|null
	 */
	public function formatTimestamp( ?string $timestamp ): ?string {
		if ( !$timestamp ) {
			return $timestamp;
		}
		return ( new DateTime( $timestamp ) )->format( 'Y-m-d H:i' );
	}

	/**
	 * Get JSON representation of the Record that is needed by the frontend.
	 *
	 * @return array
	 */
	public function getStatusJson(): array {
		return [
			'user' => $this->getStatusUser(),
			'userpage' => $this->getUrl( 'User:' . $this->getStatusUser() ),
			'timestamp' => $this->getStatusTimestamp(),
			'status' => $this->getStatus(),
		];
	}
}
