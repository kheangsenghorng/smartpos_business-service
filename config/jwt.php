<?php

return [
    /*
    |--------------------------------------------------------------------------
    | JWT Authentication Configuration
    |--------------------------------------------------------------------------
    */

    'secret' => env('JWT_SECRET', 'RBX4H7inTQwxHrstXMsHwCGkUWm4JPgAjl7gFN7FLwkTwy28HNN3gYbGZwE3q1UF'),

    'issuer' => env('JWT_ISSUER', 'smartpos-auth-service'),

    'audience' => env('JWT_AUDIENCE', 'smartpos-api'),

    'verify_issuer' => env('JWT_VERIFY_ISSUER', false),

    'verify_audience' => env('JWT_VERIFY_AUDIENCE', false),

    'algo' => env('JWT_ALGO', 'HS256'),
];
