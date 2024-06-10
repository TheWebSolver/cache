## Welcome

A simple, straight-forward Facade for [Symfony Cache][c].

This package aims to provide a better Developer Experience (DX) to interact with various Cache Adapters required by a PHP Project. Powered by Symfony Cache, this library provides support for [Stampede Prevention][s] out of the Box by opting to use Symfony _Cache Contracts_ instead of [PSR-6][6] _Cache Interface_.

## Benefits

- Aims to provide easier (with dead-simple API) and Type-safe (more secure and robust) application design.
- Exposes various methods:
	- for registering *_Adapters_* in the PHP project Bootstrap file, and
	- to use those registered *_Adapters_* flawlessly from within Middlewares (or anywhere it seems fit).
- Supports Dependency Injection with your own App Container (that implements [PSR-11][11] _Container Interface_).

## Usage

For usage details, visit [Wiki page][w].

<!-- MARKDOWN LINKS -->
<!-- https://www.markdownguide.org/basic-syntax/#reference-style-links -->
[c]: https://symfony.com/doc/current/components/cache.html
[s]: https://symfony.com/doc/current/components/cache.html#stampede-prevention
[6]: https://www.php-fig.org/psr/psr-6/
[11]: https://www.php-fig.org/psr/psr-11/
[w]: https://github.com/TheWebSolver/cache/wiki
