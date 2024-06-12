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
 * @method static bool isDefault(PoolType $type)   Determines whether given pool type is set as default or not.
 * @method static bool isSupported(PoolType $type) Determines whether given pool type is supported or not.
 *
 * @method static bool setDefault(PoolType $type)                                         Sets default cache pool for the application request lifecycle.
 * @method static void setEncryptionKeys(string|string[] $keys)                           Sets rotating encryption key(s) either from env or db.
 * @method static bool setDriver(PoolType $type, object $config, bool $encrypted = false) Sets driver to be used with `Cache::driver()`.
 *
 * @method static Driver encrypted(?PoolType $type = null, bool $basic = false)                       Gets the driver that encrypts values in the Cache Pool.
 * @method static Driver driver(?PoolType $type = null, bool $basic = false, bool $encrypted = false) Gets the registered driver by Cache Pool type. If `$type`
 *                       is null, default Cache Pool will be used. If default Cache Pool is also not set using `Cache::setDefault()`, Filesystem
 *                       will be created and used. If basic is set to `true`, the returned driver will not support tagging feature
 *                       (_grouping cache items by tag and deleting using tag_).
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
 * @method static bool persist(string $key, mixed $value)  Returns `true` if item is cached indefinitely (until deleted manually),
 *                                                         `false` otherwise.
 * @method static bool delete(string|string[] $key)        Removes cached item(s) with given key(s).
 * @method static bool deleteExpired()                     Removes cached items that have expired.
 * @method static bool deleteTagged(string|string[] $tags) Removes cached items that are tagged with the given value.
 *                                                         Only works if `Cache::tagged()` is used.
 * @method static bool flush()                             Removes all items inside the current cache pool.
 * @method static bool isTaggable()                        Returns `true` if is a taggable cache pool.
 *
 * @method static string[] getDecryptionKeys() Gets encryption keys used for decrypting cache value.
 * @method static string[] decryptCryptoKeys() Gets the decoded version of encryption keys used for decrypting cache value.
 */
class Cache {
	/** @throws \BadMethodCallException When undefined method is invoked. */
	public static function __callStatic( string $method, $args ) {
		return Factory::start()->$method( ...$args );
	}

	/** Cannot instantiate facade only class. */
	private function __construct() {}
}
