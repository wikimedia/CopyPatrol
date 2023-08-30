<?php

namespace App\Twig;

use Krinkle\Intuition\Intuition;
use NumberFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension {

	protected Intuition $i18n;
	protected NumberFormatter $numFormatter;

	/**
	 * @param Intuition $intuition
	 */
	public function __construct( Intuition $intuition ) {
		$this->i18n = $intuition;
		$this->numFormatter = new NumberFormatter( $this->i18n->getLang(), NumberFormatter::DECIMAL );
	}

	/**
	 * @return TwigFilter[]
	 * @codeCoverageIgnore
	 */
	public function getFilters(): array {
		return [
			new TwigFilter( 'diff_format', [ $this, 'diffFormat' ], [ 'is_safe' => [ 'html' ] ] )
		];
	}

	/**
	 * Format a given number as a diff, colouring it green if it's positive, red if negative, gray if zero.
	 *
	 * @param int|null $size Diff size
	 * @return string Markup with formatted number
	 */
	public function diffFormat( ?int $size ): string {
		if ( $size === null ) {
			// Deleted/suppressed revisions.
			return '';
		}

		if ( $size < 0 ) {
			$class = 'diff-neg';
		} elseif ( $size > 0 ) {
			$class = 'diff-pos';
		} else {
			$class = 'diff-zero';
		}

		$size = $this->numFormatter->format( $size );

		return "<div class='$class'" . ( $this->i18n->isRTL() ? " dir='rtl'" : '' ) . ">$size</div>";
	}
}
