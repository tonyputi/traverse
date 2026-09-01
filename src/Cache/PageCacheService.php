<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Cache;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use InvalidArgumentException;
use Tonyputi\Traverse\Contracts\Factory;
use Tonyputi\Traverse\Contracts\Page;
use Tonyputi\Traverse\Contracts\PageCache;
use Tonyputi\Traverse\Contracts\SupportsPageCache;
use Tonyputi\Traverse\EventingBrowser;

final class PageCacheService implements PageCache
{
    public function __construct(
        private readonly CacheFactory $cache,
        private readonly ConfigRepository $config,
        private readonly Factory $factory,
    ) {}

    public function forget(string $url, ?string $driver = null): bool
    {
        try {
            return $this->storeFor($driver ?? $this->defaultDriver())?->forget($url) ?? false;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public function refresh(string $url, ?string $driver = null): Page
    {
        $resolved = $driver ?? $this->defaultDriver();

        $this->forget($url, $resolved);

        return $this->factory->browser($resolved)->visit($url);
    }

    private function storeFor(string $driver): ?CachedPageStore
    {
        $configuration = CacheConfiguration::fromArray($this->config->get('traverse.cache'));

        if (! $configuration->enabled) {
            return null;
        }

        $browser = $this->factory->browser($driver);
        $core = $browser instanceof EventingBrowser ? $browser->unwrap() : $browser;

        if (! $core instanceof SupportsPageCache) {
            return null;
        }

        return new CachedPageStore(
            $this->cache->store($configuration->store),
            $configuration,
            $driver,
            $core->cacheVersion(),
        );
    }

    private function defaultDriver(): string
    {
        $default = $this->config->get('traverse.default', 'lightpanda');

        if (! is_string($default) || $default === '') {
            throw new InvalidArgumentException('Traverse default driver configuration must be a non-empty string.');
        }

        return $default;
    }
}
