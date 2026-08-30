<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Lightpanda;

use Tonyputi\Traverse\Contracts\Page as PageContract;

final readonly class Page implements PageContract
{
    /**
     * @param  array<string, mixed>  $semanticTree
     * @param  array<int, mixed>  $interactiveElements
     * @param  array<int|string, mixed>  $structuredData
     */
    public function __construct(
        private string $markdown,
        private array $semanticTree,
        private array $interactiveElements,
        private array $structuredData,
    ) {}

    public function markdown(): string
    {
        return $this->markdown;
    }

    public function semanticTree(): array
    {
        return $this->semanticTree;
    }

    public function interactiveElements(): array
    {
        return $this->interactiveElements;
    }

    public function structuredData(): array
    {
        return $this->structuredData;
    }
}
