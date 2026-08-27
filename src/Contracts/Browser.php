<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Contracts;

interface Browser
{
    public function visit(string $url): Page;
}
