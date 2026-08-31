<?php

declare(strict_types=1);

use Laravel\Mcp\Server\McpServiceProvider;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Tonyputi\Traverse\Contracts\Browser;
use Tonyputi\Traverse\Contracts\Factory;
use Tonyputi\Traverse\Contracts\Page;
use Tonyputi\Traverse\Mcp\Tools\ReadPageTool;
use Tonyputi\Traverse\Mcp\TraverseServer;

beforeEach(function (): void {
    app()->register(McpServiceProvider::class);
});

it('exposes a read-only open-world tool schema', function (): void {
    $tool = app(ReadPageTool::class);

    expect($tool->toArray())
        ->toMatchArray([
            'name' => 'traverse-read',
            'annotations' => [
                'readOnlyHint' => true,
                'openWorldHint' => true,
            ],
        ])
        ->and($tool->description())
        ->toContain('Use this tool when')
        ->toContain('network policy')
        ->and($tool->toArray()['inputSchema']['properties'])
        ->toHaveKeys(['url', 'format', 'max_characters']);
});

it('reads every supported representation through the ready-made server', function (string $format, string|array $expected): void {
    app()->instance(Factory::class, mcpReadPageFactory());

    TraverseServer::tool(ReadPageTool::class, [
        'url' => 'https://example.test/documentation',
        'format' => $format,
    ])->assertOk()->assertStructuredContent([
        'ok' => true,
        'url' => 'https://example.test/documentation',
        'format' => $format,
        'content' => $expected,
        'truncated' => false,
    ]);
})->with('supported read page formats');

it('applies the shared Markdown limit through the ready-made server', function (): void {
    app()->instance(Factory::class, mcpReadPageFactory('abcdef'));

    TraverseServer::tool(ReadPageTool::class, [
        'url' => 'https://example.test',
        'max_characters' => 4,
    ])->assertOk()->assertStructuredContent([
        'ok' => true,
        'url' => 'https://example.test',
        'format' => 'markdown',
        'content' => 'abcd',
        'truncated' => true,
    ]);
});

it('returns structured validation errors without visiting a page', function (): void {
    app()->instance(Factory::class, mcpReadPageFactory());

    TraverseServer::tool(ReadPageTool::class, [
        'url' => 'file:///private/example',
    ])->assertOk()->assertStructuredContent([
        'ok' => false,
        'error' => [
            'code' => 'invalid_request',
            'message' => 'The url must be an absolute HTTP or HTTPS URL.',
        ],
    ]);
});

it('does not expose driver diagnostics in structured failures', function (): void {
    $browser = new class implements Browser
    {
        public function visit(string $url): Page
        {
            throw new RuntimeException('/private/application/lightpanda');
        }
    };

    app()->instance(Factory::class, mcpReadPageFactory(browser: $browser));

    TraverseServer::tool(ReadPageTool::class, [
        'url' => 'https://example.test',
    ])->assertOk()->assertStructuredContent([
        'ok' => false,
        'error' => [
            'code' => 'visit_failed',
            'message' => 'The page could not be read.',
        ],
    ])->assertDontSee('/private/application/lightpanda');
});

it('exposes only the read tool from its ready-made server', function (): void {
    $server = app()->make(TraverseServer::class, ['transport' => new FakeTransporter]);

    expect($server->createContext()->tools()->map->name()->all())->toBe(['traverse-read'])
        ->and($server->createContext()->instructions)
        ->toContain('traverse-read')
        ->toContain("application's outbound network access");
});

function mcpReadPageFactory(string $markdown = '# Traverse', ?Browser $browser = null): Factory
{
    $page = new class($markdown) implements Page
    {
        public function __construct(private readonly string $markdown) {}

        public function markdown(): string
        {
            return $this->markdown;
        }

        public function semanticTree(): array
        {
            return ['role' => 'main'];
        }

        public function interactiveElements(): array
        {
            return [['role' => 'link', 'name' => 'Documentation']];
        }

        public function structuredData(): array
        {
            return [['@type' => 'Article']];
        }
    };

    $browser ??= new class($page) implements Browser
    {
        public function __construct(private readonly Page $page) {}

        public function visit(string $url): Page
        {
            return $this->page;
        }
    };

    return new class($browser) implements Factory
    {
        public function __construct(private readonly Browser $browser) {}

        public function browser(?string $driver = null): Browser
        {
            return $this->browser;
        }
    };
}
