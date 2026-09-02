<?php

declare(strict_types=1);

use Illuminate\Cache\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tonyputi\Traverse\Cache\CachedPage;
use Tonyputi\Traverse\Cache\PageCacheKey;
use Tonyputi\Traverse\Contracts\Browser;
use Tonyputi\Traverse\Contracts\Factory;
use Tonyputi\Traverse\Contracts\Page;
use Tonyputi\Traverse\Contracts\PageCache;
use Tonyputi\Traverse\Contracts\SupportsPageCache;
use Tonyputi\Traverse\EventingBrowser;
use Tonyputi\Traverse\Events\VisitCompleted;
use Tonyputi\Traverse\Events\VisitFailed;
use Tonyputi\Traverse\Events\VisitStarted;
use Tonyputi\Traverse\Lightpanda\Browser as LightpandaBrowser;
use Tonyputi\Traverse\Lightpanda\Process;
use Tonyputi\Traverse\Tests\Fixtures\Cache\CountingBrowser;
use Tonyputi\Traverse\Tests\Fixtures\Cache\FakePage;
use Tonyputi\Traverse\Tests\Fixtures\Cache\InMemoryStore;
use Tonyputi\Traverse\Tests\Fixtures\Cache\SeededOnSecondGetStore;

const CACHE_URL = 'https://example.test/docs';

function enablePageCache(array $overrides = []): void
{
    config()->set('traverse.cache', array_merge([
        'enabled' => true,
        'store' => 'array',
    ], $overrides));
}

function cachedEntryKey(string $driver = 'cached', string $version = 'test/1', string $url = CACHE_URL): string
{
    return PageCacheKey::entry('traverse:pages:v1', $driver, $version, $url);
}

function cachedBrowser(CountingBrowser $browser): Factory
{
    $factory = app(Factory::class);
    $factory->extend('cached', fn (): CountingBrowser => $browser);

    return $factory;
}

afterEach(function (): void {
    Carbon::setTestNow();
});

it('does not cache visits when the cache is disabled by default', function (): void {
    config()->set('traverse.cache', ['store' => 'array']);

    $browser = new CountingBrowser;
    $factory = cachedBrowser($browser);

    $factory->browser('cached')->visit(CACHE_URL);
    $factory->browser('cached')->visit(CACHE_URL);

    expect($browser->visits)->toBe(2)
        ->and(Cache::store('array')->get(cachedEntryKey()))->toBeNull()
        ->and(app(PageCache::class)->forget(CACHE_URL))->toBeFalse();
});

it('caches a successful visit and serves later visits from the snapshot', function (): void {
    enablePageCache();

    $browser = new CountingBrowser;
    $factory = cachedBrowser($browser);

    $first = $factory->browser('cached')->visit(CACHE_URL);
    $second = $factory->browser('cached')->visit(CACHE_URL);

    expect($browser->visits)->toBe(1)
        ->and($second->markdown())->toBe($first->markdown())
        ->and($second->semanticTree())->toBe($first->semanticTree())
        ->and($second->interactiveElements())->toBe($first->interactiveElements())
        ->and($second->structuredData())->toBe($first->structuredData());
});

it('stores validated JSON snapshots of all four primitives', function (): void {
    enablePageCache();

    cachedBrowser(new CountingBrowser)->browser('cached')->visit(CACHE_URL);

    $raw = Cache::store('array')->get(cachedEntryKey());

    expect($raw)->toBeString();

    $decoded = json_decode((string) $raw, true, flags: JSON_THROW_ON_ERROR);

    expect($decoded)->toHaveKeys(['markdown', 'semanticTree', 'interactiveElements', 'structuredData'])
        ->and($decoded['markdown'])->toBe('# Counted #1')
        ->and($decoded['semanticTree'])->toBe(['role' => 'document'])
        ->and($decoded['interactiveElements'])->toBe([['role' => 'link', 'name' => 'Docs']])
        ->and($decoded['structuredData'])->toBe(['jsonLd' => ['@type' => 'Article']]);

    $restored = CachedPage::restore((string) $raw);

    expect($restored)->toBeInstanceOf(CachedPage::class)
        ->markdown()->toBe('# Counted #1')
        ->and(CachedPage::restore('not-json'))->toBeNull()
        ->and(CachedPage::restore('{"markdown":12}'))->toBeNull();
});

it('separates entries by url, driver, and cache version', function (): void {
    enablePageCache();

    $browser = new CountingBrowser;
    $factory = app(Factory::class);
    $factory->extend('one', fn (): CountingBrowser => $browser);
    $factory->extend('two', fn (): CountingBrowser => $browser);

    $factory->browser('one')->visit(CACHE_URL);
    $factory->browser('two')->visit(CACHE_URL);
    $factory->browser('one')->visit(CACHE_URL);

    expect($browser->visits)->toBe(2)
        ->and(Cache::store('array')->get(cachedEntryKey(driver: 'one')))->toBeString()
        ->and(Cache::store('array')->get(cachedEntryKey(driver: 'two')))->toBeString()
        ->and(cachedEntryKey(driver: 'one'))
        ->not->toBe(cachedEntryKey(driver: 'two'))
        ->and(cachedEntryKey(version: 'test/2'))
        ->not->toBe(cachedEntryKey(version: 'test/1'));
});

