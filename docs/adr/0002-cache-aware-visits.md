# ADR 0002: Opt-in cache-aware page visits

## Status

Accepted — 2026-08-31.

## Context

Every synchronous `Browser::visit()` starts Lightpanda, navigates, and extracts the four page primitives. For public pages that change rarely, repeating that pipeline for identical visits wastes process startup, navigation time, and agent tokens.

Laravel applications already operate a cache layer with configured stores, TTLs, and atomic locks. Traverse must reuse it without becoming a second storage abstraction, and without changing the synchronous `visit(string $url): Page` contract or the visit lifecycle guarantees established in ADR 0001.

## Decision

### Capability-gated caching

Caching is disabled by default and only applies to drivers that explicitly implement the capability contract:

```php
interface SupportsPageCache
{
    public function cacheVersion(): string;
}
```

`cacheVersion()` represents snapshot compatibility, not the runtime binary version; the Lightpanda driver returns the constant `lightpanda-0.3`, bumped only when cached snapshots must be invalidated across releases. Drivers that do not implement the capability are never cached, so Traverse never assumes a driver can produce comparable snapshots.

### Snapshot storage

Successful pages are stored as validated JSON snapshots of the four read primitives (`markdown`, `semanticTree`, `interactiveElements`, `structuredData`) through an internal `CachedPage` value object that also implements `Contracts\Page` for reconstruction. Capture failures mean the page is not cached; restore failures are treated as cache misses. Nothing else is persisted: no live browser, process, or CDP objects, no exceptions, cookies, headers, credentials, or diagnostics.

Keys are internal. They combine the configured prefix with a SHA-256 digest of the resolved driver name, the driver snapshot version, and a conservatively normalized URL (case-insensitive scheme and host, default ports and fragments removed, query preserved verbatim). URLs containing userinfo bypass the cache. Raw URLs never appear in the cache store, and consumers use the bound `Contracts\PageCache` service (`forget()` / `refresh()`) instead of cache keys.

### Visit integration

The internal `EventingBrowser` decorator gains an optional `CachedPageStore` collaborator created by `BrowserManager` when caching is enabled and the driver is cache-capable. The full visit flow stays in one place: `VisitStarted` dispatches, then either a cached snapshot or at most one real visit produces a page, then exactly one terminal event dispatches. `VisitCompleted` and `VisitFailed` carry a scalar `cacheHit` boolean; failures always report `false`.

On a miss with a store implementing `Illuminate\Contracts\Cache\LockProvider`, Traverse acquires an atomic lock, re-checks the cache after acquiring it, then visits and stores. `lock_wait_seconds` exhaustion degrades to an unlocked, best-effort visit instead of failing the caller. Stores without lock support work normally without stampede protection. Runtime cache-store failures likewise degrade to a miss or skipped write; invalid configuration still fails explicitly.

Configuration lives under `traverse.cache` (`enabled`, `store`, `ttl`, `prefix`, `lock_seconds`, `lock_wait_seconds`) and is validated when enabled; missing keys fall back to safe defaults because `mergeConfigFrom` only merges top-level keys. `illuminate/cache` becomes a direct dependency.

## Consequences

- Issue #20 can build queued visits on a durable, JSON-round-trippable result store with deterministic idempotency semantics.
- Applications keep full ownership of store selection, TTL policy, and shared-cache risk for the URLs they visit.
- Traverse does not interpret HTTP freshness headers or include request context in cache keys. Applications must enable caching only for public, identical-request pages; URL userinfo is the one authentication signal Traverse can detect and bypasses.

## Sources

- [Laravel cache](https://laravel.com/docs/12.x/cache)
- [Laravel atomic locks](https://laravel.com/docs/12.x/cache#atomic-locks)
