<?php

declare( strict_types=1 );

namespace App\Tests\Model;

use App\Model\Record;
use App\Repository\CopyPatrolRepository;
use PHPUnit\Framework\TestCase;

/**
 * @covers Record
 */
class RecordTest extends TestCase {

	protected Record $record;

	public function setUp(): void {
		// Real sample data
		$data = [
			'diff_id' => 14168,
			'project' => 'wikipedia',
			'lang' => 'en',
			'page_namespace' => 118,
			'page_title' => 'STAR_Early_College_High_School_@_Erasmus_Hall',
			'rev_id' => 726095124,
			'rev_parent_id' => 0,
			'rev_timestamp' => '20160620003337',
			'rev_user_text' => 'Starec2016',
			'submission_id' => '23323186',
			'status' => 1,
			'status_timestamp' => '20230704164250',
			'status_user_text' => 'MusikAnimal',
			'sources' => [
				[
					'source_id' => 28671,
					'url' => 'http://www.brooklyn.cuny.edu/bc/pubs/bulletin/2010/ug_bulletin2010.pdf',
					'percent' => 53.0,
				],
				[
					'source_id' => 28672,
					'url' => 'http://www.readbag.com/brooklyn-cuny-bc-pubs-bulletin-2010-ug-bulletin2010',
					'percent' => 53.0,
				],
			]
		];

		$this->record = new Record(
			$data,
			50_000,
			false,
			true,
			true,
			[ 'New York City', null ],
			0.15
		);

		parent::setUp();
	}

	public function testGetters(): void {
		static::assertSame( 23323186, $this->record->getSubmissionId() );
		static::assertSame( [
			[
				'source_id' => 28671,
				'url' => 'http://www.brooklyn.cuny.edu/bc/pubs/bulletin/2010/ug_bulletin2010.pdf',
				'percent' => 53.0,
			],
			[
				'source_id' => 28672,
				'url' => 'http://www.readbag.com/brooklyn-cuny-bc-pubs-bulletin-2010-ug-bulletin2010',
				'percent' => 53.0,
			],
		], $this->record->getSources() );
		static::assertSame(
			'Draft:STAR Early College High School @ Erasmus Hall',
			$this->record->getPageTitle()
		);
		static::assertSame(
			'Draft:STAR_Early_College_High_School_@_Erasmus_Hall',
			$this->record->getPageTitle( true )
		);
		static::assertSame(
			'https://en.wikipedia.org/wiki/Draft:STAR_Early_College_High_School_@_Erasmus_Hall',
			$this->record->getPageUrl()
		);
		static::assertTrue( $this->record->isPageDead() );
		static::assertSame(
			'https://en.wikipedia.org/wiki/Special:PageHistory/Draft:STAR_Early_College_High_School_@_Erasmus_Hall',
			$this->record->getPageHistoryUrl()
		);
		static::assertSame(
			'https://en.wikipedia.org/wiki/Special:Diff/726095124',
			$this->record->getDiffUrl()
		);
		static::assertSame( '2016-06-20 00:33', $this->record->getDiffTimestamp() );
		static::assertSame( [ 'New York City' ], $this->record->getWikiProjects() );
		static::assertNull( $this->record->getOresScore() );
		static::assertSame( 726095124, $this->record->getRevId() );
		static::assertSame( 0, $this->record->getRevParentId() );
		static::assertSame( 'Starec2016', $this->record->getEditor() );
		static::assertSame(
			'https://en.wikipedia.org/wiki/User:Starec2016',
			$this->record->getUserPageUrl()
		);
		static::assertFalse( $this->record->isUserPageDead() );
		static::assertSame( 50_000, $this->record->getEditCount() );
		static::assertSame(
			'https://en.wikipedia.org/wiki/User_talk:Starec2016',
			$this->record->getUserTalkPageUrl()
		);
		static::assertFalse( $this->record->isUserTalkPageDead() );
		static::assertSame(
			'https://en.wikipedia.org/wiki/Special:Contribs/Starec2016',
			$this->record->getUserContribsUrl()
		);
		static::assertSame( CopyPatrolRepository::STATUS_FIXED, $this->record->getStatus() );
		static::assertSame( 'MusikAnimal', $this->record->getStatusUser() );
		static::assertSame( '2023-07-04 16:42', $this->record->getStatusTimestamp() );
		static::assertSame(
			'https://en.wikipedia.org/wiki/User:MusikAnimal',
			$this->record->getReviewedByUrl()
		);
	}

	public function testFormatTimestamp(): void {
		static::assertNull( $this->record->formatTimestamp( null ) );
		static::assertSame( '2020-01-23 12:59', $this->record->formatTimestamp( '20200123125959' ) );
	}

	public function testStatusJson(): void {
		static::assertSame( [
			'user' => 'MusikAnimal',
			'userpage' => 'https://en.wikipedia.org/wiki/User:MusikAnimal',
			'timestamp' => '2023-07-04 16:42',
			'status' => CopyPatrolRepository::STATUS_FIXED,
		], $this->record->getStatusJson() );
	}
}
