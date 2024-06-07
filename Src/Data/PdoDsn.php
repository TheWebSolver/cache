<?php
/**
 * Database Adapter data transfer object.
 *
 * @package TheWebSolver\Codegarage\Library
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Cache\Data;

readonly class PdoDsn {
	public function __construct(
		public \PDO|string $dsn,
		public string $namespace = '',
		public int $life = 0,
		public array $options = array()
	) {}
}
