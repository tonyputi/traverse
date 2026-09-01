<?php

return [
    'default' => env('TRAVERSE_DRIVER', 'lightpanda'),

    'drivers' => [
        'lightpanda' => [
            'driver' => 'lightpanda',
            'binary' => env('TRAVERSE_LIGHTPANDA_BINARY'),
            'timeout' => 30,
        ],
    ],

    'cache' => [
        'enabled' => env('TRAVERSE_CACHE_ENABLED', false),
        'store' => env('TRAVERSE_CACHE_STORE'),
        'ttl' => (int) env('TRAVERSE_CACHE_TTL', 300),
        'prefix' => 'traverse:pages:v1',
        'lock_seconds' => 60,
        'lock_wait_seconds' => 60,
    ],
];
