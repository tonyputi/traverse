<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Cache;

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
        if (PageCacheKey::hasUserInfo($url)) {
            return null;
        }

        $snapshot = $this->repository->get($this->entryKey($url));

        if (! is_string($snapshot)) {
            return null;
        }

        return CachedPage::restore($snapshot);
    }

    /**
     * @param  (callable(): Page)  $visit
     * @return array{page: Page, cacheHit: bool}
     */
    public function visit(string $url, callable $visit): array
    {
        if (PageCacheKey::hasUserInfo($url)) {
            return $this->visitWithoutCache($visit);
        }

        $cached = $this->get($url);

        if ($cached !== null) {
            return ['page' => $cached, 'cacheHit' => true];
        }

        $store = $this->repository->getStore();

        if (! $store instanceof LockProvider) {
            return $this->visitAndStore($url, $visit);
        }

        $lock = $store->lock($this->lockKey($url), $this->configuration->lockSeconds);

        try {
            return $lock->block(
                $this->configuration->lockWaitSeconds,
                fn (): array => $this->visitAfterRecheck($url, $visit),
            );
        } catch (LockTimeoutException) {
            return $this->visitAndStore($url, $visit);
        }
    }

    public function forget(string $url): bool
    {
        if (PageCacheKey::hasUserInfo($url)) {
            return false;
        }

        return $this->repository->forget($this->entryKey($url));
    }

    /**
     * @param  (callable(): Page)  $visit
     * @return array{page: Page, cacheHit: bool}
     */
    private function visitAfterRecheck(string $url, callable $visit): array
    {
        $cached = $this->get($url);

        if ($cached !== null) {
            return ['page' => $cached, 'cacheHit' => true];
        }

        return $this->visitAndStore($url, $visit);
    }

    /**
     * @param  (callable(): Page)  $visit
     * @return array{page: Page, cacheHit: false}
     */
    private function visitAndStore(string $url, callable $visit): array
    {
        $page = $visit();
        $this->put($url, $page);

        return ['page' => $page, 'cacheHit' => false];
    }

    /**
     * @param  (callable(): Page)  $visit
     * @return array{page: Page, cacheHit: false}
     */
    private function visitWithoutCache(callable $visit): array
    {
        return ['page' => $visit(), 'cacheHit' => false];
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
