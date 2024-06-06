<?php
/**
 * The Cache Pool Type.
 *
 * @package TheWebSolver\Codegarage\Library
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Cache\Data;

use Symfony\Component\Cache\Adapter\PdoAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

enum PoolType: string {
	case FileSystem = 'localFileSystem';
	case Database   = 'sqlDatabase';

	public function adapter(): string {
		return match ( $this ) {
			self::FileSystem => FilesystemAdapter::class,
			self::Database   => PdoAdapter::class,
		};
	}

	public function fqcn(): string {
		return self::class . "::{$this->name}";
	}
}
