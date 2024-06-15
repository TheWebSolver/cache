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
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use TheWebSolver\Codegarage\Lib\Cache\Data\InMemoryArray;
use Symfony\Component\Cache\Adapter\AbstractTagAwareAdapter;

class DriverTest extends TestCase {
	/** Expects Cache Item key to be set as _**`cacheKey`**_. */
	private function simulateCacheMissesFirstTimeAndHitsSecondTime(
		string $val,
		bool $getCachedValue = true
	): MockObject&AbstractAdapter {
		$adapter = $this->createMock( AbstractAdapter::class );
		$isHit   = false;

		$adapter->expects( $this->exactly( 2 ) )
			->method( 'get' )
			->with( 'cacheKey', fn( $item ): string => $val )
			->willReturnCallback(
				function ( string $key, Closure $callback ) use ( $getCachedValue, &$isHit ) {
					if ( ! $isHit ) {
						$isHit = true;

						( $item = $this->createMock( CacheItem::class ) )
							->expects( $this->exactly( $getCachedValue ? 1 : 0 ) )
							->method( 'get' )
							->willReturn( $callback( $item ) );
					}
				}
			);

		return $adapter;
	}

	private function getMockedAdapter( string $adapterClass ): MockObject&AdapterInterface {
		/** @var MockObject&AdapterInterface */
		$mocked = $this->createMock( $adapterClass );

		return $mocked;
	}

	private function getDriverWithInMemoryCacheAdapter(): Driver {
		return new Driver(
			adapter: new TagAwareAdapter( new ArrayAdapter( ...( new InMemoryArray() )->toArray() ) ),
			taggable: true
		);
	}

