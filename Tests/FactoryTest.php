<?php
/**
 * Factory Test.
 *
 * @package TheWebSolver\Codegarage\Test
 */

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use TheWebSolver\Codegarage\Lib\Cache\Factory;
use Symfony\Component\Cache\Adapter\PdoAdapter;
use TheWebSolver\Codegarage\Lib\Cache\Data\PdoDsn;
use TheWebSolver\Codegarage\Lib\Cache\Configurable;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use TheWebSolver\Codegarage\Lib\Cache\Data\PoolType;
use TheWebSolver\Codegarage\Lib\Cache\Data\Directory;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;

/** @coversDefaultClass \TheWebSolver\Codegarage\Lib\Cache\Factory */
class FactoryTest extends TestCase {
	private Factory $factory;
	private Configurable $defaultConfig;

	public const TEST_CACHE_DIR  = __DIR__ . '/cache';
	public const TEST_CONN_DSN   = 'mysql:host=localhost;dbname=testDatabase';
	public const TEST_CRYPTO_KEY = 'FzuBrVYVR3Ls6AIJgMTEqnM6/XBiTWA+Qd6JMr10yOQzCLr5fOOHVbxbw+3cD0g5gidAizoYUspk6eefH/w3WA==';

	protected function setUp(): void {
		$this->factory       = new Factory();
		$this->defaultConfig = new Directory( location: self::TEST_CACHE_DIR );
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

	/** @return string[] */
	public static function getDecryptionKeysForMarshaller(): array {
		return array( base64_decode( self::TEST_CRYPTO_KEY ) );
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

		$this->factory->configure( default: $this->defaultConfig );

		$this->assertTrue( $this->factory->isDefault( PoolType::FileSystem ) );
		$this->assertTrue( $this->factory->isSupported( PoolType::FileSystem ) );
		$this->assertFalse( $this->factory->isSupported( PoolType::Database ) );

		$this->factory->configure(
			$this->defaultConfig,
			new PdoDsn( dsn: self::TEST_CONN_DSN )
		);

		$this->assertTrue( $this->factory->isSupported( PoolType::Database ) );

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
		$this->assertInstanceOf(
			expected: FilesystemTagAwareAdapter::class,
			actual: $factory->driver()->adapter,
			message: 'Factory must create driver with FileSystem Adapter that'
			. ' supports tagging feature, if no arguments passed.'
		);

		$this->assertInstanceOf(
			expected: FilesystemAdapter::class,
			actual: $factory->driver( basic: true )->adapter,
			message: 'Factory must create driver with FileSystem Adapter that'
			. ' does not support tagging feature if basic set to "true".'
		);

		$keys = $this->getDecryptionKeysForMarshaller();

		$this->assertFalse( $factory->isSupported( PoolType::FileSystem, encrypted: true ) );
		$this->assertTrue( $factory->driver( basic: true, encrypted: $keys )->encrypted );
		$this->assertTrue(
			$factory->isSupported( PoolType::FileSystem, encrypted: true, basic: true )
		);

		$this->assertInstanceOf(
			expected: TagAwareAdapter::class,
			actual: $factory->driver( type: PoolType::Database )->adapter,
			message: 'Factory must create driver with TagAware Adapter that supports'
			. ' tagging feature when type passed but basic is set to "false".'
		);

		$this->assertFalse( $factory->isSupported( PoolType::Database, encrypted: true ) );
		$this->assertTrue( $factory->driver( type: PoolType::Database, encrypted: $keys )->encrypted );
		$this->assertTrue( $factory->isSupported( PoolType::Database, encrypted: true ) );

		$this->assertInstanceOf(
			expected: PdoAdapter::class,
			actual: $factory->driver( type: PoolType::Database, basic: true )->adapter,
			message: 'Factory must create driver with Pdo Adapter that does not'
			. ' support tagging feature if basic is set to "true".'
		);
	}

	public function testExceptionThrownIfDriverNotRegisteredWithGivenCachePoolType(): void {
		// Cannot retrieve Cache Pool that is not configured yet.
		$this->expectException( LogicException::class );
		$this->factory->driver( PoolType::FileSystem );
	}

	public function testExceptionThrownIfAdditionalDriverNotRegistered(): void {
		$this->factory->configure( $this->defaultConfig );

		// Cannot retrieve additional Cache Pool other than default if not configured yet.
		$this->expectException( LogicException::class );
		$this->factory->driver( PoolType::Database );
	}
}
