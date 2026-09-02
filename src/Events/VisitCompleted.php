<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Events;

use DateTimeImmutable;

final readonly class VisitCompleted
{
    public function __construct(
        public string $invocationId,
        public string $url,
        public string $driver,
        public DateTimeImmutable $completedAt,
        public float $durationInMilliseconds,
        public bool $cacheHit = false,
    ) {}
}
