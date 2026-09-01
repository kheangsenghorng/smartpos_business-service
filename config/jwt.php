<?php

return [

    /*
    |--------------------------------------------------------------------------
    | JWT Authentication Configuration
    |--------------------------------------------------------------------------
    |
    | Business Service does NOT sign JWT tokens.
    | Identity Service signs tokens using the RSA private key.
    |
    | Business Service receives only the public key and uses it to verify:
    |
    |   - Signature
    |   - Issuer (iss)
    |   - Audience (aud)
    |   - Expiration (exp)
    |
    */

    /*
    |--------------------------------------------------------------------------
    | JWT Algorithm
    |--------------------------------------------------------------------------
    */

    'algo' => env('JWT_ALGO', 'RS256'),

    /*
    |--------------------------------------------------------------------------
    | JWT Public Key
    |--------------------------------------------------------------------------
    |
    | Production:
    | /run/secrets/identity-jwt/public.pem
    |
    | The Docker container receives this file from:
    | /opt/smartpos/secrets/identity-jwt/public.pem
    |
    */

    'public_key' => env(
        'JWT_PUBLIC_KEY',
        file_exists('/run/secrets/identity-jwt/public.pem')
            ? '/run/secrets/identity-jwt/public.pem'
            : (file_exists(base_path('../secrets/identity-jwt/public.pem'))
                ? base_path('../secrets/identity-jwt/public.pem')
                : (file_exists(storage_path('certs/jwt-public.pem'))
                    ? 'file://storage/certs/jwt-public.pem'
                    : '/opt/smartpos/secrets/identity-jwt/public.pem'))
    ),

    /*
    |--------------------------------------------------------------------------
    | JWT Issuer
    |--------------------------------------------------------------------------
    |
    | Must match the "iss" claim created by Identity Service.
    |
    */

    'issuer' => env(
        'JWT_ISSUER',
        'smartpos-auth-service'
    ),

    /*
    |--------------------------------------------------------------------------
    | JWT Audience
    |--------------------------------------------------------------------------
    |
    | Must match the "aud" claim created by Identity Service.
    |
    */

    'audience' => env(
        'JWT_AUDIENCE',
        'smartpos-api'
    ),

    /*
    |--------------------------------------------------------------------------
    | Claim Verification
    |--------------------------------------------------------------------------
    */

    'verify_issuer' => env(
        'JWT_VERIFY_ISSUER',
        true
    ),

    'verify_audience' => env(
        'JWT_VERIFY_AUDIENCE',
        true
    ),

    /*
    |--------------------------------------------------------------------------
    | Identity Service
    |--------------------------------------------------------------------------
    |
    | Internal Docker address.
    |
    */

    'identity_service_url' => env(
        'IDENTITY_SERVICE_URL',
        'http://identity-service:8000'
    ),

];