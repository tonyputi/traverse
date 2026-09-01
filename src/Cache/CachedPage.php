<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Cache;

use JsonException;
use Tonyputi\Traverse\Contracts\Page;

/**
 * A JSON-serializable snapshot of a page's four read primitives.
 *
 * Values that cannot be JSON-encoded fail capture and the page is not
 * cached. Restoring rejects invalid JSON or shape-mismatched payloads, so
 * corrupt entries become cache misses. Note that non-JSON-native values
 * (objects) that do encode are decoded lossily into arrays.
 *
 * @internal
 */
final readonly class CachedPage implements Page
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

    public static function capture(Page $page): ?string
    {
        try {
            return json_encode([
                'markdown' => $page->markdown(),
                'semanticTree' => $page->semanticTree(),
                'interactiveElements' => $page->interactiveElements(),
                'structuredData' => $page->structuredData(),
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }

    public static function restore(string $snapshot): ?self
    {
        try {
            $decoded = json_decode($snapshot, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        $markdown = $decoded['markdown'] ?? null;
        $semanticTree = $decoded['semanticTree'] ?? null;
        $interactiveElements = $decoded['interactiveElements'] ?? null;
        $structuredData = $decoded['structuredData'] ?? null;

        if (! is_string($markdown) || ! is_array($semanticTree) || ! is_array($interactiveElements) || ! is_array($structuredData)) {
            return null;
        }

        return new self($markdown, $semanticTree, $interactiveElements, $structuredData);
    }

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
