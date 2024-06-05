<?php
/**
 * Adapter.
 *
 * @package TheWebSolver\Codegarage\Library
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Cache;

use Psr\Container\ContainerInterface;

class Adapter {
	private static ?ContainerInterface $app = null;

	public static function app(): ?ContainerInterface {
		return static::$app;
	}

	public static function setApp( ?ContainerInterface $app ): void {
		static::$app = $app;
	}
}
