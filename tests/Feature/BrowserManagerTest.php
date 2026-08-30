<?php

declare(strict_types=1);

use Tonyputi\Traverse\BrowserManager;
use Tonyputi\Traverse\Contracts\Browser;
use Tonyputi\Traverse\Contracts\Factory;
use Tonyputi\Traverse\Contracts\Page;
use Tonyputi\Traverse\Contracts\TerminableBrowser;
use Tonyputi\Traverse\EventingBrowser;
use Tonyputi\Traverse\Exceptions\Lightpanda\BinaryNotFoundException;

it('binds the browser factory as a singleton', function (): void {
    $first = app(Factory::class);
    $second = app(Factory::class);

    expect($first)
        ->toBeInstanceOf(BrowserManager::class)
        ->toBe($second);
});

it('merges the default driver configuration', function (): void {
    expect(config('traverse.default'))->toBe('lightpanda')
        ->and(config('traverse.drivers.lightpanda.driver'))->toBe('lightpanda')
        ->and(config('traverse.drivers.lightpanda.timeout'))->toBe(30);
});

it('uses an application configuration override for the default driver', function (): void {
    $browser = new class implements Browser
    {
        public function visit(string $url): Page
        {
            return new class implements Page
            {
                public function markdown(): string
                {
                    return '';
                }

                public function semanticTree(): array
                {
                    return [];
                }

                public function interactiveElements(): array
                {
                    return [];
                }

                public function structuredData(): array
                {
                    return [];
                }
            };
        }
    };

    $factory = app(Factory::class);
    $factory->extend('custom', fn () => $browser);

    config()->set('traverse.default', 'custom');

    expect($factory->browser())->toBeInstanceOf(EventingBrowser::class);
});

it('terminates decorated terminable drivers', function (): void {
    $browser = new class implements TerminableBrowser
    {
        public bool $terminated = false;

        public function visit(string $url): Page
        {
            return new class implements Page
            {
                public function markdown(): string
                {
                    return '';
                }

                public function semanticTree(): array
                {
                    return [];
                }

                public function interactiveElements(): array
                {
                    return [];
                }

                public function structuredData(): array
                {
                    return [];
                }
            };
        }

        public function terminate(): void
        {
            $this->terminated = true;
        }
    };

    $factory = app(Factory::class);
    $factory->extend('terminable', fn () => $browser);
    $factory->browser('terminable');
    $factory->terminate();

    expect($browser->terminated)->toBeTrue();
});

it('rejects an unknown driver', function (): void {
    expect(fn () => app(Factory::class)->browser('unknown'))
        ->toThrow(InvalidArgumentException::class, 'Driver [unknown] not supported.');
});

it('decorates the Lightpanda driver with visit lifecycle events', function (): void {
    expect(app(Factory::class)->browser('lightpanda'))
        ->toBeInstanceOf(EventingBrowser::class);
});

it('requires an externally managed Lightpanda binary when visiting a page', function (): void {
    config()->set('traverse.drivers.lightpanda.binary', null);

    expect(fn () => app(Factory::class)->browser('lightpanda')->visit('https://example.com'))
        ->toThrow(BinaryNotFoundException::class, 'TRAVERSE_LIGHTPANDA_BINARY');
});
