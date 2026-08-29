<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Contracts;

interface TerminableBrowser extends Browser
{
    public function terminate(): void;
}
