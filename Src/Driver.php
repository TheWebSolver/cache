<?php
/**
 * Driver that provides cache pool on-demand.
 *
 * @package TheWebSolver\Codegarage\Cache
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Cache;

use Closure;
use DateInterval;
use DateTimeInterface;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\PruneableInterface;
use TheWebSolver\Codegarage\Lib\Cache\Data\Time;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\AbstractTagAwareAdapter;

class Driver {
	private array $tags = array();

	public function __construct(
		public readonly AbstractAdapter|TagAwareAdapter|AbstractTagAwareAdapter|AdapterInterface $adapter,
		public readonly bool $taggable = false,
		public readonly bool $encrypted = false
	) {}

	/** @param string|string[] $tag */
	public function tagged( string|array $tag ): self {
		$this->tags = array_unique( array_filter( (array) $tag ) );

		return $this;
	}

	public function item( string $key ): ?CacheItem {
		return ( $item = $this->adapter->getItem( $key ) )->isHit() ? $item : null;
	}

	public function add(
		string $key,
		mixed $value,
		Time|DateTimeInterface|DateInterval|int|null $time = null
		/* $beta = `null` => Symfony handles re-computation; `true` => Force recomputation. */
	): ?CacheItem {
		$cached = null;

		$this->adapter->get(
			key: $key,
			callback: function ( CacheItem $item ) use ( $time, $value, &$cached ) {
				$this->updateTag( self::addExpiry( $item, $time ) );

				$cached = $item;

				return $value instanceof Closure ? $value( $item ) : $value;
			},
			beta: func_num_args() === 4 ? \INF : null
		);

		return $cached;
	}

	public function addComputed(
		string $key,
		Closure $value,
		Time|DateTimeInterface|DateInterval|int|null $time = null
	): ?CacheItem {
		return $this->add( $key, $value, $time );
	}

	public function until( DateTimeInterface $time, string $key, mixed $value ): ?CacheItem {
		return $this->add( $key, $value, $time );
	}

	public function for( Time|DateInterval|int $time, string $key, mixed $value ): ?CacheItem {
		return $this->add( $key, $value, $time );
	}

	public function persist( string $key, mixed $value ): bool {
		return null !== $this->add( $key, $value, time: null );
	}

	public function pull( string|array $key ): ?CacheItem {
		$this->updateTag();

		if ( $item = $this->item( $key ) ) {
			$this->delete( $key );

			return $item;
		}

		return null;
	}

	public function update(
		string $key,
		mixed $value,
		Time|DateTimeInterface|DateInterval|int|null $time = null
	): ?CacheItem {
		return $this->add( $key, $value, $time, /* Recompute Cached Item */ true );
	}

	public function increase( string $key, int $by = 1 ): ?CacheItem {
		return $this->updateIntValue( $key, $by, fn( int $current, int $by ): int => $current + $by );
	}

	public function decrease( string $key, int $by = 1 ): ?CacheItem {
		return $this->updateIntValue( $key, $by, fn( int $current, int $by ): int => $current - $by );
	}

	/** @var string|string[] $key */
	public function delete( string|array $key ): bool {
		$this->updateTag();

		return $this->adapter->deleteItems( (array) $key );
	}

	public function deleteExpired(): bool {
		$this->updateTag();

		return $this->adapter instanceof PruneableInterface && $this->adapter->prune();
	}

	public function deleteTagged( string|array $tags ): bool {
		$this->updateTag();

		return $this->isTaggable() && $this->adapter->invalidateTags( (array) $tags );
	}

	public function flush(): bool {
		$this->updateTag();

		return method_exists( $this->adapter, method: 'clear' ) ? $this->adapter->clear() : false;
	}

	public function isTaggable(): bool {
		return $this->taggable;
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
		if ( ! $this->isTaggable() || ! $item instanceof CacheItem ) {
			return;
		}

		// Assign user provided tags, or from tags previously set on item (if any).
		$tags = ! empty( $this->tags )
			? $this->tags
			: ( $item->getMetadata()[ CacheItem::METADATA_TAGS ] ?? array() );

		// Flush user provided tags so it'll not interfere with next item that'll be added to the pool.
		$this->tags = array();

		if ( ! empty( $tags ) ) {
			$item->tag( $tags );
		}
	}

	private function updateIntValue( string $key, int $by, Closure $compute ): ?CacheItem {
		if ( ! is_numeric( $current = ( $this->item( $key )?->get() ?? 0 ) ) ) {
			return null;
		}

		return $current ? $this->update( $key, value: $compute( (int) $current, $by ) ) : null;
	}
}
