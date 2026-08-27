<?php

declare(strict_types=1);

namespace TonyPuti\Traverse\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use TonyPuti\Traverse\TraverseServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            TraverseServiceProvider::class,
        ];
    }
}
