<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Actions;

use Throwable;
use Tonyputi\Traverse\Contracts\Factory;
use Tonyputi\Traverse\ValueObjects\ReadPageRequest;
use Tonyputi\Traverse\ValueObjects\ReadPageResponse;

/**
 * @internal
 */
final readonly class ReadPageAction
{
    public function __construct(private Factory $browsers) {}

    public function handle(ReadPageRequest $request): ReadPageResponse
    {
        try {
            return ReadPageResponse::fromPage(
                $request,
                $this->browsers->browser()->visit($request->url),
            );
        } catch (Throwable) {
            return ReadPageResponse::failure('visit_failed', 'The page could not be read.');
        }
    }
}
