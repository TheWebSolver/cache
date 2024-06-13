<?php
/**
 * Configurator.
 *
 * @package TheWebSolver\Codegarage\Cache
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Cache;

trait Configurator {
	/** @return mixed[] */
	public function toArray(): array {
		return array_values( get_object_vars( $this ) );
	}
}
