<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Events;

use DateTimeImmutable;

final readonly class VisitFailed
{
    public function __construct(
        public string $invocationId,
        public string $url,
        public string $driver,
        public DateTimeImmutable $failedAt,
        public float $durationInMilliseconds,
        public string $exceptionClass,
    ) {}
}
