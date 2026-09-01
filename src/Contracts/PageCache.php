<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Contracts;

use Tonyputi\Traverse\Exceptions\Lightpanda\LightpandaException;

interface PageCache
{
    /**
     * Remove the cached page for a URL. Applications never handle cache keys.
     *
     * Returns false when caching is disabled, the driver does not support
     * caching, or the store reports nothing to remove.
     */
    public function forget(string $url, ?string $driver = null): bool;

    /**
     * Remove the cached page for a URL and return a fresh page obtained
     * through the normal browser flow.
     *
     * @throws LightpandaException
     */
    public function refresh(string $url, ?string $driver = null): Page;
}
