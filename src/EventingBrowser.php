<?php

declare(strict_types=1);

namespace Tonyputi\Traverse;

use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;
use Throwable;
use Tonyputi\Traverse\Cache\CachedPageStore;
use Tonyputi\Traverse\Contracts\Browser;
use Tonyputi\Traverse\Contracts\Page;
use Tonyputi\Traverse\Contracts\TerminableBrowser;
use Tonyputi\Traverse\Events\VisitCompleted;
use Tonyputi\Traverse\Events\VisitFailed;
use Tonyputi\Traverse\Events\VisitStarted;

/**
 * @internal
 */
final class EventingBrowser implements Browser
{
    public function __construct(
        private readonly Browser $browser,
        private readonly string $driver,
        private readonly Dispatcher $events,
        private readonly ?CachedPageStore $cache = null,
    ) {}

    public function visit(string $url): Page
    {
        $invocationId = (string) Str::uuid7();
        $startedAt = new DateTimeImmutable;
        $startedAtNanoseconds = hrtime(true);

        $this->events->dispatch(new VisitStarted($invocationId, $url, $this->driver, $startedAt));

        try {
            $served = $this->cache?->visit($url, fn (): Page => $this->browser->visit($url))
                ?? ['page' => $this->browser->visit($url), 'cacheHit' => false];
        } catch (Throwable $exception) {
            $this->events->dispatch(new VisitFailed(
                $invocationId,
                $url,
                $this->driver,
                new DateTimeImmutable,
                $this->durationInMilliseconds($startedAtNanoseconds),
                $exception::class,
                false,
            ));

            throw $exception;
        }

        $this->events->dispatch(new VisitCompleted(
            $invocationId,
            $url,
            $this->driver,
            new DateTimeImmutable,
            $this->durationInMilliseconds($startedAtNanoseconds),
            $served['cacheHit'],
        ));

        return $served['page'];
    }

    public function terminate(): void
    {
        if ($this->browser instanceof TerminableBrowser) {
            $this->browser->terminate();
        }
    }

    /**
     * The undecorated driver this decorator wraps.
     *
     * @internal
     */
    public function unwrap(): Browser
    {
        return $this->browser;
    }

    private function durationInMilliseconds(int $startedAtNanoseconds): float
    {
        return (hrtime(true) - $startedAtNanoseconds) / 1_000_000;
    }
}
