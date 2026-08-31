<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Tonyputi\Traverse\Actions\ReadPageAction;
use Tonyputi\Traverse\Enums\PageFormat;
use Tonyputi\Traverse\ValueObjects\ReadPageRequest;
use Tonyputi\Traverse\ValueObjects\ReadPageResponse;

#[Name('traverse-read')]
#[Description('Read one HTTP or HTTPS page as Markdown, a semantic tree, interactive elements, or structured data. This tool can access the same outbound network as the application.')]
#[IsReadOnly]
#[IsOpenWorld]
final class ReadPageTool extends Tool
{
    public function __construct(private readonly ReadPageAction $readPage) {}

    public function handle(Request $request): ResponseFactory
    {
        try {
            $input = ReadPageRequest::fromArray($request->all());
        } catch (InvalidArgumentException $exception) {
            return $this->structured(ReadPageResponse::failure('invalid_request', $exception->getMessage()));
        }

        return $this->structured($this->readPage->handle($input));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()
                ->format('uri')
                ->description('The absolute HTTP or HTTPS URL to read.')
                ->required(),
            'format' => $schema->string()
                ->enum(PageFormat::values())
                ->description('The page representation to return.')
                ->default(PageFormat::Markdown->value),
            'max_characters' => $schema->integer()
                ->min(1)
                ->max(ReadPageRequest::MAX_MAX_CHARACTERS)
                ->description('The maximum Markdown characters to return. Only use this with the markdown format.'),
        ];
    }

    private function structured(ReadPageResponse $response): ResponseFactory
    {
        return Response::structured($response->toArray());
    }
}
