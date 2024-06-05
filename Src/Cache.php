<?php
/**
 * The cache drivers.
 *
 * @package TheWebSolver\Codegarage\Drivers
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Cache;

use BadMethodCallException;
use Psr\Cache\CacheItemInterface;

/**
 * @method static Driver driver(?string $store = null, ?object $config = null) The {@param `$store`} value must be one of `Factory::*` constant.
 * @method static Driver tagged(string|string[] $tags) Registers tags to be added to the new cache item.
 *
 * @method static ?CacheItemInterface item(string $key) Gets the item if it exists in the cache pool, `null` otherwise.
 *
 * @method static bool add(string $key, mixed $value, \DateTimeInterface|\DateInterval|int|null $time = null) Returns `true` if added successfully, `false` otherwise.
 * @method static bool addComputed(string $key, \Closure $value, \DateTimeInterface|\DateInterval|int|null $time = null) Returns `true` if added successfully, `false` otherwise.
 * @method static bool persist(string $key, mixed $value) Returns `true` if persisted, `false` otherwise.
 * @method static bool until(\DateTimeInterface $time, string $key, mixed $value) Adds an item which expires at the given time.
 * @method static bool for(int|\DateInterval $time, string $key, mixed $value) Adds an item which expires after given seconds or interval.
 * @method static bool remove(string|string[] $key) Removes cached item(s) with given key(s).
 * @method static bool removeExpired() Removes cached items that have expired.
 * @method static bool removeTagged(string|array $tags) Removes cached items that are tagged with the given value.
 * @method static bool flush() Removes all items inside the current cache pool.
 */
class Cache {
	/** Cannot instantiate facade only class. */
	private function __construct() {}

	public static function __callStatic( string $method, $args ) {
		$factory = Factory::start();
		$method  = strtolower( $method );

		if ( 'driver' === $method ) {
			return $factory->driver( ...$args );
		}

		return method_exists( $driver = $factory->driver(), $method )
			? $driver->$method( ...$args )
			: throw new BadMethodCallException( "Driver does not support method: {$method}." );
	}
}
