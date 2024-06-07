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

	public function add( int $extra = 1, ?Time $time = null ): int {
		return $this->format( $this->value + ( $extra * ( $time ?? $this )->value ) );
	}

	public function reduce( int $by = 1, ?Time $time = null ): int {
		return self::Second !== $this
			? $this->format( $this->value - ( $by * ( $time ?? $this )->value ) )
			: throw new class( 'Cannot reduce cache time to less than a second.' )
				extends \InvalidArgumentException implements InvalidArgumentException {};
	}

	/** Divides time by given value. */
	public function split( int $by = 2 ): int {
		return intval( floor( $this->value / $by ) );
	}

	/** Multiplies time by given value. */
	public function expand( int $by = 2 ): int {
		return intval( ceil( $this->value * $by ) );
	}

	private function format( int|float $value ): int {
		return intval( round( $value ) );
	}
}
