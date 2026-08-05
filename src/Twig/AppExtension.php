<?php

namespace App\Twig;

use DateTimeImmutable;
use Krinkle\Intuition\Intuition;
use NumberFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension {

	protected NumberFormatter $numFormatter;

	public function __construct(
		protected Intuition $i18n,
		protected string $appVersion
	) {
		$this->numFormatter = new NumberFormatter( $this->i18n->getLang(), NumberFormatter::DECIMAL );
	}

	/**
	 * @return TwigFilter[]
	 * @codeCoverageIgnore
	 */
	public function getFilters(): array {
		return [
			new TwigFilter( 'diff_format', $this->diffFormat(...), [ 'is_safe' => [ 'html' ] ] ),
		];
	}

	/**
	 * @return TwigFunction[]
	 * @codeCoverageIgnore
	 */
	public function getFunctions(): array {
		return [
			new TwigFunction( 'version', $this->version( ... ), [ 'is_safe' => [ 'html' ] ] ),
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

	/**
	 * Return the CalVer as it is set in .env
	 *
	 * @param bool $formatted Whether to return the formatted version or the actual version.
	 * @return string
	 */
	public function version( bool $formatted = true ): string {
		if ( $formatted ) {
			return DateTimeImmutable::createFromFormat( 'Y.m.d', $this->appVersion )
				->format( 'Y-m-d' );
		}
		return $this->appVersion;
	}
}
