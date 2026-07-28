<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers.
    |
    */

    // Include the base 'portal/login' and 'user' routes just in case
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'portal/login',
        'logout',
        'user'
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS',
        'https://rungika.nova.bi,http://localhost:5173,https://localhost:5173,http://127.0.0.1:5173,https://127.0.0.1:5173'
    )))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    // CRITICAL: This must be true to allow cookies/sessions
    'supports_credentials' => true,

];
