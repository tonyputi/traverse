<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Tests\Fixtures\Cache;

use Illuminate\Cache\ArrayStore;

/**
 * An ArrayStore that stores a seed value on its second read of any key,
 * simulating another process completing the visit while a lock is held.
 */
final class SeededOnSecondGetStore extends ArrayStore
{
    public int $reads = 0;

    public function __construct(private readonly string $seed) {}

    public function get($key)
    {
        $this->reads++;

        if ($this->reads === 2) {
            $this->put($key, $this->seed, 0);
        }

        return parent::get($key);
    }
}
