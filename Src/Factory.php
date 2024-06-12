<?php
/**
 * Factory to create cache driver.
 *
 * @package TheWebSolver\Codegarage\Library
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Cache;

use LogicException;
use BadMethodCallException;
use Psr\Container\ContainerInterface;
use TheWebSolver\Codegarage\Lib\Cache\Data\PoolType;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use TheWebSolver\Codegarage\Lib\Cache\Data\Directory;
use Symfony\Component\Cache\Marshaller\SodiumMarshaller;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;

final class Factory {
	private static Factory $instance;
	private PoolType $default;

	/** @var array<string,Driver> */
	private array $drivers = array();

	/** @var array<string,mixed[]> */
	private array $config;

	/** @var array<string> */
	private array $crypto = array();

	public readonly Directory $directory;

	public function __construct() {
		$this->directory = new Directory( namespace: 'kyasa', location: dirname( __DIR__ ) . '/tmp/' );
	}

	/**
	 * @throws BadMethodCallException When undefined driver method was invoked.
	 * @mixin \TheWebSolver\Codegarage\Lib\Cache\Driver
	 */
	public function __call( string $method, array $args ): mixed {
		return method_exists( $driver = $this->driver(), $method )
			? $driver->$method( ...$args )
			: throw new BadMethodCallException( "Cache driver does not support method: {$method}." );
	}

	public static function start( ?ContainerInterface $app = null ): self {
		return self::$instance ??= $app?->get( id: self::class ) ?? new self();
	}

	public function isDefault( PoolType $type ): bool {
		return $this->getDefaultType() === $type;
	}

	public function isSupported( PoolType $type ): bool {
		return isset( $this->drivers[ $type->value ] )
			|| isset( $this->drivers[ $this->awareKey( $type ) ] );
	}

	public function setDefaultPool( PoolType $type, object $config, bool $encrypted = false ): bool {
		if ( $this->default ?? false ) {
			return false;
		}

		$this->default = $type;

		// This may return false if driver was already set but is registered as default just now.
		$this->setDriver( $type, $config, $encrypted );

		return true;
	}

	public function setDriver( PoolType $type, object $config, bool $encrypted = false ): bool {
		if ( $this->drivers[ $key = $this->awareKey( $type, $encrypted ) ] ?? false ) {
			return false;
		}

		$this->drivers[ $key ] = new Driver(
			adapter: $this->get( $type, dto: $config, encrypted: $encrypted ),
			taggable: true
		);

		return true;
	}

	public function setEncryptionKeys( string|array $keys ): bool {
		if ( ! empty( $this->crypto ) ) {
			return false;
		}

		$this->crypto = $this->checkCrypto( $keys );

		return true;
	}

	/** @throws LogicException When unregistered Cache Pool Type is being retrieved. */
	public function driver( ?PoolType $type = null, bool $basic = false, bool $encrypted = false ): Driver {
		return $this->resolveDriver( $type, $basic, $encrypted );
	}

	public function encrypted( ?PoolType $type = null, bool $basic = false ): Driver {
		return $this->driver( $type, $basic, encrypted: true );
	}

	/** @return string[] */
	public function getDecryptionKeys(): array {
		return $this->crypto;
	}

	private function resolveDriver( ?PoolType $type, bool $basic, bool $encrypted ): Driver {
		if ( $type && ! $this->isDefault( $type ) && ! $this->isSupported( $type ) ) {
			throw new LogicException(
				'Cannot retrieve Driver for Cache Pool Type that is not registered. Use method '
				. Cache::class . '::setDriver() to register Driver for: ' . $type->fqcn() . '.'
			);
		}

		$type ??= $this->getDefaultType() ?? $this->registerFileSystemDriverAsDefault();

		return $basic
			? $this->basic( $type, $encrypted )
			: $this->drivers[ $this->awareKey( $type, $encrypted ) ];
	}

	private function getDefaultType(): ?PoolType {
		return $this->default ?? null;
	}

	private function registerFileSystemDriverAsDefault(): PoolType {
		$this->setDriver(
			type: $default = PoolType::FileSystem,
			config: $this->directory
		);

		return $default;
	}

	private function basic( PoolType $type, bool $encrypted ): Driver {
		$cachePool  = $type->adapter();
		$marshaller = $encrypted ? new SodiumMarshaller( $this->crypto ) : new DefaultMarshaller();

		return $this->drivers[ $type->value ] ??= new Driver(
			adapter: new $cachePool( ...array( ...$this->config[ $type->value ], $marshaller ) )
		);
	}

	private function awareKey( PoolType $type, bool $encrypted = false ): string {
		return ( $encrypted ? 'encrypted:' : '' ) . 'tagAware:' . $type->value;
	}

	private function get( PoolType $type, object $dto, bool $encrypted ): AdapterInterface {
		[ $adapter, $this->config[ $type->value ] ] = $type->tagAware( $dto, $encrypted );

		return $adapter;
	}

	/** @return string[] */
	private function checkCrypto( string|array $keys ): array {
		return array_unique( array_filter( (array) $keys ) );
	}
}
