<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;

it('registers the configuration with the traverse publish tags', function (): void {
    $traversePaths = ServiceProvider::$publishGroups['traverse'] ?? null;
    $configPaths = ServiceProvider::$publishGroups['traverse-config'] ?? null;

    expect($traversePaths)
        ->toBe($configPaths)
        ->toHaveCount(1);

    $source = array_key_first($traversePaths);

    expect(realpath($source))
        ->toBe(realpath(dirname(__DIR__, 2).'/config/traverse.php'))
        ->and($traversePaths[$source])
        ->toBe(config_path('traverse.php'));
});
