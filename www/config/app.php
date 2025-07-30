<?php

/**
 * App Configuration
 */

return [
    'config' => sp_data_path('config.json'),
    'name' => 'Streaming Plus',
    'code_name' => 'streaming-plus',
    'env' => env('APP_ENV', 'local'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => 'http://localhost:8095',
    'timezone' => env('TZ', 'Europe/Rome'),
    'locale' =>'en',
    'fallback_locale' => 'en',
    'key' => null,
    'cipher' => 'AES-256-CBC',
];
