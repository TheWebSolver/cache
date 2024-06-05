<?php
/**
 * Cache repository.
 *
 * @package TheWebSolver\Codegarage\Cache
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib;

use Closure;
use DateInterval;
use DateTimeInterface;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\AbstractTagAwareAdapter;

class CacheDriver {
	public function __construct(
		private readonly AbstractAdapter|TagAwareAdapter|AbstractTagAwareAdapter $adapter
	) {}

	public function item( string $key ): ?CacheItemInterface {
		return ( $item = $this->adapter->getItem( $key ) )->isHit() ? $item : null;
	}

	public function add(
		string $key,
		mixed $value,
		DateTimeInterface|DateInterval|int|null $time = null
	): bool {
		$cached = false;
		$value  = $this->adapter->get(
			key: $key,
			callback: function ( CacheItemInterface $item, bool &$save ) use ( $time, $value, &$cached ) {
				$this->addExpiry( $item, $time );

				$value  = $value instanceof Closure ? $value( $item ) : $value;
				$cached = $save = true;

				return $value;
			}
		);

		return $cached;
	}

	public function addComputed(
		string $key,
		Closure $value,
		DateTimeInterface|DateInterval|int|null $time = null
	): bool {
		return $this->add( $key, $value, $time );
	}

	public function persist( string $key, mixed $value ): mixed {
		return $this->add( $key, $value, time: null );
	}

	public function until( DateTimeInterface $time, string $key, mixed $value ): bool {
		return $this->add( $key, $value, $time );
	}

	public function for( int|DateInterval $time, string $key, mixed $value ): bool {
		return $this->add( $key, $value, $time );
	}

	/** @var string|string[] $key */
	public function remove( string|array $key ): bool {
		return $this->adapter->deleteItems( (array) $key );
	}

	public function clean(): bool {
		return $this->adapter instanceof PruneableInterface ? $this->adapter->prune() : false;
	}

	private static function addExpiry( CacheItemInterface $item, mixed $time ): CacheItemInterface {
		return match ( true ) {
			is_int( $time ) || $time instanceof DateInterval => $item->expiresAfter( $time ),
			$time instanceof DateTimeInterface               => $item->expiresAt( $time ),
			default                                          => $item
		};
	}
}
