<?php

use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;

return [

    /*
    |--------------------------------------------------------------------------
    | API
    |--------------------------------------------------------------------------
    */

    'api_path' => 'api/v1',

    'api_domain' => null,

    /*
    |--------------------------------------------------------------------------
    | OpenAPI Server
    |--------------------------------------------------------------------------
    |
    | Force HTTPS because production is behind Nginx/OpenResty.
    | This prevents Scramble from generating:
    |
    | http://smartpos-api.servicefixit.me/api/v1
    |
    */

    'servers' => [
        'Production' => 'https://smartpos-api.servicefixit.me/api/v1',
    ],

    /*
    |--------------------------------------------------------------------------
    | API Information
    |--------------------------------------------------------------------------
    */

    'info' => [
        'version' => '1.0.0',

        'description' => '
# SmartPOS Business

Business Operations & POS Management API for SmartPOS.

## Features

- Businesses (Tenant Master & Multi-Tenant Management)
- Business Users & Memberships
- Outlets (Store Locations)
- Cash Registers (POS Terminals)
- POS Hardware Devices (Terminal Security & Authentication)
        ',
    ],

    /*
    |--------------------------------------------------------------------------
    | Documentation UI
    |--------------------------------------------------------------------------
    */

    'ui' => [
        'title' => 'SmartPOS Business',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    'security_strategy' => [
        MiddlewareAuthSecurityStrategy::class,
        [
            'middleware' => [
                'jwt.auth',
                'auth',
                'auth:*',
            ],
        ],
    ],

];