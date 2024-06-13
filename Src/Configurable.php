<?php
/**
 * Interface for various Cache Pool Adapter Data Transfer Object
 *
 * @package TheWebSolver\Codegarage\Cache
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Cache;

interface Configurable {
	/**
	 * Returns an array of configuration data in order respective Adapter parameter is defined.
	 *
	 * @return mixed[]
	 */
	public function toArray(): array;
}
