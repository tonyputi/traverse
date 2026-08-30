<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Ai;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use JsonException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request as ToolRequest;
use Tonyputi\Traverse\Actions\ReadPageAction;
use Tonyputi\Traverse\Enums\PageFormat;
use Tonyputi\Traverse\ValueObjects\ReadPageRequest;
use Tonyputi\Traverse\ValueObjects\ReadPageResponse;

final readonly class ReadPageTool implements Tool
{
    public function __construct(private ReadPageAction $readPage) {}

    public function name(): string
    {
        return 'traverse-read';
    }

    public function description(): string
    {
        return 'Read one HTTP or HTTPS page as Markdown, a semantic tree, interactive elements, or structured data. This tool can access the same outbound network as the application.';
    }

    public function handle(ToolRequest $request): string
    {
        try {
            $input = ReadPageRequest::fromArray($request->all());
        } catch (\InvalidArgumentException $exception) {
            return $this->encode(ReadPageResponse::failure('invalid_request', $exception->getMessage()));
        }

        return $this->encode($this->readPage->handle($input));
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

    private function encode(ReadPageResponse $result): string
    {
        try {
            return json_encode($result->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            return '{"ok":false,"error":{"code":"invalid_page_data","message":"The browsing driver returned data that cannot be encoded."}}';
        }
    }
}
