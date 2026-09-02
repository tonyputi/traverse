<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Contracts;

/**
 * Drivers that implement this capability may have their successful page
 * visits cached when the application enables Traverse's page cache.
 */
interface SupportsPageCache
{
    /**
     * A stable identifier for the driver's snapshot compatibility. Changing
     * this value invalidates previously cached pages for the driver.
     */
    public function cacheVersion(): string;
}
