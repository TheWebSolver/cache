<?php
/**
 * The Cache Pool Type.
 *
 * @package TheWebSolver\Codegarage\Library
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Cache\Data;

use InvalidArgumentException;
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
		$args    = array( ...$this->toArray( $dto ), new TagAwareMarshaller() );
		$default = $this->adapter();
		$adapter = TagAwareAdapter::class === $tagAware
			? new TagAwareAdapter( new $default( ...$args ) )
			: new $tagAware( ...$args );

		return array( $adapter, $config );
	}

	public function toArray( object $config ): array {
		$this->validateConfig( $config );

		return match ( $this ) {
			self::FileSystem => array( $config->namespace, $config->life, $config->location ),
			self::Database   => array( $config->dsn, $config->namespace, $config->life, $config->options )
		};
	}

	private function validateConfig( object $config ): void {
		$class = match ( $this ) {
			self::FileSystem => Directory::class,
			self::Database   => PdoDsn::class,
		};

		if ( is_a( $config, $class ) ) {
			return;
		}

		throw new InvalidArgumentException(
			$this->fqcn() . ' only accepts configuration object of class "' . $class . '".'
		);
	}
}
