<?php
/**
 * Filesystem adapter data transfer object.
 *
 * @package TheWebSolver\Codegarage\Cache
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Cache\Data;

use TheWebSolver\Codegarage\Lib\Cache\Configurable;
use TheWebSolver\Codegarage\Lib\Cache\Configurator;

readonly class Directory implements Configurable {
	use Configurator;

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
