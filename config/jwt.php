<?php

return [
    /*
    |--------------------------------------------------------------------------
    | JWT Authentication Configuration
    |--------------------------------------------------------------------------
    */

    'secret' => env('JWT_SECRET'),

    'issuer' => env('JWT_ISSUER', 'smartpos-auth-service'),

    'audience' => env('JWT_AUDIENCE', 'smartpos-api'),

    'verify_issuer' => env('JWT_VERIFY_ISSUER', false),

    'verify_audience' => env('JWT_VERIFY_AUDIENCE', false),

    'identity_service_url' => env('IDENTITY_SERVICE_URL', 'http://localhost:8001'),

    'algo' => env('JWT_ALGO', 'HS256'),
];
