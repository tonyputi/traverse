<?php

declare(strict_types=1);

use Tonyputi\Traverse\Lightpanda\LightpandaPage;

it('preserves the native Lightpanda page payloads', function (): void {
    $page = new LightpandaPage(
        '# Traverse',
        ['role' => 'document', 'children' => []],
        [['role' => 'link', 'name' => 'Documentation']],
        ['jsonLd' => [['@type' => 'Article']]],
    );

    expect($page->markdown())->toBe('# Traverse')
        ->and($page->semanticTree())->toBe(['role' => 'document', 'children' => []])
        ->and($page->interactiveElements())->toBe([['role' => 'link', 'name' => 'Documentation']])
        ->and($page->structuredData())->toBe(['jsonLd' => [['@type' => 'Article']]]);
});
