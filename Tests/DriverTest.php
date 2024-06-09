<?php
/**
 * Driver Test.
 *
 * @package TheWebSolver\Codegarage\Test
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\CacheItem;
use PHPUnit\Framework\MockObject\MockObject;
use TheWebSolver\Codegarage\Lib\Cache\Driver;
use TheWebSolver\Codegarage\Lib\Cache\Data\Time;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

class DriverTest extends TestCase {
	private bool $isHit = false;

	/** Expects Cache Item key to be set as _**`cacheKey`**_. */
	private function simulateCacheMissesFirstTimeAndHitsSecondTime(
		string $val,
		bool $getCachedValue = true
	): MockObject&AbstractAdapter {
		$adapter     = $this->createMock( AbstractAdapter::class );
		$this->isHit = false;

		$adapter->expects( $this->exactly( 2 ) )
			->method( 'get' )
			->with( 'cacheKey', fn( $item ): string => $val )
			->willReturnCallback(
				function ( string $key, Closure $callback ) use ( $getCachedValue ) {
					if ( ! $this->isHit ) {
						$this->isHit = true;

						( $item = $this->createMock( CacheItem::class ) )
							->expects( $this->exactly( $getCachedValue ? 1 : 0 ) )
							->method( 'get' )
							->willReturn( $callback( $item ) );
					}
				}
			);

		return $adapter;
	}

	/** @dataProvider provideVariousItemTagTypes */
	public function testTaggedItem( string|array $tags, bool $taggable ): void {
		$driver = new Driver( $adapter = $this->createMock( TagAwareAdapter::class ), $taggable );

		$adapter->expects( $this->once() )
			->method( 'get' )
			->with( 'key', fn( $item ): string => 'value' )
			->willReturnCallback(
				function ( string $key, Closure $callable ) use ( $tags, $taggable ) {
					$item = $this->createMock( CacheItem::class );

					$item->expects( $this->exactly( $taggable ? 1 : 0 ) )
						->method( 'tag' )
						->with( (array) $tags )
						->willReturnSelf();

						$callable( $item );
				}
			);

		$driver->tagged( $tags )->add( 'key', 'value' );
	}

	/** @return array<array<string|string[]>> */
	public function provideVariousItemTagTypes(): array {
		return array(
			array( 'testOne', true ),
			array( 'should not be added', false ),
			array( array( '1', '2' ), true ),
			array( array( 'non-taggable', 'adapter' ), false ),
		);
	}

	public function testIsTaggableGetter(): void {
		$this->assertTrue(
			( new Driver( $this->createStub( AbstractAdapter::class ), taggable: true ) )->isTaggable()
		);

		$this->assertFalse(
			( new Driver( $this->createStub( AbstractAdapter::class ), taggable: false ) )->isTaggable()
		);
	}

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
		$driver = new Driver(
			$this->simulateCacheMissesFirstTimeAndHitsSecondTime( 'cache saved', getCachedValue: true )
		);

		$missed = $driver->add( 'cacheKey', 'cache saved' );
		$hits   = $driver->add( 'cacheKey', 'is never triggered.' );

		$this->assertSame( 'cache saved', $missed->get() );
		$this->assertInstanceOf( CacheItem::class, $missed );
		$this->assertNull( $hits );
	}

	public function testPersistingItemForever(): void {
		$driver = new Driver(
			$this->simulateCacheMissesFirstTimeAndHitsSecondTime( 'persist', getCachedValue: false )
		);

		$this->assertTrue( $driver->persist( 'cacheKey', 'persist' ) );
		$this->assertFalse( $driver->persist( 'cacheKey', 'is never triggered' ) );
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
						->method( 'get' )
						->willReturn( $compute( $item ) );
				}
			);

		$this->assertSame( 'Computed Value', actual: $driver->addComputed( 'key', $computed )->get() );
	}

	public function testAddingItemUntilTheGivenTime(): void {
		$adapter  = $this->createMock( AbstractAdapter::class );
		$driver   = new Driver( $adapter );
		$date     = DateTimeImmutable::createFromFormat( 'Y-M', '2024-May' );
		$callback = fn( $item ) => 'value';

		$adapter->expects( $this->once() )
			->method( 'get' )
			->with( 'key', $callback )
			->willReturnCallback(
				function ( $key, $callback ) use ( $date ) {
					( $item = $this->createMock( CacheItem::class ) )
						->expects( $this->once() )
						->method( 'expiresAt' )
						->with( $date )
						->willReturnSelf();

					$item->method( 'get' )->willReturn( $callback( $item ) );
				}
			);

		$this->assertSame( 'value', actual: $driver->until( $date, 'key', 'value' )->get() );
	}

	/** @dataProvider provideVariousExpirationTimes */
	public function testAddingItemForTheGivenTime( Time|DateInterval|int $expiresAfter ): void {
		$adapter  = $this->createMock( AbstractAdapter::class );
		$driver   = new Driver( $adapter );
		$callback = fn( $item ) => 'value';

		$adapter->expects( $this->once() )
			->method( 'get' )
			->with( 'key', $callback )
			->willReturnCallback(
				function ( string $key, Closure $callback ) use ( $expiresAfter ) {
					( $item = $this->createMock( CacheItem::class ) )
						->expects( $this->once() )
						->method( 'expiresAfter' )
						// Adapter itself does not accept "Time" enum. Make it compatible.
						->with( $expiresAfter instanceof Time ? $expiresAfter->value : $expiresAfter )
						->willReturnSelf();

					$item->method( 'get' )->willReturn( $callback( $item ) );
				}
			);

		$this->assertSame( 'value', actual: $driver->for( $expiresAfter, 'key', 'value' )->get() );
	}

	public function provideVariousExpirationTimes(): array {
		return array(
			array( 30 ),
			array( Time::Minute ),
			array( new DateInterval( 'PT1M' ) ),
		);
	}
}
