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
use TheWebSolver\Codegarage\Lib\Cache\Configurable;
use TheWebSolver\Codegarage\Lib\Cache\Configurator;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use TheWebSolver\Codegarage\Lib\Cache\Data\PoolType;
use TheWebSolver\Codegarage\Lib\Cache\Data\Directory;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Marshaller\SodiumMarshaller;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\Marshaller\TagAwareMarshaller;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;

class PoolTypeTest extends TestCase {
	/** @dataProvider provideConfigurationDto */
	public function testPoolTypeFromConfigurationDto(
		Configurable $dto,
		PoolType $expected,
		bool $thrown,
	): void {
		if ( $thrown ) {
			$this->expectException( InvalidArgumentException::class );
		}

		$this->assertSame( $expected, actual: PoolType::fromConfiguration( $dto ) );
	}

	/** @return array<array{0:Configurable,1:PoolType,2:bool}> */
	public function provideConfigurationDto(): array {
		return array(
			array( new Directory(), PoolType::FileSystem, false ),
			array( new PdoDsn( '' ), PoolType::Database, false ),
			array( new class() implements Configurable { use Configurator; }, PoolType::Database, true ),
		);
	}

	/** @dataProvider providePoolTypeAndItsConfigurationDto */
	public function testConfigurationDtoMatchesPoolType(
		bool $expected,
		PoolType $type,
		object $dto
	): void {
		if ( $expected ) {
			$this->assertSame( expected: $dto::class, actual: $type->dto() );
		} else {
			$this->expectException( InvalidArgumentException::class );
		}

		$this->assertSame( expected: $expected, actual: $type->isValid( $dto ) );
		$this->assertSame( expected: $dto, actual: $type->validate( $dto ) );
	}

	/** @dataProvider provideAdaptersByPoolType */
	public function testAdapterTypes( string $default, ?string $tagAware, PoolType $type ): void {
		$this->assertSame( expected: $default, actual: $type->adapter() );
		$this->assertSame(
			expected: $tagAware ?? TagAwareAdapter::class,
			actual: $type->tagAwareAdapter()
		);
	}

	/** @return array<array{0:class-string<Configurable>,1:PoolType}> */
	public function provideAdaptersByPoolType(): array {
		return array(
			array( FilesystemAdapter::class, FilesystemTagAwareAdapter::class, PoolType::FileSystem ),
			array( PdoAdapter::class, null, PoolType::Database ),
		);
	}

	public function testFullyQualifiedClassName(): void {
		foreach ( PoolType::cases() as $case ) {
			$this->assertSame( expected: PoolType::class . '::' . $case->name, actual: $case->fqcn() );
		}
	}

	/** @dataProvider providePoolTypeAndItsConfigurationDto */
	public function testBasicAdapterGetterIntegration(
		bool $isValid,
		PoolType $type,
		Configurable $dto
	): void {
		if ( ! $isValid ) {
			// TypeError thrown by Symfony Cache when invalid Configurable
			// object's array value passed as constructor args.
			$this->expectException( TypeError::class );
		}

		$this->assertInstanceOf( expected: $type->adapter(), actual: $type->basic( $dto->toArray() ) );
	}

	/** @dataProvider providePoolTypeAndItsConfigurationDto */
	public function testTagAwareAdapterGetterIntegration(
		bool $isValid,
		PoolType $type,
		Configurable $dto
	): void {
		if ( ! $isValid ) {
			// TypeError thrown by Symfony Cache when invalid Configurable
			// object's array value passed as constructor args.
			$this->expectException( TypeError::class );
		}

		[ $adapter ] = $type->tagAware( $dto->toArray() );

		$this->assertInstanceOf( expected: $type->tagAwareAdapter(), actual: $adapter );
	}

	/** @return array<array{0:bool,1:PoolType,2:Configurable}> */
	public function providePoolTypeAndItsConfigurationDto(): array {
		return array(
			array( true, PoolType::FileSystem, new Directory() ),
			array( true, PoolType::Database, new PdoDsn( '' ) ),
			array( false, PoolType::FileSystem, new PdoDsn( '' ) ),
			array( false, PoolType::Database, new Directory() ),
			array( false, PoolType::Database, new class() implements Configurable { use Configurator; } ),
		);
	}

	/**
	 * @param string        $marshaller
	 * @param bool|string[] $encrypt
	 * @dataProvider provideMarshallerCreationArgs
	 */
	public function testMarshallerGetter( string $marshaller, bool|array $encrypt, bool $taggable ): void {
		$this->assertInstanceOf(
			expected: $marshaller,
			actual: PoolType::resolveMarshaller( isEncrypted: $encrypt, isTagAware: $taggable )
		);
	}

	/** @return array<array{0:class-string<MarshallerInterface,1:bool,2:bool}> */
	public function provideMarshallerCreationArgs(): array {
		return array(
			array( DefaultMarshaller::class, false, false ),
			array( TagAwareMarshaller::class, false, true ),
			array( SodiumMarshaller::class, FactoryTest::getDecryptionKeysForMarshaller(), false ),
			array( SodiumMarshaller::class, FactoryTest::getDecryptionKeysForMarshaller(), true ),
		);
	}

	/**
	 * @param bool|string[] $keys
	 * @dataProvider provideEncryptionKeys
	 */
	public function testEncryptionNeeded( bool|array $keys, bool $expected ): void {
		$this->assertSame( $expected, actual: PoolType::needsEncryption( $keys ) );
	}

	public function provideEncryptionKeys(): array {
		return array(
			array( true, true ),
			array( false, false ),
			array( array(), false ),
			array( array( '' ), false ),
		);
	}
}
