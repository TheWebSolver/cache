<?php
/**
 * Factory to create cache driver.
 *
 * @package TheWebSolver\Codegarage\Cache
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Cache;

use LogicException;
use BadMethodCallException;
use Psr\Container\ContainerInterface;
use TheWebSolver\Codegarage\Lib\Cache\Data\PoolType;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use TheWebSolver\Codegarage\Lib\Cache\Data\Directory;

final class Factory {
	private static Factory $instance;
	private PoolType $default;

	/** @var array<string,Driver> */
	private array $drivers = array();

	/** @var array<string,mixed[]> */
	private array $config;

	/** @var array<string> */
	private array $crypto = array();

	public readonly Directory $directory;

	public function __construct() {
		$this->directory = new Directory( namespace: 'kyasa', location: dirname( __DIR__ ) . '/tmp/' );
	}

	/**
	 * @throws BadMethodCallException When undefined driver method was invoked.
	 * @mixin \TheWebSolver\Codegarage\Lib\Cache\Driver
	 */
	public function __call( string $method, array $args ): mixed {
		return method_exists( $driver = $this->driver(), $method )
			? $driver->$method( ...$args )
			: throw new BadMethodCallException( "Cache driver does not support method: {$method}." );
	}

	public static function start( ?ContainerInterface $app = null ): self {
		return $app?->get( id: self::class ) ?? ( self::$instance ??= new self() );
	}

	public function setEncryptionKeys( string|array $keys ): bool {
		if ( ! empty( $this->crypto ) ) {
			return false;
		}

		$this->crypto = PoolType::checkCrypto( $keys );

		return true;
	}

	/** @throws InvalidArgumentException When unsupported configuration given. */
	public function configure( Configurable $default, Configurable ...$additional ): void {
		if ( $type = $this->register( config: $default ) ) {
			$this->default = $type;
		}

		array_walk( array: $additional, callback: $this->register( ... ) );
	}

	public function isDefault( PoolType $type ): bool {
		return $this->getDefaultType() === $type;
	}

	public function isSupported(
		PoolType $type,
		bool $encrypted = false,
		bool $basic = false
	): bool {
		return ! $basic
			? isset( $this->drivers[ $this->awareKey( $type, $encrypted ) ] )
			: isset( $this->drivers[ $this->basicKey( $type, $encrypted ) ] );
	}

	public function encrypted( ?PoolType $type = null, bool $basic = false ): Driver {
		return $this->driver( $type, $basic, encrypted: true );
	}

	/**
	 * @param ?PoolType     $type       The Pool Type
	 * @param bool          $basic      Whether to get Driver with Adapter that doesn't support Tag.
	 * @param bool|string[] $encrypted  Whether to get encryption supported Driver. Almost always
	 *                                  pass a boolean value (unless you have specific need
	 *                                  to pass decryption keys).
	 * @throws LogicException When unregistered Cache Pool Type is being retrieved.
	 */
	public function driver(
		?PoolType $type = null,
		bool $basic = false,
		bool|array $encrypted = false
	): Driver {
		$type = $this->validateResolving( $type );

		return $basic ? $this->basic( $encrypted, $type ) : $this->taggable( $encrypted, $type );
	}

	/** @return string[] */
	public function getDecryptionKeys(): array {
		return $this->crypto;
	}

	/** @return string[] */
	public function decryptCryptoKeys(): array {
		static $decrypted = null;

		if ( null === $decrypted ) {
			$decrypted = array_map( callback: base64_decode( ... ), array: $this->getDecryptionKeys() );
		}

		return $decrypted;
	}

	private function register( Configurable $config ): ?PoolType {
		$type = PoolType::fromConfiguration( dto: $config );

		return ! isset( $this->drivers[ $key = $this->awareKey( $type, encrypted: false ) ] )
			? $this->createDriver( $key, $type, $config )
			: null;
	}

	private function createDriver( string $key, PoolType $type, Configurable $config ): PoolType {
		$this->drivers[ $key ] = new Driver(
			adapter: $this->get( false, $type, dto: $this->config[ $type->value ] ?? $config ),
			taggable: true,
			encrypted: false
		);

		return $type;
	}

	private function getDefaultType(): ?PoolType {
		return $this->default ?? null;
	}

	/** @param bool|string[] $encrypted */
	private function basic( bool|array $encrypted, PoolType $type ): Driver {
		$needsEncryption = PoolType::needsEncryption( keys: $encrypted );
		$key             = $this->basicKey( $type, encrypted: $needsEncryption );

		// Create Basic Driver on demand.
		return $this->drivers[ $key ] ??= new Driver(
			adapter: $type->basic( config: $this->config[ $type->value ], encrypted: $encrypted ),
			taggable: false,
			encrypted: $needsEncryption
		);
	}

	/** @param bool|string[] $encrypted */
	private function taggable( bool|array $encrypted, PoolType $type ): Driver {
		$key = $this->awareKey( $type, encrypted: PoolType::needsEncryption( keys: $encrypted ) );

		if ( ! $encrypted ) {
			return $this->drivers[ $key ];
		}

		// Create Encrypted driver on demand.
		return $this->drivers[ $key ] ??= new Driver(
			adapter: $this->get( $encrypted, $type, dto: $this->config[ $type->value ] ),
			taggable: true,
			encrypted: true
		);
	}

	private function basicKey( PoolType $type, bool $encrypted ): string {
		return $this->encryptedPrefix( $encrypted ) . $type->value;
	}

	private function awareKey( PoolType $type, bool $encrypted = false ): string {
		return "{$this->encryptedPrefix( $encrypted )}tagAware:{$type->value}";
	}

	private function encryptedPrefix( bool $encrypted ): string {
		return $encrypted ? 'encrypted:' : '';
	}

	/** @param bool|string[] $encrypted */
	private function get(
		bool|array $encrypted,
		PoolType $type,
		Configurable|array $dto,
	): AdapterInterface {
		// The $dto may be doing a round-trip and updating config value with same value again.
		// We are okay with that because the same type can have the same config value. The
		// possibility of this happening is based on whether the driver registration is
		// done twice. Once for non-encrypted version & one for the encrypted version.
		// It is designed this way to prevent doing array conversion multiple times.
		[ $adapter, $this->config[ $type->value ] ] = $type->tagAware( $dto, $encrypted );

		return $adapter;
	}

	private function validateResolving( ?PoolType $type ): PoolType {
		// We'll only check if non-encrypted tag-aware driver is supported before resolving.
		// Other driver types will be generated on-demand and cannot be bootstrapped.
		if ( $type && ! $this->isSupported( $type, encrypted: false, basic: false ) ) {
			throw new LogicException(
				'Additional Driver not set. Use method ' . Cache::class
				. "::configure() to register Driver for: {$type->fqcn()} during project bootstrap."
			);
		}

		return $type ?? $this->getDefaultType() ?? throw new LogicException(
			'Default driver not set. Use method ' . Cache::class
			. '::configure() to register it during project bootstrap.'
		);
	}
}
