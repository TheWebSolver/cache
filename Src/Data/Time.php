<?php
/**
 * Time calculation in seconds.
 *
 * @package TheWebSolver\Codegarage\Library
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Cache\Data;

use Psr\Cache\InvalidArgumentException;

enum Time: int {
	case Second = 1;
	case Minute = 60;
	case Hour   = 60 * self::Minute->value;
	case Day    = 24 * self::Hour->value;
	case Week   = 7 * self::Day->value;
	case Month  = 30 * self::Day->value;
	case Year   = 365 * self::Day->value;

	public function add( int|float $extra = 1, ?Time $time = null ): int {
		return ( $seconds = $this->format( $this->value + ( $extra * ( $time ?? $this )->value ) ) ) > 0
			? $seconds
			: $this->throwLesserThanOneSecond();
	}

	public function reduce( int|float $by = 1, ?Time $time = null ): int {
		return ( $seconds = $this->format( $this->value - ( $by * ( $time ?? $this )->value ) ) ) > 0
			? $seconds
			: $this->throwLesserThanOneSecond();
	}

	/** Divides time by given value. */
	public function split( int|float $by = 2 ): int {
		return ( $seconds = intval( floor( $this->value / $by ) ) ) > 0
			? $seconds
			: $this->throwLesserThanOneSecond();
	}

	/** Multiplies time by given value. */
	public function expand( int|float $by = 2 ): int {
		return ( $seconds = intval( ceil( $this->value * $by ) ) ) > 0
			? $seconds
			: $this->throwLesserThanOneSecond();
	}

	public function getInterval(): string {
		return match ( $this ) {
			self::Second => '1 second',
			self::Minute => '1 minute',
			self::Hour   => '1 hour',
			self::Day    => '1 day',
			self::Week   => '1 week',
			self::Month  => '1 month',
			self::Year   => '1 year',
		};
	}

	private function format( int|float $value ): int {
		return intval( round( $value ) );
	}

	private function throwLesserThanOneSecond(): never {
		throw new class( 'Cannot set cache time to less than a second.' )
			extends \InvalidArgumentException implements InvalidArgumentException {};
	}
}
