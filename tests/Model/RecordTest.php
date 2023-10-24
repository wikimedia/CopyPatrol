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
			'length_change' => 500,
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
			],
			'tags' => [
				'mw-reverted',
			],
			'tags_labels' => [
				'Reverted',
			]
		];

		$this->record = new Record(
			$data,
			50_000,
			false,
			true,
			true,
			[ 'New York City', null ]
		);

		parent::setUp();
	}

	public function testGetters(): void {
		static::assertSame( '23323186', $this->record->getSubmissionId() );
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
		static::assertTrue( $this->record->isNewPage() );
		static::assertSame( 500, $this->record->getDiffSize() );
		static::assertSame( [ 'mw-reverted' ], $this->record->getTags() );
		static::assertSame( [ 'Reverted' ], $this->record->getTagLabels() );
		static::assertSame(
			'https://en.wikipedia.org/wiki/Draft:STAR_Early_College_High_School_@_Erasmus_Hall' .
				'?action=edit&undoafter=0&undo=726095124',
			$this->record->getUndoUrl()
		);
		static::assertSame(
			'https://en.wikipedia.org/wiki/Special:RevisionDelete?type=revision' .
				'&ids=726095124&wpHidePrimary=1&wpReason=http%3A%2F%2Fwww.brooklyn.cuny.edu%2Fbc%2Fpubs%2Fbulletin' .
				'%2F2010%2Fug_bulletin2010.pdf&wpRevDeleteReasonList=%5B%5BWP%3ARD1%7CRD1%5D%5D%3A+Violations+of+' .
				'%5B%5BWikipedia%3ACopyright+violations%7Ccopyright+policy%5D%5D',
			$this->record->getRevdelUrl()
		);
		static::assertSame(
			'https://en.wikipedia.org/wiki/Draft:STAR_Early_College_High_School_@_Erasmus_Hall?action=delete' .
				'&wpDeleteReasonList=%5B%5BWP%3ACSD%23G12%7CG12%5D%5D%3A+Unambiguous+%5B%5BWP%3ACV%7Ccopyright+' .
				'infringement%5D%5D&wpReason=http%3A%2F%2Fwww.brooklyn.cuny.edu%2Fbc%2Fpubs%2Fbulletin' .
				'%2F2010%2Fug_bulletin2010.pdf&wpDeleteTalk=1',
			$this->record->getDeleteUrl()
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

	public function testParseWikitext(): void {
		static::assertEquals(
			"&lt;script&gt;alert(\"XSS baby\")&lt;/script&gt; " .
			"<a target='_blank' href='https://en.wikipedia.org/wiki/Test_page'>test page</a>",
			$this->record->parseWikitext( '<script>alert("XSS baby")</script> [[test page]]' )
		);

		static::assertEquals(
			'<a target="_blank" href="https://example.org">https://example.org</a>',
			$this->record->parseWikitext( 'https://example.org' )
		);
	}
}
