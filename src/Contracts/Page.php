<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Contracts;

interface Page
{
    public function markdown(): string;

    /**
     * @return array<string, mixed>
     */
    public function semanticTree(): array;

    /**
     * @return array<int, mixed>
     */
    public function interactiveElements(): array;

    /**
     * @return array<int|string, mixed>
     */
    public function structuredData(): array;
}
