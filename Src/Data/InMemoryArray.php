<?php
/**
 * In-memory Array Adapter data transfer object.
 *
 * @package TheWebSolver\Codegarage\Cache
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Cache\Data;

use TheWebSolver\Codegarage\Lib\Cache\Configurable;
use TheWebSolver\Codegarage\Lib\Cache\Configurator;

readonly class InMemoryArray implements Configurable {
	use Configurator;

	public function __construct(
		public int $life = 0,
		public bool $serializeValue = false,
		public int $emptyArrayAfter = 0,
		public int $maxItemsInArray = 0
	) {}
}
