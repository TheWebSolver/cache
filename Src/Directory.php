<?php
/**
 * Filesystem adapter data transfer object.
 *
 * @package TheWebSolver\Codegarage\Library
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Cache;

readonly class Directory {
	public function __construct(
		public string $namespace = 'route',
		public int $life = 0,
		public string $location = __DIR__ . '/Cache/temp'
	) {}

	/** Sets the default expiry time (in seconds) for all cache items. */
	public static function for( int $seconds ): self {
		return new self( life: $seconds );
	}
}
