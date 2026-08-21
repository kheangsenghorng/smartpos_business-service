<?php

return [
    /*
    |--------------------------------------------------------------------------
    | JWT Authentication Configuration
    |--------------------------------------------------------------------------
    |
    | SEC-01: verify_issuer and verify_audience are explicitly set to true by
    | default to prevent cross-service token replay attacks. A JWT minted for
    | another internal service will be rejected even if it carries the same
    | signing secret. Override via .env only for local/testing environments.
    |
    */

    'secret' => env('JWT_SECRET'),

    'issuer' => env('JWT_ISSUER', 'smartpos-auth-service'),

    'audience' => env('JWT_AUDIENCE', 'smartpos-api'),

    // SEC-01 FIX: Changed defaults from false → true (secure-by-default)
    'verify_issuer' => env('JWT_VERIFY_ISSUER', true),

    'verify_audience' => env('JWT_VERIFY_AUDIENCE', true),

    'identity_service_url' => env('IDENTITY_SERVICE_URL', 'http://localhost:8001'),

    'algo' => env('JWT_ALGO', 'HS256'),
];
