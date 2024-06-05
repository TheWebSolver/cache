<?php
/**
 * Driver that provides cache pool on-demand.
 *
 * @package TheWebSolver\Codegarage\Library
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Cache;

use Closure;
use DateInterval;
use DateTimeInterface;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\AbstractTagAwareAdapter;

class Driver {
	private array $tags = array();

	public function __construct(
		private readonly AbstractAdapter|TagAwareAdapter|AbstractTagAwareAdapter $adapter
	) {}

	/** @param string|string[] $tag */
	public function tagged( string|array $tag ): self {
		$this->tags = (array) $tag;

		return $this;
	}

	public function item( string $key ): ?CacheItemInterface {
		$this->updateTag();

		return ( $item = $this->adapter->getItem( $key ) )->isHit() ? $item : null;
	}

	public function add(
		string $key,
		mixed $value,
		DateTimeInterface|DateInterval|int|Time|null $time = null
	): bool {
		$cached = false;
		$value  = $this->adapter->get(
			key: $key,
			callback: function ( CacheItemInterface $item, bool &$save ) use ( $time, $value, &$cached ) {
				$this->updateTag( self::addExpiry( $item, $time ) );

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
		DateTimeInterface|DateInterval|int|Time|null $time = null
	): bool {
		return $this->add( $key, $value, $time );
	}

	public function persist( string $key, mixed $value ): bool {
		return $this->add( $key, $value, time: null );
	}

	public function until( DateTimeInterface $time, string $key, mixed $value ): bool {
		return $this->add( $key, $value, $time );
	}

	public function for( int|Time|DateInterval $time, string $key, mixed $value ): bool {
		return $this->add( $key, $value, $time );
	}

	/** @var string|string[] $key */
	public function remove( string|array $key ): bool {
		$this->updateTag();

		return $this->adapter->deleteItems( (array) $key );
	}

	public function removeExpired(): bool {
		$this->updateTag();

		return $this->adapter instanceof PruneableInterface && $this->adapter->prune();
	}

	public function removeTagged( string|array $tags ): bool {
		return $this->adapter instanceof AbstractTagAwareAdapter
			&& $this->adapter->invalidateTags( (array) $tags );
	}

	public function flush(): bool {
		$this->updateTag();

		return method_exists( $this->adapter, method: 'clear' ) ? $this->adapter->clear() : false;
	}

	private static function addExpiry( CacheItemInterface $item, mixed $time ): CacheItemInterface {
		$time = $time instanceof Time ? $time->value : $time;

		return match ( true ) {
			is_int( $time ) || $time instanceof DateInterval => $item->expiresAfter( $time ),
			$time instanceof DateTimeInterface               => $item->expiresAt( $time ),
			default                                          => $item
		};
	}

	private function updateTag( mixed $item = null ): void {
		if (
			! empty( $this->tags )
			&& $item instanceof CacheItem
			&& $this->adapter instanceof TagAwareCacheInterface
		) {
			$item->tag( $this->tags );
		}

		$this->tags = array();
	}
}
