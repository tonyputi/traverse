<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Cache;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Throwable;
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

        try {
            $snapshot = $this->repository->get($this->entryKey($url));
        } catch (Throwable) {
            return null;
        }

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

        try {
            $store = $this->repository->getStore();
        } catch (Throwable) {
            return $this->visitAndStore($url, $visit);
        }

        if (! $store instanceof LockProvider) {
            return $this->visitAndStore($url, $visit);
        }

        try {
            $lock = $store->lock($this->lockKey($url), $this->configuration->lockSeconds);
        } catch (Throwable) {
            return $this->visitAndStore($url, $visit);
        }

        /** @var array{page: Page, cacheHit: bool}|null $served */
        $served = null;
        $callbackStarted = false;

        try {
            return $lock->block(
                $this->configuration->lockWaitSeconds,
                function () use ($url, $visit, &$served, &$callbackStarted): array {
                    $callbackStarted = true;

                    return $served = $this->visitAfterRecheck($url, $visit);
                },
            );
        } catch (LockTimeoutException) {
            return $served ?? $this->visitAndStore($url, $visit);
        } catch (Throwable $exception) {
            if ($served !== null) {
                return $served;
            }

            if ($callbackStarted) {
                throw $exception;
            }

            return $this->visitAndStore($url, $visit);
        }
    }

    public function forget(string $url): bool
    {
        if (PageCacheKey::hasUserInfo($url)) {
            return false;
        }

        try {
            return $this->repository->forget($this->entryKey($url));
        } catch (Throwable) {
            return false;
        }
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

        try {
            $this->repository->put($this->entryKey($url), $snapshot, $this->configuration->ttl);
        } catch (Throwable) {
            // Cache writes must not turn a successful visit into a failure.
        }
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
