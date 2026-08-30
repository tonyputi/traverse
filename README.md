# Traverse

**AI-native web browsing for Laravel.**

Traverse will give Laravel applications and AI agents efficient, structured access to the web without relying on an AI provider's built-in browsing features.

## Status

Traverse is in early development. It currently provides a Lightpanda driver for the package's read-only, AI-native page primitives.

## Vision

The first planned driver is [Lightpanda](https://lightpanda.io/). Traverse will expose its machine-native browsing capabilities through a focused Laravel abstraction, while leaving room for future drivers that provide equivalent native capabilities.

Traverse is not:

- a replacement for Laravel Dusk, Playwright, or Puppeteer;
- a general-purpose browser automation framework;
- a generic web scraper; or
- a PHP wrapper around every Lightpanda protocol surface.

The intended focus is token-efficient, agent-friendly access to content such as Markdown, semantic page structure, interactive elements, and structured data. These capabilities are not available in this bootstrap release.

## Requirements

- PHP 8.3 or later (CI-tested on PHP 8.3 and 8.4)
- Laravel 12

## Public API

The V0.x surface is deliberately small:

- `Tonyputi\Traverse\Contracts\Factory` — `browser(?string $driver = null): Browser` (bound in the container)
- `Tonyputi\Traverse\Contracts\Browser` — `visit(string $url): Page`
- `Tonyputi\Traverse\Contracts\Page` — `markdown()`, `semanticTree()`, `interactiveElements()`, `structuredData()`
- `Tonyputi\Traverse\Events\VisitStarted`, `VisitCompleted`, and `VisitFailed` — factory-resolved visit lifecycle events
- `Tonyputi\Traverse\Ai\ReadPageTool` — optional Laravel AI `traverse-read` tool
- `config/traverse.php` — `default`, `drivers.*` (publish via `php artisan vendor:publish --tag=traverse-config`)

Configure the externally managed Lightpanda executable before visiting a page. Traverse supports Lightpanda `>= 0.3.7` and `< 0.4.0`:

```dotenv
TRAVERSE_LIGHTPANDA_BINARY=/usr/local/bin/lightpanda
```

```php
use Tonyputi\Traverse\Contracts\Factory;

$page = app(Factory::class)->browser()->visit('https://example.com');

$markdown = $page->markdown();
```

Traverse starts Lightpanda locally on an ephemeral loopback port and terminates it with the Laravel application. It does not install, download, or redistribute Lightpanda; deploy and configure the executable yourself.

### Visit lifecycle events

Every visit made through `Contracts\Factory` dispatches `VisitStarted` immediately before the driver is called, then either `VisitCompleted` after it returns a page or `VisitFailed` before its exception is rethrown. The events share an `invocationId` and include the requested URL, resolved driver, timestamp, and terminal duration in milliseconds. `VisitFailed` includes only the exception class, not the exception message or driver diagnostics.

```php
use Illuminate\Support\Facades\Event;
use Tonyputi\Traverse\Events\VisitCompleted;

Event::listen(VisitCompleted::class, function (VisitCompleted $visit): void {
    logger()->info('Traverse visit completed.', [
        'invocation_id' => $visit->invocationId,
        'url' => $visit->url,
        'driver' => $visit->driver,
        'duration_ms' => $visit->durationInMilliseconds,
    ]);
});
```

Completed events do not contain a `Page`, Markdown, semantic data, cookies, headers, or process objects. Traverse does not broadcast these events, select a channel, or configure a queue. Applications that need real-time updates should map only the appropriate event metadata to their own authorized broadcast event. Use a queued listener, including `ShouldQueueAfterCommit` where appropriate, when event work must not run during the visit.

### Laravel AI tools

Laravel AI is optional. Install it only when an application needs to expose Traverse to a tool-capable agent:

```bash
composer require laravel/ai
```

Attach Traverse's `traverse-read` tool to an agent that implements Laravel AI's `HasTools` contract:

```php
use Tonyputi\Traverse\Ai\ReadPageTool;

public function tools(): iterable
{
    return [app(ReadPageTool::class)];
}
```

`traverse-read` accepts an absolute HTTP(S) `url`, an optional `format` (`markdown`, `semantic-tree`, `interactive-elements`, or `structured-data`), and an optional Markdown-only `max_characters` limit. Markdown defaults to 12,000 characters and reports whether it was truncated.

Attaching `traverse-read` grants the agent the same outbound network reachability as the application. It validates URL syntax but does not provide an SSRF guarantee across DNS resolution or redirects. Apply egress controls appropriate to your deployment before attaching it to an untrusted agent.

Environment variables: `TRAVERSE_DRIVER`, `TRAVERSE_LIGHTPANDA_BINARY`.

There is no facade in V0.x; resolve `Contracts\Factory` via dependency injection. Custom drivers are registered with `BrowserManager::extend()`, as in Laravel's manager packages.

Before 1.0, breaking changes may land in minor releases and are always noted in the release notes.

## Lightpanda licensing

Lightpanda is an external dependency licensed by its authors under AGPL-3.0-only. Traverse is MIT-licensed and does not distribute Lightpanda. You are responsible for obtaining, deploying, and determining the appropriate license for the executable used by your application.

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
composer analyse
composer test
composer format
```

Tests use [Pest](https://pestphp.com/) and Orchestra Testbench. Static analysis uses [Larastan](https://larastan.com/).

## Roadmap

The implementation plan is tracked in [issue #10](https://github.com/tonyputi/traverse/issues/10). The next milestone is the Lightpanda driver, built on the architecture recorded in [ADR 0001](docs/adr/0001-v0.1-architecture.md).

## Contributing

Contributions should be focused on a GitHub issue and submitted as one short-lived branch and one pull request. Read the [contributing guide](.github/CONTRIBUTING.md) and [AGENTS.md](AGENTS.md) for the contributor workflow and project constraints.

## License

Traverse is open-sourced software licensed under the [MIT license](LICENSE).
