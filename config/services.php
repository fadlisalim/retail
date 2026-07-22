<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Reference payment gateway. The webhook secret is read from .env — NEVER
    // hardcode gateway keys in source (spec §18/§30).
    'demo_gateway' => [
        'secret' => env('DEMO_GATEWAY_SECRET'),
    ],

    // WhatsApp gateway (Wablas). Token/secret ONLY from .env — never hardcode.
    // base_url is the server your Wablas device is on (see the Wablas dashboard),
    // e.g. https://solo.wablas.com, https://tegal.wablas.com, https://console.wablas.com.
    'wablas' => [
        'enabled' => (bool) env('WABLAS_ENABLED', false),
        'base_url' => env('WABLAS_BASE_URL', 'https://console.wablas.com'),
        'token' => env('WABLAS_TOKEN'),
        // Shared secret for the incoming-message webhook (?token=... on the URL
        // configured in the Wablas console). Empty = no token check.
        'webhook_token' => env('WABLAS_WEBHOOK_TOKEN'),
    ],

    // CS Assistant (Claude / Anthropic). API key ONLY from .env — never hardcode.
    // Powers the storefront chat assistant; answers are grounded in our own
    // product catalogue. Disabled by default so the site runs without a key.
    'anthropic' => [
        'enabled' => (bool) env('ANTHROPIC_ENABLED', false),
        'api_key' => env('ANTHROPIC_API_KEY'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5'),
        // Chat logging & retention. Raw transcripts are kept for a short window
        // (may contain names/phones a customer typed); aggregated daily stats are
        // non-personal and kept longer. Pruned daily by `assistant:prune`.
        'logging' => (bool) env('ANTHROPIC_LOG', true),
        'log_retention_days' => (int) env('ANTHROPIC_LOG_DAYS', 30),
        'stats_retention_days' => (int) env('ANTHROPIC_STATS_DAYS', 180),
    ],

];
