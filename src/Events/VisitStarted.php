<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Events;

use DateTimeImmutable;

final readonly class VisitStarted
{
    public function __construct(
        public string $invocationId,
        public string $url,
        public string $driver,
        public DateTimeImmutable $startedAt,
    ) {}
}
