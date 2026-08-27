<?php

declare(strict_types=1);

use Tonyputi\Traverse\BrowserManager;
use Tonyputi\Traverse\Contracts\Browser;
use Tonyputi\Traverse\Contracts\Factory;
use Tonyputi\Traverse\Contracts\Page;

it('binds the browser factory as a singleton', function (): void {
    $first = app(Factory::class);
    $second = app(Factory::class);

    expect($first)
        ->toBeInstanceOf(BrowserManager::class)
        ->toBe($second);
});

it('merges the default driver configuration', function (): void {
    expect(config('traverse'))
        ->toMatchArray([
            'driver' => 'lightpanda',
            'drivers' => [
                'lightpanda' => [
                    'driver' => 'lightpanda',
                    'binary' => null,
                    'timeout' => 30,
                ],
            ],
        ]);
});

it('resolves a custom driver configured as the default', function (): void {
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

    config()->set('traverse.driver', 'custom');

    expect($factory->browser())->toBe($browser);
});

it('rejects an unknown driver', function (): void {
    expect(fn () => app(Factory::class)->browser('unknown'))
        ->toThrow(InvalidArgumentException::class, 'Driver [unknown] not supported.');
});

it('fails fast until the Lightpanda driver is implemented', function (): void {
    expect(fn () => app(Factory::class)->browser('lightpanda'))
        ->toThrow(InvalidArgumentException::class, 'Driver [lightpanda] is not available yet.');
});
