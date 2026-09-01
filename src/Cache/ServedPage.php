<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Cache;

use Tonyputi\Traverse\Contracts\Page;

/**
 * @internal
 */
final readonly class ServedPage
{
    public function __construct(
        public Page $page,
        public bool $fromCache,
    ) {}
}
