<?php

declare(strict_types=1);

use Tonyputi\Traverse\TraverseServiceProvider;

it('boots its service provider in Laravel', function (): void {
    expect(app()->getProvider(TraverseServiceProvider::class))->not->toBeNull();
});

it('declares its service provider for Laravel package discovery', function (): void {
    $composer = json_decode(
        file_get_contents(dirname(__DIR__, 2).'/composer.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($composer['extra']['laravel']['providers'])->toContain(TraverseServiceProvider::class);
});