	/** @dataProvider provideVariousItemTagTypes */
	public function testTaggedItem( string|array $tags, bool $expectTaggable ): void {
		$driver = new Driver( $adapter = $this->createMock( TagAwareAdapter::class ), taggable: true );

		$adapter->expects( $this->once() )
			->method( 'get' )
			->with( 'key', fn( $item ): string => 'value' )
			->willReturnCallback(
				function ( string $key, Closure $callable ) use ( $tags, $expectTaggable ) {
					$item = $this->createMock( CacheItem::class );

					$item->expects( $this->exactly( $expectTaggable ? 1 : 0 ) )
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
			array( array( '1', '2' ), true ),
			array( '' /* Empty tag */, false ),
			array( array( /* Empty tags array */ ), false ),
			array( array( '', '' /* Empty tags array with empty string */ ), false ),
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

		$misses = $driver->add( 'cacheKey', 'cache saved' );
		$hits   = $driver->add( 'cacheKey', 'is never triggered.' );

		$this->assertSame( 'cache saved', $misses->get() );
		$this->assertInstanceOf( CacheItem::class, $misses );
		$this->assertNull( $hits, message: 'Item instance must be flashed only once on Cache miss' );
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
						->expects( $this->once() )
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
				function ( string $key, Closure $callback ) use ( $date ) {
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

	public function testDeleteCachedItemsUsingCacheKey(): void {
		$adapter = $this->createMock( AbstractAdapter::class );
		$driver  = new Driver( $adapter );

		$adapter->expects( $this->exactly( 2 ) )
			->method( 'deleteItems' )
			->willReturn( true, false );

		$this->assertTrue( $driver->delete( 'cacheKey' ) );
		$this->assertFalse( $driver->delete( 'cacheKey' ) );
	}

	public function testDeleteTaggedCacheItemUsingTag(): void {
		$adapter = $this->createMock( TagAwareAdapter::class );
		$driver  = new Driver( $adapter, taggable: true );

		$adapter->expects( $this->once() )
			->method( 'invalidateTags' )
			->willReturn( true );

		$this->assertTrue( $driver->deleteTagged( 'testTag' ) );

		$driver = new Driver( $adapter, taggable: false );

		$this->assertFalse( $driver->deleteTagged( 'testAgain' ) );
	}

	/** @dataProvider providePruneableAdapters */
	public function testDeleteExpiredCacheItemsUsingCacheKey(
		string $adapterClass,
		bool $expectPruneMethod
	): void {
		$driver = new Driver( $adapter = $this->getMockedAdapter( $adapterClass ) );

		if ( $expectPruneMethod ) {
			$adapter->expects( $this->exactly( 2 ) )
				->method( 'prune' )
				->willReturn( false, true );

			$this->assertFalse( $driver->deleteExpired() );
		}

		$this->assertSame( $expectPruneMethod, $driver->deleteExpired() );
	}

	/** @return array<array{0:string,1:bool}> */
	public function providePruneableAdapters(): array {
		return array(
			array( TagAwareAdapter::class, true ),
			array( AbstractAdapter::class, false ),
			array( AbstractTagAwareAdapter::class, false ),
		);
	}

	/** @dataProvider provideFlushableAdapters */
	public function testFlushCachedItems( string $adapterClass, bool $expectClearMethod ): void {
		$driver = new Driver( $adapter = $this->getMockedAdapter( $adapterClass ) );

		if ( $expectClearMethod ) {
			$adapter->expects( $this->exactly( 2 ) )
				->method( 'clear' )
				->willReturn( false, true );

			$this->assertFalse( $driver->flush() );
		}

		$this->assertSame( $expectClearMethod, $driver->flush() );
	}

	/** @return array<array{0:string,1:bool}> */
	public function provideFlushableAdapters(): array {
		return array(
			array( AbstractAdapter::class, true ),
			array( TagAwareAdapter::class, false ),
			array( AbstractTagAwareAdapter::class, true ),
		);
	}

	public function testPullCacheItem(): void {
		$driver = $this->getDriverWithInMemoryCacheAdapter();

		$driver->add( key: 'pullIfExists', value: 'test' );

		$this->assertInstanceOf( CacheItem::class, $item = $driver->pull( 'pullIfExists' ) );
		$this->assertSame(
			expected: 'test',
			actual: $item->get(),
			message: "Pulled item's value must be same."
		);
		$this->assertNull(
			actual: $driver->pull( 'pullIfExists' ),
			message: 'Once pulled, it must be deleted from the Cache Pool.'
		);
	}

	public function testUpdateCacheItem(): void {
		$driver = $this->getDriverWithInMemoryCacheAdapter();

		$driver->tagged( array( 'tag1', 'tag2' ) )->add( 'cacheKey', value: 'currentValue' );
		$driver->update( 'cacheKey', value: 'updatedValue' );
		$this->assertSame( 'updatedValue', $driver->item( 'cacheKey' )->get() );
	}

	/** @dataProvider provideIncrementDecrementValues */
	public function testIncrementDecrementCacheItem(
		int $by,
		mixed $value,
		?bool $increase,
		mixed $expectedValue
	): void {
		$driver = $this->getDriverWithInMemoryCacheAdapter();

		if ( is_bool( $increase ) ) {
			$driver->add( 'test', $value );
		}

		match ( $increase ) {
			true  => $this->assertSame( $expectedValue, $driver->increase( 'test', $by )?->get() ),
			false => $this->assertSame( $expectedValue, $driver->decrease( 'test', $by )?->get() ),
			null  => $this->assertNull( $driver->increase( 'test', $by ) )
				|| $this->assertNull( $driver->decrease( 'test', $by ) )
		};
	}

	public function provideIncrementDecrementValues(): array {
		return array(
			array(
				'by'            => 1,
				'value'         => 5,
				'increase'      => true,
				'expectedValue' => 6,
			),
			array(
				'by'            => 3,
				'value'         => 7,
				'increase'      => false,
				'expectedValue' => 4,
			),
			array(
				'by'            => 3,
				'value'         => 7,
				'increase'      => null,
				'expectedValue' => null,
			),
			array(
				'by'            => 1,
				'value'         => 'non-numeric-returns-null',
				'increase'      => true,
				'expectedValue' => null,
			),
			array(
				'by'            => 1,
				'value'         => 'non-numeric-returns-null',
				'increase'      => false,
				'expectedValue' => null,
			),
		);
	}
}
