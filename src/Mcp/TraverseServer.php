<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Mcp;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Tonyputi\Traverse\Mcp\Tools\ReadPageTool;

#[Name('Traverse')]
#[Version('0.1.0')]
#[Instructions('Traverse provides read-only web page access through the application\'s configured browser driver. Use traverse-read when a user asks you to inspect a specific HTTP or HTTPS page and its content is needed to answer. Use markdown for general reading, a semantic tree to understand page structure, interactive elements to identify available controls, or structured data for embedded machine-readable data. This server cannot interact with pages, authenticate, submit data, or retain browsing state. It uses the application\'s outbound network access, so use it only when the application\'s network policy permits the requested URL.')]
final class TraverseServer extends Server
{
    /**
     * @var list<class-string<ReadPageTool>>
     */
    protected array $tools = [
        ReadPageTool::class,
    ];
}
