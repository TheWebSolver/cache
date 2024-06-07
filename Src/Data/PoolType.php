<?php
/**
 * The Cache Pool Type.
 *
 * @package TheWebSolver\Codegarage\Library
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Cache\Data;

use Symfony\Component\Cache\Adapter\PdoAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Marshaller\TagAwareMarshaller;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;

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

	/** @return array{0:AdapterInterface,1:mixed[]} */
	public function tagAware( object $dto ): array {
		$tagAware = match ( $this ) {
			self::FileSystem => FilesystemTagAwareAdapter::class,
			default          => TagAwareAdapter::class,
		};

		$config  = array_values( (array) $dto );
		$args    = array( ...$config, new TagAwareMarshaller() );
		$default = $this->adapter();
		$adapter = TagAwareAdapter::class === $tagAware
			? new TagAwareAdapter( new $default( ...$args ) )
			: new $tagAware( ...$args );

		return array( $adapter, $config );
	}
}
