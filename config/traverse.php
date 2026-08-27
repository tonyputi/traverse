<?php

return [
    'driver' => env('TRAVERSE_DRIVER', 'lightpanda'),

    'drivers' => [
        'lightpanda' => [
            'driver' => 'lightpanda',
            'binary' => env('TRAVERSE_LIGHTPANDA_BINARY'),
            'timeout' => 30,
        ],
    ],
];
