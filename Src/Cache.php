<?php
/**
 * The cache drivers.
 *
 * @package TheWebSolver\Codegarage\Library
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Cache;

use Symfony\Component\Cache\CacheItem;
use TheWebSolver\Codegarage\Lib\Cache\Data\PoolType;

/**
 * @method static bool   isDefault(PoolType $type)      Determines whether given pool type is set as default or not.
 * @method static bool   isSupported(PoolType $type)    Determines whether given pool type is supported or not.
 * @method static bool   setDefaultPool(PoolType $type) Sets default cache pool for the application request lifecycle.
 * @method static Driver driver(?PoolType $type = null, ?object $config = null, bool $basic = false)
 *                       Gets (creates if doesn't exist) the driver. `$config` is required if calling this method for
 *                       the first time (except for default pool). If basic is set to `true`, the returned driver
 *                       will not support tagging feature (grouping cache items by tag and deleting using tag).
 *
 * @method static Driver tagged(string|string[] $tags) Registers tags to be added to the new cache item.
 *
 * @method static ?CacheItem item(string $key) Gets the item if it's value is cached', `null` otherwise.
 * @method static ?CacheItem add(string $key, mixed $value, Time|\DateTimeInterface|\DateInterval|int|null $time = null) Flashes
 *                           cache item instance once if value is cached, `null` thereafter.
 * @method static ?CacheItem addComputed(string $key, \Closure $value, Time|\DateTimeInterface|\DateInterval|int|null $time = null)
 *                           Provides option to do computation task only if cache misses. Eg: heavy database call.
 * @method static ?CacheItem until(\DateTimeInterface $time, string $key, mixed $value)   Adds an item which expires at the given time.
 * @method static ?CacheItem for(Time|\DateInterval|int $time, string $key, mixed $value) Adds an item which expires after
 *                           given seconds or interval.
 *
 * @method static bool persist(string $key, mixed $value) Returns `true` if item is cached indefinitely (until deleted manually),
 *                                                        `false` otherwise.
 * @method static bool delete(string|string[] $key)       Removes cached item(s) with given key(s).
 * @method static bool deleteExpired()                    Removes cached items that have expired.
 * @method static bool deleteTagged(string|array $tags)   Removes cached items that are tagged with the given value.
 *                                                        Only works if `Cache::tagged()` is used.
 * @method static bool flush()                            Removes all items inside the current cache pool.
 * @method static bool isTaggable()                       Returns `true` if is a taggable cache pool.
 */
class Cache {
	/** @throws \BadMethodCallException When undefined method is invoked. */
	public static function __callStatic( string $method, $args ) {
		return Factory::start()->$method( ...$args );
	}

	/** Cannot instantiate facade only class. */
	private function __construct() {}
}
