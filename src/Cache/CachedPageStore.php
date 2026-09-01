<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Cache;

use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Tonyputi\Traverse\Contracts\Page;

/**
 * The per-driver page snapshot store. Maps domain input to internal cache
 * keys and delegates persistence to the configured Laravel cache store.
 *
 * @internal
 */
final readonly class CachedPageStore
{
    public function __construct(
        private Repository $repository,
        private CacheConfiguration $configuration,
        private string $driver,
        private string $cacheVersion,
    ) {}

    public function get(string $url): ?Page
    {
        $snapshot = $this->repository->get($this->entryKey($url));

        if (! is_string($snapshot)) {
            return null;
        }

        return CachedPage::restore($snapshot);
    }

    /**
     * Serve a cached page or perform the visit at most once, protected by an
     * atomic lock when the underlying store supports locks. Stores without
     * lock support still work, without stampede protection.
     *
     * @param  (callable(): Page)  $visit
     */
    public function visit(string $url, callable $visit): ServedPage
    {
        $cached = $this->get($url);

        if ($cached !== null) {
            return new ServedPage($cached, true);
        }

        $store = $this->repository->getStore();

        if (! $store instanceof LockProvider) {
            return $this->visitAndStore($url, $visit);
        }

        $lock = $store->lock($this->lockKey($url), $this->configuration->lockSeconds);

        try {
            return $lock->block(
                $this->configuration->lockWaitSeconds,
                fn (): ServedPage => $this->visitAfterRecheck($url, $visit),
            );
        } catch (LockTimeoutException) {
            return $this->visitAndStore($url, $visit);
        }
    }

    public function forget(string $url): bool
    {
        return $this->repository->forget($this->entryKey($url));
    }

    /**
     * @param  (callable(): Page)  $visit
     */
    private function visitAfterRecheck(string $url, callable $visit): ServedPage
    {
        $cached = $this->get($url);

        if ($cached !== null) {
            return new ServedPage($cached, true);
        }

        return $this->visitAndStore($url, $visit);
    }

    /**
     * @param  (callable(): Page)  $visit
     */
    private function visitAndStore(string $url, callable $visit): ServedPage
    {
        $visit = Closure::fromCallable($visit);
        $page = $visit();
        $this->put($url, $page);

        return new ServedPage($page, false);
    }

    private function put(string $url, Page $page): void
    {
        $snapshot = CachedPage::capture($page);

        if ($snapshot === null) {
            return;
        }

        $this->repository->put($this->entryKey($url), $snapshot, $this->configuration->ttl);
    }

    private function entryKey(string $url): string
    {
        return PageCacheKey::entry($this->configuration->prefix, $this->driver, $this->cacheVersion, $url);
    }

    private function lockKey(string $url): string
    {
        return PageCacheKey::lock($this->configuration->prefix, $this->driver, $this->cacheVersion, $url);
    }
}
