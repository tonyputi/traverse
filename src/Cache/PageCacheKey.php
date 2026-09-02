<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Cache;

/**
 * Deterministic internal cache keys. The URL is conservatively normalized and
 * digested, so raw URLs never appear in the cache store.
 *
 * @internal
 */
final class PageCacheKey
{
    public static function hasUserInfo(string $url): bool
    {
        $parts = parse_url(trim($url));

        return is_array($parts) && (isset($parts['user']) || isset($parts['pass']));
    }

    public static function entry(string $prefix, string $driver, string $cacheVersion, string $url): string
    {
        return $prefix.':'.self::digest($driver, $cacheVersion, $url);
    }

    public static function lock(string $prefix, string $driver, string $cacheVersion, string $url): string
    {
        return $prefix.':lock:'.self::digest($driver, $cacheVersion, $url);
    }

    private static function digest(string $driver, string $cacheVersion, string $url): string
    {
        return hash('sha256', $driver."\0".$cacheVersion."\0".self::normalize($url));
    }

    private static function normalize(string $url): string
    {
        $trimmed = trim($url);
        $parts = parse_url($trimmed);

        if ($parts === false || ! is_string($parts['scheme'] ?? null) || ! is_string($parts['host'] ?? null) || $parts['host'] === '') {
            return $trimmed;
        }

        // Authenticated URLs are not normalized: distinct credentials must
        // never collide onto one cache key.
        if (isset($parts['user']) || isset($parts['pass'])) {
            return $trimmed;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $port = $parts['port'] ?? null;
        $defaultPort = ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443);
        $path = is_string($parts['path'] ?? null) ? $parts['path'] : '';
        $query = is_string($parts['query'] ?? null) ? '?'.$parts['query'] : '';

        return $scheme.'://'.$host.(is_int($port) && ! $defaultPort ? ':'.$port : '').$path.$query;
    }
}
