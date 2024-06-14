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
	public static function fromConfiguration( Configurable $config ): self {
		$class = $config::class;

		return match ( $class ) {
			Directory::class => self::FileSystem,
			PdoDsn::class    => self::Database,
			default          => throw new InvalidArgumentException(
				"Unsupported or Invalid Configuration class: {$class}"
			),
		};
	}

	/** @return class-string<Configurable> */
	public function dto(): string {
		return match ( $this ) {
			self::FileSystem => Directory::class,
			self::Database   => PdoDsn::class,
		};
	}

	public function isValid( Configurable $config ): bool {
		return $config::class === $this->dto();
	}

	/** @throws InvalidArgumentException When invalid configuration object given. */
	public function validate( Configurable $config ): Configurable {
		return $this->isValid( $config ) ? $config : throw new InvalidArgumentException(
			$this->fqcn() . ' only accepts configuration object of class "' . $config::class . '".'
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
	 * @param mixed[]   $config
	 * @param ?string[] $encryptionKeys
	 */
	public function basic( array $config, ?array $encryptionKeys = null ): AdapterInterface {
		$adapter    = $this->adapter();
		$marshaller = self::resolveMarshaller( $encryptionKeys, isTagAware: false );

		return new $adapter( ...array( ...$config, $marshaller ) );
	}

	/**
	 * @param Configurable|mixed[] $config
	 * @param ?string[]            $encryptionKeys
	 * @return array{0:AdapterInterface,1:mixed[]}
	 */
	public function tagAware( Configurable|array $config, ?array $encryptionKeys = null ): array {
		$marshaller = self::resolveMarshaller( $encryptionKeys, isTagAware: true );
		$config     = $config instanceof Configurable ? $this->validate( $config )->toArray() : $config;

		return array( $this->createAdapter( args: array( ...$config, $marshaller ) ), $config );
	}

	/** @param ?string[] $encryptionKeys */
	public static function resolveMarshaller(
		?array $encryptionKeys = null,
		bool $isTagAware = true
	): MarshallerInterface {
		$marshaller = $isTagAware ? new TagAwareMarshaller() : new DefaultMarshaller();

		return empty( $encryptionKeys )
			? $marshaller
			: new SodiumMarshaller( decryptionKeys: $encryptionKeys, marshaller: $marshaller );
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
