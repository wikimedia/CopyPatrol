<?php

namespace App\Tests\Twig;

use App\Twig\AppExtension;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * @covers \App\Twig\AppExtension
 */
class AppExtensionTest extends WebTestCase {

	protected AppExtension $appExtension;

	/**
	 * Set class instance.
	 */
	public function setUp(): void {
		$this->appExtension = new AppExtension(
			static::getContainer()->get( 'Krinkle\Intuition\Intuition' )
		);
	}

	/**
	 * Format number as a diff size.
	 */
	public function testDiffFormat(): void {
		static::assertEquals(
			"<div class='diff-pos'>3,000</div>",
			$this->appExtension->diffFormat( 3000 )
		);
		static::assertEquals(
			"<div class='diff-neg'>-20,000</div>",
			$this->appExtension->diffFormat( -20000 )
		);
		static::assertEquals(
			"<div class='diff-zero'>0</div>",
			$this->appExtension->diffFormat( 0 )
		);
		static::assertSame( '', $this->appExtension->diffFormat( null ) );
	}
}
