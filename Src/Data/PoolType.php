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
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\Marshaller\TagAwareMarshaller;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;
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

	public function tagAwareAdapter(): string {
		return match ( $this ) {
			self::FileSystem => FilesystemTagAwareAdapter::class,
			default          => TagAwareAdapter::class,
		};
	}

	public function fqcn(): string {
		return self::class . "::{$this->name}";
	}

	/** @param mixed[] $config */
	public function basic( array $config, bool $encrypted = false ): AdapterInterface {
		$adapter    = $this->adapter();
		$marshaller = self::resolveMarshaller( isEncrypted: $encrypted, isTagAware: false );

		return new $adapter( ...array( ...$config, $marshaller ) );
	}

	/** @return array{0:AdapterInterface,1:mixed[]} */
	public function tagAware( object|array $dto, bool $encrypted = false ): array {
		$marshaller = self::resolveMarshaller( isEncrypted: $encrypted, isTagAware: true );
		$config     = is_array( $dto ) ? $dto : array_values( $this->validateConfig( value: $dto ) );

		return array( $this->createAdapter( args: array( ...$config, $marshaller ) ), $config );
	}

	public static function resolveMarshaller(
		bool $isEncrypted = false,
		bool $isTagAware = true
	): MarshallerInterface {
		$marshaller = $isTagAware ? new TagAwareMarshaller() : new DefaultMarshaller();

		return ! $isEncrypted
			? $marshaller
			: new SodiumMarshaller( decryptionKeys: Cache::decryptCryptoKeys(), marshaller: $marshaller );
	}

	/** @param mixed[] $args */
	private function createAdapter( array $args ): AdapterInterface {
		$default  = $this->adapter();
		$tagAware = $this->tagAwareAdapter();

		return TagAwareAdapter::class === $tagAware
			? new TagAwareAdapter( new $default( ...$args ) )
			: new $tagAware( ...$args );
	}

	/**
	 * @return array<string,mixed>
	 * @throws InvalidArgumentException When invalid configuration object given.
	 */
	private function validateConfig( object $value ): array {
		$class = match ( $this ) {
			self::FileSystem => Directory::class,
			self::Database   => PdoDsn::class,
		};

		if ( is_a( $value, $class ) ) {
			return (array) $value;
		}

		throw new InvalidArgumentException(
			$this->fqcn() . ' only accepts configuration object of class "' . $class . '".'
		);
	}
}
