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

final class Factory {
	private static Factory $instance;
	private PoolType $default;

	/** @var array<string,Driver> */
	private array $drivers = array();

	/** @var array<string,mixed[]> */
	private array $config;

	public function __call( string $method, array $args ): mixed {
		return method_exists( $driver = $this->driver(), $method )
			? $driver->$method( ...$args )
			: throw new BadMethodCallException( "Cache driver does not support method: {$method}." );
	}

	public static function start( ?ContainerInterface $app = null ): self {
		return $app?->get( id: self::class ) ?? ( self::$instance ??= new self() );
	}

	public function isDefault( PoolType $type ): bool {
		return $this->getDefaultType() === $type;
	}

	public function isSupported( PoolType $type ): bool {
		return isset( $this->drivers[ $type->value ] )
			|| isset( $this->drivers[ $this->awareKey( $type ) ] );
	}

	public function setDefaultPool( PoolType $type, object $config ): bool {
		if ( $this->default ?? false ) {
			return false;
		}

		$this->default = $type;

		$this->setDriver( $type, $config );

		return true;
	}

	public function setDriver( PoolType $type, object $config ): Driver {
		return $this->drivers[ $this->awareKey( $type ) ] ??= new Driver(
			adapter: $this->get( $type, dto: $config ),
			taggable: true
		);
	}

	/** @throws LogicException When unregistered Cache Pool Type is being retrieved. */
	public function driver( ?PoolType $type = null, bool $basic = false ): Driver {
		if ( $type && ! $this->isDefault( $type ) && ! $this->isSupported( $type ) ) {
			throw new LogicException(
				'Cannot retrieve Driver for Cache Pool Type that is not registered. Use method '
				. Cache::class . '::setDriver() to register Driver for: ' . $type->fqcn() . '.'
			);
		}

		$type ??= $this->getDefaultType() ?? $this->registerFileSystemDriverAsDefault();

		return $basic ? $this->basic( $type ) : $this->drivers[ $this->awareKey( $type ) ];
	}

	private function getDefaultType(): ?PoolType {
		return $this->default ?? null;
	}

	private function registerFileSystemDriverAsDefault(): PoolType {
		$this->setDriver(
			type: $default = PoolType::FileSystem,
			config: new Directory( namespace: 'kyasa', location: dirname( __DIR__ ) . '/tmp/' )
		);

		return $default;
	}

	private function basic( PoolType $type ): Driver {
		$cachePool = $type->adapter();

		return $this->drivers[ $type->value ] ??= new Driver(
			adapter: new $cachePool( ...$this->config[ $type->value ] )
		);
	}

	private function awareKey( PoolType $type ): string {
		return 'tagAware:' . $type->value;
	}

	private function get( PoolType $type, object $dto ): AdapterInterface {
		[ $adapter, $this->config[ $type->value ] ] = $type->tagAware( $dto );

		return $adapter;
	}
}
