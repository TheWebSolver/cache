<?php
/**
 * The Cache Pool Type.
 *
 * @package TheWebSolver\Codegarage\Cache
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Cache\Data;

use InvalidArgumentException;
use TheWebSolver\Codegarage\Lib\Cache\Cache;
use Symfony\Component\Cache\Adapter\PdoAdapter;
use TheWebSolver\Codegarage\Lib\Cache\Configurable;
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

	/** @throws InvalidArgumentException When unsupported configuration given. */
	public static function fromConfiguration( Configurable $dto ): self {
		$class = $dto::class;

		return match ( $class ) {
			default          => throw new InvalidArgumentException( "Invalid configuration: {$class}" ),
			PdoDsn::class    => self::Database,
			Directory::class => self::FileSystem
		};
	}

	/** @return class-string<Configurable> */
	public function dto(): string {
		return match ( $this ) {
			self::FileSystem => Directory::class,
			self::Database   => PdoDsn::class,
		};
	}

	public function isValid( Configurable $dto ): bool {
		return $dto::class === $this->dto();
	}

	/** @throws InvalidArgumentException When invalid configuration object given. */
	public function validate( Configurable $dto ): Configurable {
		return $this->isValid( $dto ) ? $dto : throw new InvalidArgumentException(
			$this->fqcn() . ' only accepts configuration object of class "' . $dto::class . '".'
		);
	}

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

	/**
	 * @param mixed[]       $config
	 * @param bool|string[] $encrypted
	 */
	public function basic(
		array $config,
		bool|array $encrypted = false
	): AdapterInterface {
		$adapter    = $this->adapter();
		$marshaller = self::resolveMarshaller( isEncrypted: $encrypted, isTagAware: false );

		return new $adapter( ...array( ...$config, $marshaller ) );
	}

	/**
	 * @param Configurable|mixed[] $dto
	 * @param bool|string[]        $encrypted
	 * @return array{0:AdapterInterface,1:mixed[]}
	 */
	public function tagAware(
		Configurable|array $dto,
		bool|array $encrypted = false
	): array {
		$marshaller = self::resolveMarshaller( isEncrypted: $encrypted, isTagAware: true );
		$config     = $dto instanceof Configurable ? $this->validate( $dto )->toArray() : $dto;

		return array( $this->createAdapter( args: array( ...$config, $marshaller ) ), $config );
	}

	/**  @param bool|string[] $isEncrypted  */
	public static function resolveMarshaller(
		bool|array $isEncrypted = false,
		bool $isTagAware = true
	): MarshallerInterface {
		$marshaller = $isTagAware ? new TagAwareMarshaller() : new DefaultMarshaller();
		$keys       = ( true || false ) !== $isEncrypted ? $isEncrypted : Cache::decryptCryptoKeys();

		return ! self::needsEncryption( $isEncrypted )
			? $marshaller
			: new SodiumMarshaller( decryptionKeys: $keys, marshaller: $marshaller );
	}

	/** @param bool|string[] $keys */
	public static function needsEncryption( bool|array $keys ): bool {
		return is_bool( $keys ) ? $keys : ! empty( self::checkCrypto( $keys ) );
	}

	public static function checkCrypto( string|array $keys ): array {
		return array_unique( array_filter( (array) $keys ) );
	}

	/** @param mixed[] $args */
	private function createAdapter( array $args ): AdapterInterface {
		$default  = $this->adapter();
		$tagAware = $this->tagAwareAdapter();

		return TagAwareAdapter::class === $tagAware
			? new TagAwareAdapter( new $default( ...$args ) )
			: new $tagAware( ...$args );
	}
}
