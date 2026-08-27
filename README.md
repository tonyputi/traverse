# Traverse

**AI-native web browsing for Laravel.**

Traverse will give Laravel applications and AI agents efficient, structured access to the web without relying on an AI provider's built-in browsing features.

## Status

Traverse is in early development. This repository currently provides the Laravel package foundation only; browser operations and public browsing APIs have not been implemented yet.

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

The V0.1 implementation plan is tracked in [issue #10](https://github.com/tonyputi/traverse/issues/10). The next work defines the browser architecture and aligns this foundation with Laravel package conventions before a Lightpanda driver is implemented.

## Contributing

Contributions should be focused on a GitHub issue and submitted as one short-lived branch and one pull request. Read the [contributing guide](.github/CONTRIBUTING.md) and [AGENTS.md](AGENTS.md) for the contributor workflow and project constraints.

## License

Traverse is open-sourced software licensed under the [MIT license](LICENSE).
