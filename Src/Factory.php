<?php
/**
 * Factory to create cache driver.
 *
 * @package TheWebSolver\Codegarage\Library
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Cache;

use Psr\Container\ContainerInterface;
use Symfony\Component\Cache\Adapter\PdoAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;

final class Factory {
	public const DATABASE    = 'sqlDatabase';
	public const FILE_SYSTEM = 'fileSystem';

	private static Factory $instance;
	private static Directory $defaultConfig;

	private array $drivers = array();

	public function __construct() {
		self::$defaultConfig = new Directory(
			namespace: 'rewriteReloaded',
			location: dirname( __DIR__ ) . '/tmp/'
		);
	}

	public static function start( ?ContainerInterface $app = null ): self {
		return self::$instance ??= ( $app?->get( id: self::class ) ?? new self() );
	}

	public function driver( ?string $store = null, ?object $config = null ): Driver {
		$store ??= self::FILE_SYSTEM;

		return $this->drivers[ $store ] ??= $this->get( $store, dto: $config ?? self::$defaultConfig );
	}

	private static function get( string $store, ?object $dto ): Driver {
		$dto   ??= self::$defaultConfig;
		$config  = array_values( (array) $dto );

		return match ( $store ) {
			self::FILE_SYSTEM => new Driver( new FilesystemTagAwareAdapter( ...$config ) ),
			self::DATABASE    => new Driver( new TagAwareAdapter( new PdoAdapter( ...$config ) ) ),
		};
	}
}