it('normalizes urls before digesting them', function (): void {
    $entry = fn (string $url): string => cachedEntryKey(url: $url);

    expect($entry('https://EXAMPLE.test:443/docs'))
        ->toBe($entry('https://example.test/docs'))
        ->and($entry(' https://example.test/docs#section '))
        ->toBe($entry('https://example.test/docs'))
        ->and($entry('https://example.test/docs?b=2&a=1'))
        ->not->toBe($entry('https://example.test/docs?a=1&b=2'))
        ->and($entry('http://example.test:8080/docs'))
        ->toBe($entry('HTTP://example.test:8080/docs'))
        ->and($entry('http://alice:s3cret@example.test/docs'))
        ->not->toBe($entry('http://bob:other@example.test/docs'))
        ->and(str_contains($entry(CACHE_URL), 'example.test'))->toBeFalse();
});

it('bypasses cache for urls containing userinfo', function (): void {
    enablePageCache();

    $browser = new CountingBrowser;
    $factory = cachedBrowser($browser);
    $url = 'https://alice:secret@example.test/docs';

    $factory->browser('cached')->visit($url);
    $factory->browser('cached')->visit($url);

    expect($browser->visits)->toBe(2)
        ->and(app(PageCache::class)->forget($url, 'cached'))->toBeFalse();
});

it('respects the configured ttl', function (): void {
    enablePageCache(['ttl' => 120]);

    $browser = new CountingBrowser;
    $factory = cachedBrowser($browser);

    $factory->browser('cached')->visit(CACHE_URL);

    Carbon::setTestNow(Carbon::now()->addSeconds(130));

    $factory->browser('cached')->visit(CACHE_URL);

    expect($browser->visits)->toBe(2);
});

it('forgets a cached page without exposing keys', function (): void {
    enablePageCache();
    config()->set('traverse.default', 'cached');

    $browser = new CountingBrowser;
    $factory = cachedBrowser($browser);

    $factory->browser('cached')->visit(CACHE_URL);

    expect(app(PageCache::class)->forget(CACHE_URL))->toBeTrue();

    $factory->browser('cached')->visit(CACHE_URL);

    expect($browser->visits)->toBe(2);
});

it('forgets a cached page for an explicit driver', function (): void {
    enablePageCache();

    $browser = new CountingBrowser;
    $factory = cachedBrowser($browser);

    $factory->browser('cached')->visit(CACHE_URL);

    expect(app(PageCache::class)->forget(CACHE_URL, 'cached'))->toBeTrue()
        ->and(fn (): bool => app(PageCache::class)->forget(CACHE_URL, 'other'))
        ->toThrow(InvalidArgumentException::class, 'Driver [other] not supported.');
});

it('refreshes a page and caches the fresh snapshot', function (): void {
    enablePageCache();
    config()->set('traverse.default', 'cached');

    $browser = new CountingBrowser;
    $factory = cachedBrowser($browser);

    $stale = $factory->browser('cached')->visit(CACHE_URL);
    $fresh = app(PageCache::class)->refresh(CACHE_URL);

    expect($fresh->markdown())->toBe('# Counted #2')
        ->and($fresh->markdown())->not->toBe($stale->markdown());

    $served = $factory->browser('cached')->visit(CACHE_URL);

    expect($browser->visits)->toBe(2)
        ->and($served->markdown())->toBe('# Counted #2');
});

it('never caches failed visits', function (): void {
    Event::fake();

    enablePageCache();

    $exception = new RuntimeException('The driver failed.');
    $browser = new CountingBrowser(exception: $exception);
    $factory = cachedBrowser($browser);

    expect(fn (): mixed => $factory->browser('cached')->visit(CACHE_URL))
        ->toThrow($exception);

    expect(Cache::store('array')->get(cachedEntryKey()))->toBeNull();

    $failed = Event::dispatched(VisitFailed::class)->sole()[0];

    expect($failed->cacheHit)->toBeFalse();
});

it('ignores drivers without the cache capability', function (): void {
    enablePageCache();

    $page = new FakePage('# Plain');
    $browser = new class($page) implements Browser
    {
        public int $visits = 0;

        public function __construct(private readonly Page $page) {}

        public function visit(string $url): Page
        {
            $this->visits++;

            return $this->page;
        }
    };

    $factory = app(Factory::class);
    $factory->extend('plain', fn () => $browser);

    $factory->browser('plain')->visit(CACHE_URL);
    $factory->browser('plain')->visit(CACHE_URL);

    expect($browser->visits)->toBe(2);
});

