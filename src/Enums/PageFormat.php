<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Enums;

/**
 * @internal
 */
enum PageFormat: string
{
    case Markdown = 'markdown';
    case SemanticTree = 'semantic-tree';
    case InteractiveElements = 'interactive-elements';
    case StructuredData = 'structured-data';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $format): string => $format->value, self::cases());
    }
}
