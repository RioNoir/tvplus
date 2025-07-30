<?php

/**
 * Database Configuration
 */

return [

    'default' => env('SP_DB_CONNECTION', 'default'),
    'connections' => [

        'default' => [
            'driver' => 'sqlite',
            'database' => sp_data_path('/app/database.sqlite'),
            'prefix' => '',
        ],

        'jellyfin' => [
            'driver' => 'sqlite',
            'database' => sp_data_path('/jellyfin/data/jellyfin.db'),
            'prefix' => '',
        ],

        'library' => [
            'driver' => 'sqlite',
            'database' => sp_data_path('/jellyfin/data/library.db'),
            'prefix' => '',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'host' => env('SP_DB_HOST', '127.0.0.1'),
            'port' => env('SP_DB_PORT', 3306),
            'database' => env('SP_DB_DATABASE', 'forge'),
            'username' => env('SP_DB_USERNAME', 'forge'),
            'password' => env('SP_DB_PASSWORD', ''),
            'unix_socket' => env('SP_DB_SOCKET', ''),
            'charset' => env('SP_DB_CHARSET', 'utf8mb4'),
            'collation' => env('SP_DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => env('SP_DB_PREFIX', ''),
            'strict' => env('SP_DB_STRICT_MODE', false),
            'engine' => env('SP_DB_ENGINE'),
            'timezone' => env('SP_DB_TIMEZONE', '+00:00'),
        ],
    ],
    'migrations' => 'migrations',
];
