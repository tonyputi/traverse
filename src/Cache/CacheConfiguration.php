<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Cache;

use InvalidArgumentException;

/**
 * @internal
 */
final readonly class CacheConfiguration
{
    public const DEFAULT_TTL = 300;

    public const DEFAULT_PREFIX = 'traverse:pages:v1';

    public const DEFAULT_LOCK_SECONDS = 60;

    public const DEFAULT_LOCK_WAIT_SECONDS = 60;

    public function __construct(
        public bool $enabled,
        public ?string $store,
        public int $ttl,
        public string $prefix,
        public int $lockSeconds,
        public int $lockWaitSeconds,
    ) {}

    public static function fromArray(mixed $config): self
    {
        if ($config === null) {
            $config = [];
        }

        if (! is_array($config)) {
            throw new InvalidArgumentException('Traverse cache configuration must be an array.');
        }

        $enabled = self::boolean($config['enabled'] ?? false, 'enabled');

        if (! $enabled) {
            return new self(false, null, self::DEFAULT_TTL, self::DEFAULT_PREFIX, self::DEFAULT_LOCK_SECONDS, self::DEFAULT_LOCK_WAIT_SECONDS);
        }

        $store = $config['store'] ?? null;

        if ($store !== null && ! is_string($store)) {
            throw new InvalidArgumentException('Traverse cache store configuration must be a string or null.');
        }

        return new self(
            true,
            $store,
            self::positiveInteger($config['ttl'] ?? self::DEFAULT_TTL, 'ttl'),
            self::prefix($config['prefix'] ?? self::DEFAULT_PREFIX),
            self::positiveInteger($config['lock_seconds'] ?? self::DEFAULT_LOCK_SECONDS, 'lock_seconds'),
            self::positiveInteger($config['lock_wait_seconds'] ?? self::DEFAULT_LOCK_WAIT_SECONDS, 'lock_wait_seconds'),
        );
    }

    private static function boolean(mixed $value, string $key): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return false;
        }

        if (is_int($value) && ($value === 0 || $value === 1)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $normalized = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        throw new InvalidArgumentException(sprintf('Traverse cache [%s] configuration must be a boolean.', $key));
    }

    private static function positiveInteger(mixed $value, string $key): int
    {
        if (! is_int($value) || $value < 1) {
            throw new InvalidArgumentException(sprintf('Traverse cache [%s] configuration must be a positive integer.', $key));
        }

        return $value;
    }

    private static function prefix(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException('Traverse cache [prefix] configuration must be a non-empty string.');
        }

        return $value;
    }
}
