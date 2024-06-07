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
	private static Directory $defaultConfig;
	private static Factory $instance;
	private static array $config;
	private PoolType $default;

	/** @var array<string,Driver> */
	private array $drivers = array();

	public function __construct() {
		self::$defaultConfig = new Directory(
			namespace: 'rewriteReloaded',
			location: dirname( __DIR__ ) . '/tmp/'
		);
	}

	public function __call( string $method, array $args ): mixed {
		return method_exists( $driver = $this->driver(), $method )
			? $driver->$method( ...$args )
			: throw new BadMethodCallException( "Cache driver does not support method: {$method}." );
	}

	public static function start( ?ContainerInterface $app = null ): self {
		return self::$instance ??= ( $app?->get( id: self::class ) ?? new self() );
	}

	public function isDefault( PoolType $type ): bool {
		return ( $this->default ?? PoolType::FileSystem ) === $type;
	}

	public function isSupported( PoolType $type ): bool {
		return isset( $this->drivers[ $type->value ] )
			|| isset( $this->drivers[ $this->awareKey( $type ) ] );
	}

	public function setDefaultPool( PoolType $type ): bool {
		if ( $this->default ?? false ) {
			return false;
		}

		$this->default ??= $type;

		return true;
	}

	/** @throws LogicException When config not passed for initializing cache pool for first time. */
	public function driver(
		?PoolType $type = null,
		?object $config = null,
		bool $basic = false
	): Driver {
		$type ??= $this->default ?? PoolType::FileSystem;

		if ( ! $this->isSupported( $type ) && ! $this->isDefault( $type ) && ! $config ) {
			throw new LogicException(
				'Factory needs configuration object to produce Cache Pool. Provide '
				. 'appropriate object to instantiate Cache Pool type for "'
				. $type->fqcn() . '".'
			);
		}

		$tagAwarePool = $this->awareKey( $type );

		// Register all Tag Aware Adapters first even if basic adapter is being queried
		// to populate the factory properties required for the application lifecycle.
		$this->drivers[ $tagAwarePool ] ??= new Driver(
			adapter: self::get( $type, dto: $config ?? self::$defaultConfig ),
			taggable: true
		);

		return $basic ? $this->basic( $type ) : $this->drivers[ $tagAwarePool ];
	}

	private function basic( PoolType $type ): Driver {
		$cachePool = $type->adapter();

		return $this->drivers[ $type->value ] ??= new Driver(
			adapter: new $cachePool( ...self::$config[ $type->value ] )
		);
	}

	private function awareKey( PoolType $type ): string {
		return 'tagAware:' . $type->value;
	}

	private static function get( PoolType $type, object $dto ): AdapterInterface {
		[ $adapter, self::$config[ $type->value ] ] = $type->tagAware( $dto );

		return $adapter;
	}
}
