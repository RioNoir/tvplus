<?php

/**
 * Cache Configuration
 */

return [

    'default' => env('SP_CACHE_DRIVER', 'file'),
    'stores' => [

        'apc' => [
            'driver' => 'apc',
        ],

        'array' => [
            'driver' => 'array',
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'cache',
            'connection' => 'streaming_plus',
        ],

        'file' => [
            'driver' => 'file',
            'path' => sp_data_path('app/cache'),
        ],

        'memcached' => [
            'driver' => 'memcached',
            'persistent_id' => env('SP_APP_MEMCACHED_PERSISTENT_ID'),
            'sasl' => [
                env('SP_APP_MEMCACHED_USERNAME'),
                env('SP_APP_MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Memcached::OPT_CONNECT_TIMEOUT => 2000,
            ],
            'servers' => [
                [
                    'host' => env('SP_APP_MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('SP_APP_MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('SP_APP_CACHE_REDIS_CONNECTION', 'cache'),
        ],

    ],
    'prefix' => 'streaming_plus_cache',
];
