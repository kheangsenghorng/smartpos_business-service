<?php

use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;

return [

    'api_path' => 'api/v1',

    'api_domain' => null,

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

    'ui' => [
        'title' => 'SmartPOS Business',
    ],

    'security_strategy' => [
        MiddlewareAuthSecurityStrategy::class,
        [
            'middleware' => ['jwt.auth', 'auth', 'auth:*'],
        ],
    ],
];