it('treats corrupt snapshots as misses', function (): void {
    enablePageCache();

    $browser = new CountingBrowser;
    $factory = cachedBrowser($browser);

    Cache::store('array')->put(cachedEntryKey(), 'not-json-at-all');

    $page = $factory->browser('cached')->visit(CACHE_URL);

    expect($browser->visits)->toBe(1)
        ->and($page->markdown())->toBe('# Counted #1')
        ->and(Cache::store('array')->get(cachedEntryKey()))
        ->toBe(CachedPage::capture($page));
});

it('rechecks the cache after acquiring a lock', function (): void {
    enablePageCache();

    $seed = CachedPage::capture(new FakePage('# Seeded by another worker')) ?? '';
    $store = new SeededOnSecondGetStore($seed);
    Cache::extend('seeded', fn (): Repository => new Repository($store));
    config()->set('cache.stores.seeded', ['driver' => 'seeded']);
    enablePageCache(['store' => 'seeded']);

    $browser = new CountingBrowser;
    $factory = cachedBrowser($browser);

    $page = $factory->browser('cached')->visit(CACHE_URL);

    expect($browser->visits)->toBe(0)
        ->and($page->markdown())->toBe('# Seeded by another worker');
});

it('falls back to an unlocked visit when the lock times out', function (): void {
    enablePageCache(['lock_wait_seconds' => 1]);

    $store = Cache::store('array');
    $lock = $store->lock(PageCacheKey::lock('traverse:pages:v1', 'cached', 'test/1', CACHE_URL), 10);

    expect($lock->get())->toBeTrue();

    try {
        $browser = new CountingBrowser;
        $factory = cachedBrowser($browser);

        $factory->browser('cached')->visit(CACHE_URL);

        expect($browser->visits)->toBe(1);
    } finally {
        $lock->release();
    }
});

it('works with stores without lock support', function (): void {
    Cache::extend('lockless', fn (): Repository => new Repository(new InMemoryStore));
    config()->set('cache.stores.lockless', ['driver' => 'lockless']);
    enablePageCache(['store' => 'lockless']);

    $browser = new CountingBrowser;
    $factory = cachedBrowser($browser);

    $factory->browser('cached')->visit(CACHE_URL);
    $factory->browser('cached')->visit(CACHE_URL);

    expect($browser->visits)->toBe(1);
});

it('dispatches lifecycle events with cache hit metadata', function (): void {
    Event::fake();

    enablePageCache();

    $browser = new CountingBrowser;
    $factory = cachedBrowser($browser);

    $factory->browser('cached')->visit(CACHE_URL);
    $factory->browser('cached')->visit(CACHE_URL);

    Event::assertDispatchedTimes(VisitStarted::class, 2);
    Event::assertDispatchedTimes(VisitCompleted::class, 2);

    $completed = Event::dispatched(VisitCompleted::class);

    expect($completed[0][0]->cacheHit)->toBeFalse()
        ->and($completed[1][0]->cacheHit)->toBeTrue()
        ->and($completed[0][0]->invocationId)->not->toBe($completed[1][0]->invocationId)
        ->and(get_object_vars($completed[1][0]))->not->toHaveKey('page');
});

it('validates cache configuration', function (): void {
    enablePageCache(['ttl' => -1]);
    cachedBrowser(new CountingBrowser);

    expect(fn (): Browser => app(Factory::class)->browser('cached'))
        ->toThrow(InvalidArgumentException::class, 'Traverse cache [ttl] configuration must be a positive integer.')
        ->and(fn (): bool => app(PageCache::class)->forget(CACHE_URL, 'cached'))
        ->toThrow(InvalidArgumentException::class, 'Traverse cache [ttl] configuration must be a positive integer.');
});

it('accepts partial cache configuration with safe defaults', function (): void {
    config()->set('traverse.cache', ['enabled' => true, 'store' => 'array']);

    $browser = new CountingBrowser;
    $factory = cachedBrowser($browser);

    $factory->browser('cached')->visit(CACHE_URL);
    $factory->browser('cached')->visit(CACHE_URL);

    expect($browser->visits)->toBe(1)
        ->and(Cache::store('array')->get(cachedEntryKey()))->toBeString();
});

it('marks the lightpanda driver as cache capable', function (): void {
    expect(LightpandaBrowser::class)->toImplement(SupportsPageCache::class)
        ->and(app(Factory::class)->browser('lightpanda'))
        ->toBeInstanceOf(EventingBrowser::class)
        ->and((new LightpandaBrowser(new Process(null, 30, app(Illuminate\Process\Factory::class))))->cacheVersion())
        ->toBe('lightpanda-0.3');
});
