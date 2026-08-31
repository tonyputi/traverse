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
#[Instructions('This server provides read-only web page representations through Traverse.')]
final class TraverseServer extends Server
{
    /**
     * @var list<class-string<ReadPageTool>>
     */
    protected array $tools = [
        ReadPageTool::class,
    ];
}
