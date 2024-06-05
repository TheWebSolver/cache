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

final class CacheFactory {
	public const DATABASE    = 'sqlDatabase';
	public const FILE_SYSTEM = 'fileSystem';

	private static CacheFactory $instance;
	private static FileSystem $defaultConfig;

	private array $drivers = array();

	public function __construct( ?ContainerInterface $app = null ) {
		self::$instance      = $app?->has( self::class ) ? $app?->get( self::class ) : null;
		self::$defaultConfig = new FileSystem(
			namespace: 'rewriteReloaded',
			location: dirname( __DIR__ ) . '/tmp/'
		);
	}

	public static function start(): self {
		return self::$instance ??= new self();
	}

	public function driver( ?string $store = null, ?object $config = null ): CacheDriver {
		$store ??= self::FILE_SYSTEM;

		return $this->drivers[ $store ] ??= $this->get( $store, dto: $config ?? self::$defaultConfig );
	}

	private static function get( string $store, ?object $dto ): CacheDriver {
		$dto   ??= self::$defaultConfig;
		$config  = array_values( (array) $dto );

		return match ( $store ) {
			self::FILE_SYSTEM => new CacheDriver( new FilesystemTagAwareAdapter( ...$config ) ),
			self::DATABASE    => new CacheDriver( new TagAwareAdapter( new PdoAdapter( ...$config ) ) ),
		};
	}
}
