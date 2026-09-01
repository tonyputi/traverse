<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Tests\Fixtures\Cache;

use Tonyputi\Traverse\Contracts\Page;

final readonly class FakePage implements Page
{
    /**
     * @param  array<string, mixed>  $semanticTree
     * @param  array<int, mixed>  $interactiveElements
     * @param  array<int|string, mixed>  $structuredData
     */
    public function __construct(
        private string $markdown = '# Counted',
        private array $semanticTree = ['role' => 'document'],
        private array $interactiveElements = [['role' => 'link', 'name' => 'Docs']],
        private array $structuredData = ['jsonLd' => ['@type' => 'Article']],
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
