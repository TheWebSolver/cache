<?php
/**
 * Database Adapter data transfer object.
 *
 * @package TheWebSolver\Codegarage\Cache
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Cache\Data;

use TheWebSolver\Codegarage\Lib\Cache\Configurable;
use TheWebSolver\Codegarage\Lib\Cache\Configurator;

readonly class PdoDsn implements Configurable {
	use Configurator;

	public function __construct(
		public \PDO|string $dsn,
		public string $namespace = '',
		public int $life = 0,
		public array $options = array()
	) {}
}
