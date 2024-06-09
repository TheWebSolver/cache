<?php
/**
 * Factory Test.
 *
 * @package TheWebSolver\Codegarage\Test
 */

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use TheWebSolver\Codegarage\Lib\Cache\Factory;
use TheWebSolver\Codegarage\Lib\Cache\Data\PdoDsn;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use TheWebSolver\Codegarage\Lib\Cache\Data\PoolType;
use TheWebSolver\Codegarage\Lib\Cache\Data\Directory;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;

/** @coversDefaultClass \TheWebSolver\Codegarage\Lib\Cache\Factory */
class FactoryTest extends TestCase {
	private Factory $factory;

	public const TEST_CACHE_DIR = __DIR__ . '/cache';

	protected function setUp(): void {
		$this->factory = new Factory();
	}

	protected function tearDown(): void {
		$this->flushTestCacheDir( dir: self::TEST_CACHE_DIR );
	}

	private function flushTestCacheDir( string $dir ): bool {
		$removedEverything = false;

		if ( ! is_dir( $dir ) ) {
			return $removedEverything;
		}

		foreach ( scandir( $dir ) as $content ) {
			if ( '.' === $content || '..' === $content ) {
				continue;
			}

			$maybeDir          = $dir . DIRECTORY_SEPARATOR . $content;
			$removedEverything = is_dir( $maybeDir ) && ! is_link( $maybeDir )
				? $this->flushTestCacheDir( $maybeDir )
				: unlink( $maybeDir );
		}

		return rmdir( $dir ) && $removedEverything;
	}

	public function testSingleTonOrFromContainer(): void {
		$app = $this->createMock( ContainerInterface::class );

		$app->expects( $this->once() )
			->method( 'get' )
			->with( Factory::class )
			->willReturn( $this->factory );

		$this->assertSame( $this->factory, Factory::start( $app ) );
		$this->assertSame( expected: Factory::start(), actual: Factory::start() );
	}

	/**
	 * @covers ::setDefaultPool
	 * @covers ::isDefault
	 * @covers ::isSupported
	 */
	public function testDefaultPoolSetterAndVariousValidators(): void {
		$this->assertFalse( condition: $this->factory->isDefault( type: PoolType::FileSystem ) );
		$this->assertFalse( $this->factory->isSupported( PoolType::FileSystem ) );

		$directory = new Directory( location: self::TEST_CACHE_DIR );

		$this->assertTrue(
			$this->factory->setDefaultPool( PoolType::FileSystem, config: $directory )
		);

		$this->assertTrue( condition: $this->factory->isDefault( type: PoolType::FileSystem ) );

		$this->assertFalse(
			condition: $this->factory->setDefaultPool( PoolType::FileSystem, config: $directory ),
			message: 'Default Cache Pool must only be added once.'
		);

		$this->assertTrue( $this->factory->isSupported( PoolType::FileSystem ) );
		$this->assertFalse( $this->factory->isSupported( PoolType::Database ) );
	}

	public function testDriverSetterAndGetter(): void {
		$this->assertInstanceOf(
			expected: FilesystemTagAwareAdapter::class,
			actual: $this->factory->driver()->adapter,
			message: 'Factory must create a FileSystem Adapter that supports tagging feature, if no arguments passed.'
		);

		// Creates default File System Cache Pool without registering with "setDriver()" method.
		// To prevent this behavior, user must bootstrap application with their own config
		// before invoking "driver()" method without providing any of the PoolType.
		$this->assertTrue( $this->factory->isSupported( PoolType::FileSystem ) );

		$this->assertInstanceOf(
			expected: FilesystemAdapter::class,
			actual: $this->factory->driver( basic: true )->adapter,
			message: 'Factory must create a FileSystem Adapter that does not support tagging feature.'
		);

		$pdoDsn = new PdoDsn( dsn: 'mysql:host=localhost;dbname=testDatabase' );

		$this->factory->setDefaultPool( PoolType::Database, config: $pdoDsn );

		$this->assertInstanceOf(
			expected: TagAwareAdapter::class,
			actual: $this->factory->driver( PoolType::Database )->adapter
		);
	}

	public function testExceptionThrownIfDriverNotRegisteredWithGivenCachePoolType(): void {
		// Cannot retrieve Cache Pool that is not registered yet.
		$this->expectException( LogicException::class );
		$this->factory->driver( PoolType::FileSystem );
	}
}