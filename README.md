# Traverse

**AI-native web browsing for Laravel.**

Traverse will give Laravel applications and AI agents efficient, structured access to the web without relying on an AI provider's built-in browsing features.

## Status

Traverse is in early development. This repository currently provides the Laravel package foundation and architecture contracts; browser operations require the forthcoming Lightpanda driver.

## Vision

The first planned driver is [Lightpanda](https://lightpanda.io/). Traverse will expose its machine-native browsing capabilities through a focused Laravel abstraction, while leaving room for future drivers that provide equivalent native capabilities.

Traverse is not:

- a replacement for Laravel Dusk, Playwright, or Puppeteer;
- a general-purpose browser automation framework;
- a generic web scraper; or
- a PHP wrapper around every Lightpanda protocol surface.

The intended focus is token-efficient, agent-friendly access to content such as Markdown, semantic page structure, interactive elements, and structured data. These capabilities are not available in this bootstrap release.

## Requirements

- PHP 8.3 or later
- Laravel 12

## Public API

The V0.x surface is deliberately small:

- `Tonyputi\Traverse\Contracts\Factory` — `browser(?string $driver = null): Browser` (bound in the container)
- `Tonyputi\Traverse\Contracts\Browser` — `visit(string $url): Page`
- `Tonyputi\Traverse\Contracts\Page` — `markdown()`, `semanticTree()`, `interactiveElements()`, `structuredData()`
- `config/traverse.php` — `default`, `drivers.*` (publish via `php artisan vendor:publish --tag=traverse-config`)

Environment variables: `TRAVERSE_DRIVER`, `TRAVERSE_LIGHTPANDA_BINARY`.

There is no facade in V0.x; resolve `Contracts\Factory` via dependency injection. Custom drivers are registered with `BrowserManager::extend()`, as in Laravel's manager packages.

Before 1.0, breaking changes may land in minor releases and are always noted in the release notes.

## Development

Traverse is not published for installation yet. To contribute locally:

```bash
git clone git@github.com:tonyputi/traverse.git
cd traverse
composer install
composer ci
```

Available quality commands:

```bash
composer validate --strict
composer lint
composer test
composer format
```

Tests use [Pest](https://pestphp.com/) and Orchestra Testbench.

## Roadmap

The implementation plan is tracked in [issue #10](https://github.com/tonyputi/traverse/issues/10). The next milestone is the Lightpanda driver, built on the architecture recorded in [ADR 0001](docs/adr/0001-v0.1-architecture.md).

## Contributing

Contributions should be focused on a GitHub issue and submitted as one short-lived branch and one pull request. Read the [contributing guide](.github/CONTRIBUTING.md) and [AGENTS.md](AGENTS.md) for the contributor workflow and project constraints.

## License

Traverse is open-sourced software licensed under the [MIT license](LICENSE).
