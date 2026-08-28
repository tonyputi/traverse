<?php

declare(strict_types=1);

namespace Tonyputi\Traverse;

use Illuminate\Support\ServiceProvider;
use Tonyputi\Traverse\Contracts\Factory;

final class TraverseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/traverse.php', 'traverse');

        $this->app->singleton(Factory::class, BrowserManager::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/traverse.php' => config_path('traverse.php'),
            ], ['traverse', 'traverse-config']);
        }
    }
}
