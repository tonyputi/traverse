<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Tonyputi\Traverse\Contracts\Page;
use Tonyputi\Traverse\Enums\PageFormat;

/**
 * @internal
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class ReadPageResponse implements Arrayable, JsonSerializable
{
    /**
     * @param  string|array<array-key, mixed>|null  $content
     */
    private function __construct(
        private bool $successful,
        private ?string $url,
        private ?PageFormat $format,
        private string|array|null $content,
        private bool $truncated,
        private ?string $errorCode,
        private ?string $errorMessage,
    ) {}

    /**
     * @param  string|array<array-key, mixed>  $content
     */
    public static function success(
        string $url,
        PageFormat $format,
        string|array $content,
        bool $truncated = false,
    ): self {
        return new self(true, $url, $format, $content, $truncated, null, null);
    }

    public static function failure(string $code, string $message): self
    {
        return new self(false, null, null, null, false, $code, $message);
    }

    public static function fromPage(ReadPageRequest $request, Page $page): self
    {
        return match ($request->format) {
            PageFormat::Markdown => self::fromMarkdown($request, $page),
            PageFormat::SemanticTree => self::success($request->url, $request->format, $page->semanticTree()),
            PageFormat::InteractiveElements => self::success($request->url, $request->format, $page->interactiveElements()),
            PageFormat::StructuredData => self::success($request->url, $request->format, $page->structuredData()),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if (! $this->successful) {
            return [
                'ok' => false,
                'error' => [
                    'code' => $this->errorCode,
                    'message' => $this->errorMessage,
                ],
            ];
        }

        return [
            'ok' => true,
            'url' => $this->url,
            'format' => $this->format?->value,
            'content' => $this->content,
            'truncated' => $this->truncated,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function fromMarkdown(ReadPageRequest $request, Page $page): self
    {
        $limit = $request->maxCharacters ?? ReadPageRequest::DEFAULT_MAX_CHARACTERS;
        $markdown = $page->markdown();
        $truncated = mb_strlen($markdown) > $limit;

        return self::success(
            $request->url,
            $request->format,
            $truncated ? mb_substr($markdown, 0, $limit) : $markdown,
            $truncated,
        );
    }
}
