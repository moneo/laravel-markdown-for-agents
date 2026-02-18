<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "agents", "workers_ai", "browser"
    |
    | agents     — Content negotiation (Accept: text/markdown). Fastest, free.
    | workers_ai — File conversion via Workers AI toMarkdown API.
    | browser    — Headless browser rendering, ideal for SPAs.
    |
    */

    'default' => env('MFA_DRIVER', 'agents'),

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Credentials
    |--------------------------------------------------------------------------
    |
    | Required for workers_ai and browser drivers.
    | Not required for the agents driver.
    |
    */

    'account_id' => env('CF_ACCOUNT_ID'),
    'api_token' => env('CF_API_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Driver Configuration
    |--------------------------------------------------------------------------
    */

    'drivers' => [

        'agents' => [
            'timeout' => 30,
            'retry' => 3,
            'retry_delay' => 100,
        ],

        'workers_ai' => [
            'timeout' => 60,
            'retry' => 2,
        ],

        'browser' => [
            'timeout' => 90,
            'retry' => 2,
            'wait_until' => 'networkidle0',
            'user_agent' => null,
            'reject_patterns' => [],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'enabled' => env('MFA_CACHE', true),
        'store' => env('MFA_CACHE_STORE'),
        'ttl' => env('MFA_CACHE_TTL', 3600),
        'prefix' => 'mfa_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */

    'logging' => [
        'enabled' => false,
        'channel' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the ServeMarkdownForAgents middleware that makes
    | your own Laravel app respond with markdown to AI agents.
    |
    */

    'middleware' => [
        'content_signals' => [
            'ai-train' => 'yes',
            'search' => 'yes',
            'ai-input' => 'yes',
        ],
    ],

];
