<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Tests\Fixtures\Cache;

use Illuminate\Contracts\Cache\Store;

/**
 * A minimal in-memory store without lock support.
 */
final class InMemoryStore implements Store
{
    /**
     * @var array<string, mixed>
     */
    private array $items = [];

    public function get($key)
    {
        return $this->items[$key] ?? null;
    }

    public function many(array $keys)
    {
        return array_map(fn (string $key): mixed => $this->get($key), array_combine($keys, $keys) ?: []);
    }

    public function put($key, $value, $seconds)
    {
        $this->items[$key] = $value;

        return true;
    }

    public function putMany(array $values, $seconds)
    {
        foreach ($values as $key => $value) {
            $this->put($key, $value, $seconds);
        }

        return true;
    }

    public function increment($key, $value = 1)
    {
        $current = is_numeric($this->items[$key] ?? null) ? (int) $this->items[$key] : 0;
        $this->items[$key] = $current + $value;

        return $this->items[$key];
    }

    public function decrement($key, $value = 1)
    {
        return $this->increment($key, $value * -1);
    }

    public function forever($key, $value)
    {
        return $this->put($key, $value, 0);
    }

    public function forget($key)
    {
        unset($this->items[$key]);

        return true;
    }

    public function flush()
    {
        $this->items = [];

        return true;
    }

    public function getPrefix()
    {
        return '';
    }
}
