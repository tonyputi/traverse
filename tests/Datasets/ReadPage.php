<?php

declare(strict_types=1);

dataset('supported read page formats', [
    'markdown' => ['markdown', '# Traverse'],
    'semantic tree' => ['semantic-tree', ['role' => 'main']],
    'interactive elements' => ['interactive-elements', [['role' => 'link', 'name' => 'Documentation']]],
    'structured data' => ['structured-data', [['@type' => 'Article']]],
]);

dataset('invalid read page inputs', [
    'missing URL' => [[], 'The url must be an absolute HTTP or HTTPS URL.'],
    'unsupported URL scheme' => [['url' => 'file:///etc/passwd'], 'The url must be an absolute HTTP or HTTPS URL.'],
    'unknown format' => [['url' => 'https://example.test', 'format' => 'html'], 'The format must be one of [markdown, semantic-tree, interactive-elements, structured-data].'],
    'limit on structured data' => [['url' => 'https://example.test', 'format' => 'structured-data', 'max_characters' => 100], 'The max_characters option is only available for Markdown.'],
    'limit above maximum' => [['url' => 'https://example.test', 'max_characters' => 50_001], 'The max_characters must be between 1 and 50000.'],
]);
