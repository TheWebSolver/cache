<?php
/**
 * Factory Test.
 *
 * @package TheWebSolver\Codegarage\Test
 */

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use TheWebSolver\Codegarage\Lib\Cache\Driver;
use TheWebSolver\Codegarage\Lib\Cache\Factory;
use Symfony\Component\Cache\Adapter\PdoAdapter;
use TheWebSolver\Codegarage\Lib\Cache\Data\PdoDsn;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use TheWebSolver\Codegarage\Lib\Cache\Data\PoolType;
use TheWebSolver\Codegarage\Lib\Cache\Data\Directory;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use TheWebSolver\Codegarage\Lib\Cache\Data\InMemoryArray;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;

/** @coversDefaultClass \TheWebSolver\Codegarage\Lib\Cache\Factory */
class FactoryTest extends TestCase {
	private Factory $factory;

	public const TEST_CACHE_DIR  = __DIR__ . '/cache';
	public const TEST_CONN_DSN   = 'mysql:host=localhost;dbname=testDatabase';
	public const TEST_CRYPTO_KEY = 'FzuBrVYVR3Ls6AIJgMTEqnM6/XBiTWA+Qd6JMr10yOQzCLr5fOOHVbxbw+3cD0g5gidAizoYUspk6eefH/w3WA==';

	protected function setUp(): void {
		$this->factory = new Factory();
	}

	protected function tearDown(): void {
		$this->flushTestCacheDir( dir: self::TEST_CACHE_DIR );
	}

	public static function flushTestCacheDir( string $dir ): bool {
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
				? self::flushTestCacheDir( $maybeDir )
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

	public function testFactoryConfigurationBootstrapIntegration(): Factory {
		$this->assertFalse( $this->factory->isDefault( PoolType::FileSystem ) );

		$configured = $this->factory->configure(
			new Directory( location: self::TEST_CACHE_DIR ),
			new PdoDsn( dsn: self::TEST_CONN_DSN )
		);

		$this->assertTrue(
			condition: $configured,
			message: 'Returns true only if default and all additional configs are being bootstrapped'
			. ' only for the first time.'
		);

		$this->assertTrue( $this->factory->isDefault( PoolType::FileSystem ) );
		$this->assertTrue( $this->factory->isSupported( PoolType::FileSystem ) );
		$this->assertTrue( $this->factory->isSupported( PoolType::Database ) );

		$this->assertFalse(
			condition: $this->factory->configure( new Directory( location: self::TEST_CACHE_DIR ) ),
			message: 'Same config, either default or additional cannot be bootstrapped more than once.'
		);

		return $this->factory;
	}

	/**
	 * Performs test that factory produces driver with appropriate Cache Adapter instance.
	 *
	 * The [#3] parameter `$encrypted` is not directly testable because it is delegated
	 * to the PoolType which instantiates appropriate Marshaller based on whether
	 * encrypted version of adapter is required or not. The encrypted arg test
	 * can loosely be confirmed by `PoolType::resolverMarshaller()` test.
	 * However, we can test if respective driver is instantiated by
	 * checking with `Factory::isSupported()` method.
	 *
	 * @depends testFactoryConfigurationBootstrapIntegration
	 */
	public function testFactoryAndItsIntegration( Factory $factory ): void {
		$this->assertTrue( $factory->setEncryptionKeys( self::TEST_CRYPTO_KEY ) );
		$this->assertFalse( $factory->setEncryptionKeys( self::TEST_CRYPTO_KEY ) );
		$this->assertSame( self::TEST_CRYPTO_KEY, $factory->getDecryptionKeys()[0] );
		$this->assertSame(
			expected: base64_decode( self::TEST_CRYPTO_KEY ),
			actual: $factory->decryptCryptoKeys()[0]
		);

		$this->assertInstanceOf(
			expected: FilesystemTagAwareAdapter::class,
			actual: $factory->driver()->adapter,
			message: 'Taggable Cache Pool Driver must be available on demand for default Pool Type.'
		);

		$this->assertInstanceOf(
			expected: FilesystemAdapter::class,
			actual: $factory->driver( basic: true )->adapter,
			message: 'Non-taggable Cache Pool Driver must be available on demand for default Pool Type.'
		);

		$this->assertFalse( $factory->isSupported( PoolType::FileSystem, encrypted: true ) );
		$this->assertTrue( $factory->encrypted( basic: true )->encrypted );
		$this->assertTrue(
			condition: $factory->isSupported( PoolType::FileSystem, encrypted: true, basic: true ),
			message: 'Encryption supported Non-taggable Cache Pool Driver must be available on demand.'
		);

		$this->assertInstanceOf(
			expected: TagAwareAdapter::class,
			actual: $factory->driver( type: PoolType::Database )->adapter,
			message: 'Taggable Cache Pool Driver must be available on demand for given Pool Type.'
		);

		$this->assertFalse( $factory->isSupported( PoolType::Database, encrypted: true ) );
		$this->assertTrue( $factory->driver( type: PoolType::Database, encrypted: true )->encrypted );
		$this->assertTrue(
			condition: $factory->isSupported( PoolType::Database, encrypted: true ),
			message: 'Encryption supported Taggable Cache Pool Driver must be available on demand.'
		);

		$this->assertInstanceOf(
			expected: PdoAdapter::class,
			actual: $factory->driver( type: PoolType::Database, basic: true )->adapter,
			message: 'Non-taggable Cache Pool Driver must be available on demand for given Pool Type.'
		);
	}

	public function testExceptionThrownIfDriverNotRegisteredWithGivenCachePoolType(): void {
		// Cannot retrieve Cache Pool that is not configured yet.
		$this->expectException( LogicException::class );
		$this->factory->driver( PoolType::FileSystem );
	}

	public function testExceptionThrownIfAdditionalDriverNotRegistered(): void {
		$this->factory->configure( new InMemoryArray() );

		$this->assertInstanceOf( Driver::class, $this->factory->driver() );

		// Cannot retrieve additional Cache Pool other than default if not configured yet.
		$this->expectException( LogicException::class );
		$this->factory->driver( PoolType::Database );
	}
}
