<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    */

    'paths' => [
        'api/*',
        'docs/*',
    ],

    'allowed_methods' => [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'OPTIONS',
    ],

    'allowed_origins' => array_values(array_filter([

        // SmartPOS API / API documentation
        'https://smartpos-api.servicefixit.me',

        // Web Admin
        env('FRONTEND_URL'),

        // Flutter Web / POS Web
        env('POS_CLIENT_URL'),

        // Local development
        'http://localhost:3000',
        'http://localhost:3001',
        'http://localhost:5173',

    ])),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'Origin',
        'X-Requested-With',
        'X-User-Uuid',
        'X-Device-Uuid',
    ],

    'exposed_headers' => [
        'Retry-After',
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
    ],

    'max_age' => 86400,

    'supports_credentials' => true,

];