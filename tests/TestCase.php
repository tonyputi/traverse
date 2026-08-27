<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Tonyputi\Traverse\TraverseServiceProvider;

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
