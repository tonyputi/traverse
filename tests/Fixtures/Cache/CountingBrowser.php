<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Tests\Fixtures\Cache;

use RuntimeException;
use Tonyputi\Traverse\Contracts\Browser;
use Tonyputi\Traverse\Contracts\Page;
use Tonyputi\Traverse\Contracts\SupportsPageCache;

final class CountingBrowser implements Browser, SupportsPageCache
{
    public int $visits = 0;

    public function __construct(
        private readonly string $version = 'test/1',
        private readonly ?RuntimeException $exception = null,
    ) {}

    public function visit(string $url): Page
    {
        $this->visits++;

        if ($this->exception !== null) {
            throw $this->exception;
        }

        return new FakePage('# Counted #'.$this->visits);
    }

    public function cacheVersion(): string
    {
        return $this->version;
    }
}
