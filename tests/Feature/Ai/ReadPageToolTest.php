<?php

declare(strict_types=1);

use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\AiServiceProvider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Tools\Request;
use Tonyputi\Traverse\Actions\ReadPageAction;
use Tonyputi\Traverse\Ai\ReadPageTool;
use Tonyputi\Traverse\Contracts\Browser;
use Tonyputi\Traverse\Contracts\Factory;
use Tonyputi\Traverse\Contracts\Page;
use Tonyputi\Traverse\Enums\PageFormat;
use Tonyputi\Traverse\ValueObjects\ReadPageRequest as PageReadRequest;
use Tonyputi\Traverse\ValueObjects\ReadPageResponse;

it('is arrayable and JSON serializable', function (): void {
    $response = ReadPageResponse::success('https://example.test', PageFormat::Markdown, '# Traverse');

    expect($response->toArray())->toBe([
        'ok' => true,
        'url' => 'https://example.test',
        'format' => 'markdown',
        'content' => '# Traverse',
        'truncated' => false,
    ])->and(json_encode($response, JSON_THROW_ON_ERROR))->toBe(json_encode($response->toArray(), JSON_THROW_ON_ERROR));
});

it('resolves the read-only tool from the container', function (): void {
    $tool = app(ReadPageTool::class);

    expect($tool)
        ->toBeInstanceOf(ReadPageTool::class)
        ->name()->toBe('traverse-read');
});

it('exposes a compact read-only schema', function (): void {
    [$tool] = readPageTool();

    $schema = $tool->schema(new JsonSchemaTypeFactory);

    expect($tool)
        ->name()->toBe('traverse-read')
        ->description()->toContain('outbound network')
        ->and($schema)
        ->toHaveKeys(['url', 'format', 'max_characters'])
        ->and($schema['url']->toArray())
        ->toMatchArray([
            'type' => 'string',
            'format' => 'uri',
        ])
        ->and($schema['format']->toArray())
        ->toMatchArray([
            'enum' => ['markdown', 'semantic-tree', 'interactive-elements', 'structured-data'],
            'default' => 'markdown',
        ])
        ->and($schema['max_characters']->toArray())
        ->toMatchArray([
            'minimum' => 1,
            'maximum' => PageReadRequest::MAX_MAX_CHARACTERS,
        ]);
});

it('reads the selected page representation through the browser contract', function (string $format, string|array $expected): void {
    [$tool, $browser] = readPageTool();

    $result = aiToolResult($tool, [
        'url' => 'https://example.test/documentation',
        'format' => $format,
    ]);

    expect($result)
        ->toMatchArray([
            'ok' => true,
            'url' => 'https://example.test/documentation',
            'format' => $format,
            'content' => $expected,
            'truncated' => false,
        ])
        ->and($browser->urls)->toBe(['https://example.test/documentation']);
})->with('supported read page formats');

it('defaults to bounded Markdown and reports truncation', function (): void {
    [$tool, $browser] = readPageTool(markdown: 'abcdef');

    $result = aiToolResult($tool, [
        'url' => 'https://example.test',
        'max_characters' => 4,
    ]);

    expect($result)
        ->toMatchArray([
            'ok' => true,
            'format' => 'markdown',
            'content' => 'abcd',
            'truncated' => true,
        ])
        ->and($browser->urls)->toBe(['https://example.test']);
});

it('rejects invalid tool input before visiting a page', function (array $input, string $message): void {
    [$tool, $browser] = readPageTool();

    $result = aiToolResult($tool, $input);

    expect($result)
        ->toMatchArray([
            'ok' => false,
            'error' => [
                'code' => 'invalid_request',
                'message' => $message,
            ],
        ])
        ->and($browser->urls)->toBe([]);
})->with('invalid read page inputs');

it('executes through a Laravel AI agent without a provider-native browsing tool', function (): void {
    app()->register(AiServiceProvider::class);

    [$tool, $browser] = readPageTool();

    $agent = new class($tool) implements Agent, HasTools
    {
        use Promptable;

        public function __construct(private readonly ReadPageTool $tool) {}

        public function instructions(): string
        {
            return 'Use the available tools.';
        }

        public function tools(): iterable
        {
            return [$this->tool];
        }
    };

    $agent::fake([
        new ToolCall('call_123', 'traverse-read', [
            'url' => 'https://example.test',
            'format' => 'markdown',
        ]),
        'The page was read.',
    ]);

    $response = $agent->prompt('Read the page.');

    expect($response)
        ->text->toBe('The page was read.')
        ->toolResults->toHaveCount(1)
        ->and($response->toolResults->first()->result)
        ->toBe(json_encode([
            'ok' => true,
            'url' => 'https://example.test',
            'format' => 'markdown',
            'content' => '# Traverse',
            'truncated' => false,
        ], JSON_THROW_ON_ERROR))
        ->and($browser->urls)->toBe(['https://example.test']);
});

it('returns a safe error without exposing driver diagnostics', function (): void {
    $browser = new class implements Browser
    {
        public function visit(string $url): Page
        {
            throw new RuntimeException('/private/application/lightpanda');
        }
    };
    $tool = new ReadPageTool(new ReadPageAction(readPageFactory($browser)));

    expect(aiToolResult($tool, ['url' => 'https://example.test']))->toBe([
        'ok' => false,
        'error' => [
            'code' => 'visit_failed',
            'message' => 'The page could not be read.',
        ],
    ]);
});

/**
 * @return array{ReadPageTool, Browser&object{urls: list<string>}}
 */
function readPageTool(string $markdown = '# Traverse'): array
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

    $browser = new class($page) implements Browser
    {
        /** @var list<string> */
        public array $urls = [];

        public function __construct(private readonly Page $page) {}

        public function visit(string $url): Page
        {
            $this->urls[] = $url;

            return $this->page;
        }
    };

    return [new ReadPageTool(new ReadPageAction(readPageFactory($browser))), $browser];
}

function readPageFactory(Browser $browser): Factory
{
    return new class($browser) implements Factory
    {
        public function __construct(private readonly Browser $browser) {}

        public function browser(?string $driver = null): Browser
        {
            return $this->browser;
        }
    };
}

/**
 * @param  array<string, mixed>  $input
 * @return array<string, mixed>
 */
function aiToolResult(ReadPageTool $tool, array $input): array
{
    return json_decode($tool->handle(new Request($input)), true, flags: JSON_THROW_ON_ERROR);
}
