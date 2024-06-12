<?php
/**
 * The Cache Pool Type.
 *
 * @package TheWebSolver\Codegarage\Library
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Cache\Data;

use InvalidArgumentException;
use TheWebSolver\Codegarage\Lib\Cache\Cache;
use Symfony\Component\Cache\Adapter\PdoAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Marshaller\SodiumMarshaller;
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
	public function tagAware( object $dto, bool $encrypted = false ): array {
		$tagAware = match ( $this ) {
			self::FileSystem => FilesystemTagAwareAdapter::class,
			default          => TagAwareAdapter::class,
		};

		$marshaller = new TagAwareMarshaller();
		$marshaller = ! $encrypted
			? $marshaller
			: new SodiumMarshaller( decryptionKeys: Cache::decryptCryptoKeys(), marshaller: $marshaller );

		$config  = array_values( $this->validateConfig( $dto ) );
		$args    = array( ...$config, $marshaller );
		$default = $this->adapter();
		$adapter = TagAwareAdapter::class === $tagAware
			? new TagAwareAdapter( new $default( ...$args ) )
			: new $tagAware( ...$args );

		return array( $adapter, $config );
	}

	/**
	 * @return array<string,mixed>
	 * @throws InvalidArgumentException When invalid configuration object given.
	 */
	private function validateConfig( object $config ): array {
		$class = match ( $this ) {
			self::FileSystem => Directory::class,
			self::Database   => PdoDsn::class,
		};

		if ( is_a( $config, $class ) ) {
			return (array) $config;
		}

		throw new InvalidArgumentException(
			$this->fqcn() . ' only accepts configuration object of class "' . $class . '".'
		);
	}
}
