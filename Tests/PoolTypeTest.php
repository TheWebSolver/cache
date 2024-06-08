<?php
/**
 * PoolType Enum Test.
 *
 * @package TheWebSolver\Codegarage\Test
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\PdoAdapter;
use TheWebSolver\Codegarage\Lib\Cache\Data\PdoDsn;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use TheWebSolver\Codegarage\Lib\Cache\Data\PoolType;
use TheWebSolver\Codegarage\Lib\Cache\Data\Directory;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;

class PoolTypeTest extends TestCase {
	public function testDefaultAdapter(): void {
		$this->assertSame( FilesystemAdapter::class, PoolType::FileSystem->adapter() );
		$this->assertSame( PdoAdapter::class, PoolType::Database->adapter() );
	}

	public function testFullyQualifiedClassName(): void {
		foreach ( PoolType::cases() as $case ) {
			$this->assertSame(
				expected: PoolType::class . '::' . $case->name,
				actual: $case->fqcn()
			);
		}
	}

	/**
	 * @param array<mixed> $data
	 * @dataProvider provideConfigAndArray
	 */
	public function testResolveTagAwareAdapter(
		array $data,
		object $config,
		PoolType $type,
		?string $class
	): void {
		$expectedClass           = $class ?? TagAwareAdapter::class;
		[ $adapter, $converted ] = $type->tagAware( $config );

		$this->assertSame( $data, $converted );
		$this->assertTrue( is_a( $adapter, $expectedClass ) );
	}

	/** @return array<mixed> */
	public function provideConfigAndArray(): array {
		return array(
			array(
				array( 'sub-directory', 5, 'parent-directory' ),
				new Directory( 'sub-directory', location: 'parent-directory', life: 5 ),
				PoolType::FileSystem,
				FilesystemTagAwareAdapter::class,
			),
			array(
				array( 'child', 0, 'parent' ),
				new Directory( location: 'parent', namespace: 'child' ),
				PoolType::FileSystem,
				FilesystemTagAwareAdapter::class,
			),
			array(
				array( 'sql:localhost:test', 'colId', 99, array( 'item_id' => 'cacheId' ) ),
				new PdoDsn( 'sql:localhost:test', 'colId', 99, array( 'item_id' => 'cacheId' ) ),
				PoolType::Database,
				null,
			),
		);
	}

	public function testExceptionThrownWhenWhenInvalidConfigGiven(): void {
		$this->expectException( InvalidArgumentException::class );
		PoolType::FileSystem->tagAware( new PdoDsn( 'sql' ) );
	}
}
