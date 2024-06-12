<?php
/**
 * Factory to create cache driver.
 *
 * @package TheWebSolver\Codegarage\Library
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
		return self::$instance ??= $app?->get( id: self::class ) ?? new self();
	}

	public function isDefault( PoolType $type ): bool {
		return $this->getDefaultType() === $type;
	}

	public function isSupported( PoolType $type, bool $encrypted ): bool {
		return isset( $this->drivers[ $this->awareKey( $type, $encrypted ) ] );
	}

	public function setDefault( PoolType $type ): bool {
		if ( $this->default ?? false ) {
			return false;
		}

		$this->default = $type;

		return true;
	}

	public function setDriver( PoolType $type, object $config, bool $encrypted = false ): bool {
		if ( $this->drivers[ $key = $this->awareKey( $type, $encrypted ) ] ?? false ) {
			return false;
		}

		$config                = $this->config[ $type->value ] ?? $config;
		$this->drivers[ $key ] = new Driver(
			adapter: $this->get( $type, dto: $config, encrypted: $encrypted ),
			taggable: true
		);

		return true;
	}

	public function setEncryptionKeys( string|array $keys ): bool {
		if ( ! empty( $this->crypto ) ) {
			return false;
		}

		$this->crypto = $this->checkCrypto( $keys );

		return true;
	}

	/** @throws LogicException When unregistered Cache Pool Type is being retrieved. */
	public function driver(
		?PoolType $type = null,
		bool $basic = false,
		bool $encrypted = false
	): Driver {
		return $this->resolveDriver( $type, $basic, $encrypted );
	}

	public function encrypted( ?PoolType $type = null, bool $basic = false ): Driver {
		return $this->driver( $type, $basic, encrypted: true );
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

	private function resolveDriver( ?PoolType $type, bool $basic, bool $encrypted ): Driver {
		if ( $type && ! $this->isSupported( $type, $encrypted ) ) {
			throw new LogicException(
				'Cannot retrieve Driver for Cache Pool Type that is not registered. Use method '
				. Cache::class . '::setDriver() to register Driver for: ' . $type->fqcn() . '.'
			);
		}

		$type ??= $this->getDefaultType() ?? throw new LogicException(
			'Default driver not set. Use method ' . Cache::class . '::setDefault() to register it.'
		);

		return $basic
			? $this->basic( $type, $encrypted )
			: $this->drivers[ $this->awareKey( $type, $encrypted ) ];
	}

	private function getDefaultType(): ?PoolType {
		return $this->default ?? null;
	}

	private function basic( PoolType $type, bool $encrypted ): Driver {
		return $this->drivers[ $this->basicKey( $type, $encrypted ) ] ??= new Driver(
			adapter: $type->basic( config: $this->config[ $type->value ], encrypted: $encrypted )
		);
	}

	private function basicKey( PoolType $type, bool $encrypted ): string {
		return $this->encryptedPrefix( $encrypted ) . $type->value;
	}

	private function awareKey( PoolType $type, bool $encrypted ): string {
		return "{$this->encryptedPrefix( $encrypted )}tagAware:{$type->value}";
	}

	private function encryptedPrefix( bool $encrypted ): string {
		return $encrypted ? 'encrypted:' : '';
	}

	private function get( PoolType $type, object|array $dto, bool $encrypted ): AdapterInterface {
		// The $dto may be doing a round-trip and updating config value with same value again.
		// We are okay with that because the same type can have the same config value. The
		// possibility of this happening is based on whether the driver registration is
		// done twice. Once for non-encrypted version & one for the encrypted version.
		// It is designed this way to prevent doing array conversion multiple times.
		[ $adapter, $this->config[ $type->value ] ] = $type->tagAware( $dto, $encrypted );

		return $adapter;
	}

	/** @return string[] */
	private function checkCrypto( string|array $keys ): array {
		return array_unique( array_filter( (array) $keys ) );
	}
}
