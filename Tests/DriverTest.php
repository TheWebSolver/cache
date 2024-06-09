<?php
/**
 * Driver Test.
 *
 * @package TheWebSolver\Codegarage\Test
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\CacheItem;
use TheWebSolver\Codegarage\Lib\Cache\Driver;
use Symfony\Component\Cache\Adapter\AbstractAdapter;

class DriverTest extends TestCase {
	public function testGettingCachedItem(): void {
		$adapter = $this->createMock( AbstractAdapter::class );
		$adapter->expects( $this->exactly( 2 ) )
			->method( 'getItem' )
			->with( 'cacheItemKey' )
			->willReturn( $cachedItem = $this->createMock( CacheItem::class ) );

		$cachedItem->expects( $this->exactly( 2 ) )
			->method( 'isHit' )
			->willReturn( true, false );

		$driver = new Driver( $adapter );

		$this->assertSame( $cachedItem, actual: $driver->item( 'cacheItemKey' ) );
		$this->assertNull( $driver->item( 'cacheItemKey' ) );
	}

	public function testCacheItemAddition(): void {
		$adapter  = $this->createMock( AbstractAdapter::class );
		$item     = null;

		$adapter->expects( $this->exactly( 2 ) )
			->method( 'get' )
			->with( 'cacheKey', fn(): string => 'set value only if cache misses' )
			->willReturnCallback(
				function () use ( &$item ) {
					// Simulate whether cache item is hit or miss.
					static $isHit = false;

					// If cache item hits, the value already exists in the cache (until it expires).
					// When this happens, the passed argument as callback is never invoked.
					// Due to this, the adapter misses assigning the cached item.
					if ( $isHit ) {
						return $item = null;
					}

					$isHit = true;

					return $item = $this->createStub( CacheItem::class );
				}
			);

		$driver = new Driver( $adapter );

		$driver->add( 'cacheKey', 'value' );
		$this->assertInstanceOf( CacheItem::class, $item );

		$driver->add( 'cacheKey', 'value' );
		$this->assertNull( $item );
	}

	public function testAddingComputedValue(): void {
		$adapter  = $this->createMock( AbstractAdapter::class );
		$driver   = new Driver( $adapter );
		$computed = fn( $item ): string => 'Computed Value';

		$adapter->expects( $this->once() )
			->method( 'get' )
			->with( 'key', $computed )
			->willReturnCallback(
				function ( string $key, Closure $compute ) {
					( $item = $this->createMock( CacheItem::class ) )
						->expects( $this->once() )
						->method( 'get' )
						->willReturn( $compute( $item ) );
				}
			);

		$this->assertSame( 'Computed Value', $driver->addComputed( 'key', $computed )->get() );
	}

	public function testAddingItemExpiresAt(): void {
		$adapter = $this->createMock( AbstractAdapter::class );
		$driver  = new Driver( $adapter );
		$date    = DateTimeImmutable::createFromFormat( 'Y-M', '2024-May' );
		$callback = fn( $item ) => 'value';

		$adapter->expects( $this->once() )
			->method( 'get' )
			->with( 'key', $callback )
			->willReturnCallback(
				function ( $key, $callback ) use ( $date ) {
					$item = $this->createMock( CacheItem::class );

					$item->expects( $this->once() )
						->method( 'expiresAt' )
						->with( $date )
						->willReturnSelf();

					$callback( $item );
				}
			);

		$driver->until( $date, 'key', 'value' );
	}
}
