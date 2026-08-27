<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Contracts;

interface Factory
{
    public function browser(?string $driver = null): Browser;
}
