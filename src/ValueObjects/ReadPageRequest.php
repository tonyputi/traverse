<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\ValueObjects;

use InvalidArgumentException;
use Tonyputi\Traverse\Enums\PageFormat;

/**
 * @internal
 */
final readonly class ReadPageRequest
{
    public const int DEFAULT_MAX_CHARACTERS = 12_000;

    public const int MAX_MAX_CHARACTERS = 50_000;

    public function __construct(
        public string $url,
        public PageFormat $format,
        public ?int $maxCharacters,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $url = $input['url'] ?? null;

        if (! is_string($url) || ! self::isSupportedUrl($url)) {
            throw new InvalidArgumentException('The url must be an absolute HTTP or HTTPS URL.');
        }

        $format = $input['format'] ?? PageFormat::Markdown->value;

        if (! is_string($format) || ! ($format = PageFormat::tryFrom($format))) {
            throw new InvalidArgumentException(sprintf(
                'The format must be one of [%s].',
                implode(', ', PageFormat::values()),
            ));
        }

        $maxCharacters = $input['max_characters'] ?? null;

        if ($maxCharacters !== null && ! is_int($maxCharacters)) {
            throw new InvalidArgumentException('The max_characters must be an integer.');
        }

        if ($format !== PageFormat::Markdown && $maxCharacters !== null) {
            throw new InvalidArgumentException('The max_characters option is only available for Markdown.');
        }

        if ($maxCharacters !== null && ($maxCharacters < 1 || $maxCharacters > self::MAX_MAX_CHARACTERS)) {
            throw new InvalidArgumentException(sprintf(
                'The max_characters must be between 1 and %d.',
                self::MAX_MAX_CHARACTERS,
            ));
        }

        return new self($url, $format, $maxCharacters);
    }

    private static function isSupportedUrl(string $url): bool
    {
        $parts = parse_url($url);

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && is_array($parts)
            && isset($parts['scheme'], $parts['host'])
            && in_array(strtolower($parts['scheme']), ['http', 'https'], true);
    }
}
