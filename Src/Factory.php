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

	/** @var bool[] */
	private array $configured;

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
		return ! empty( $this->crypto )
			? false
			: ! empty( $this->crypto = array_unique( array_filter( (array) $keys ) ) );
	}

	/** @throws InvalidArgumentException When unsupported configuration given. */
	public function configure( Configurable $default, Configurable ...$additional ): bool {
		if ( $type = $this->register( config: $default ) ) {
			$this->default = $type;
		}

		array_walk( array: $additional, callback: $this->register( ... ) );

		$bootstrapped     = $this->configured;
		$this->configured = array();

		return ! in_array( needle: false, haystack: $bootstrapped, strict: true );
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

	/** @throws LogicException When unregistered Cache Pool Type is being retrieved. */
	public function driver(
		?PoolType $type = null,
		bool $basic = false,
		bool $encrypted = false
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
		$type = PoolType::fromConfiguration( $config );
		$key  = $this->awareKey( $type, encrypted: false );

		if ( isset( $this->drivers[ $key ] ) ) {
			$this->configured[] = false;

			return null;
		}

		$this->configured[]    = true;
		$adapter               = $this->get( $type, $config, encrypted: false );
		$this->drivers[ $key ] = new Driver( $adapter, taggable: true, encrypted: false );

		return $type;
	}

	private function getDefaultType(): ?PoolType {
		return $this->default ?? null;
	}

	private function basic( bool $encrypted, PoolType $type ): Driver {
		$key = $this->basicKey( $type, $encrypted );

		// Create Basic Driver on demand.
		return $this->drivers[ $key ] ??= new Driver(
			taggable: false,
			encrypted: $encrypted,
			adapter: $type->basic(
				config: $this->config[ $type->value ],
				encryptionKeys: $encrypted ? $this->decryptCryptoKeys() : null
			)
		);
	}

	private function taggable( bool $encrypted, PoolType $type ): Driver {
		$key = $this->awareKey( $type, $encrypted );

		if ( ! $encrypted ) {
			return $this->drivers[ $key ];
		}

		$adapter = $this->get( $type, config: $this->config[ $type->value ], encrypted: $encrypted );

		// Create Encrypted driver on demand.
		return $this->drivers[ $key ] ??= new Driver( $adapter, taggable: true, encrypted: $encrypted );
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

	private function get(
		PoolType $type,
		Configurable|array $config,
		bool $encrypted = false
	): AdapterInterface {
		[ $adapter, $this->config[ $type->value ] ] = $type->tagAware(
			config: $config,
			encryptionKeys: $encrypted ? $this->decryptCryptoKeys() : null
		);

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
