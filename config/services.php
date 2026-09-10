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
        // Some Wablas servers post only the stored FILENAME of incoming media
        // ("abc.jpeg") instead of a URL. Set the base that serves those files
        // (e.g. https://pati.wablas.com/media/) so the inbox can show them.
        'media_base_url' => env('WABLAS_MEDIA_BASE_URL'),
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

    // Ongkir kurir reguler (JNE, J&T, SiCepat, …) via RajaOngkir/Komerce.
    // API key hanya dari .env. origin_id = ID kelurahan gudang (cari dengan
    // `php artisan ongkir:cari "nama kelurahan"`). Paket gratis ±100 request/
    // hari — hasil di-cache `cache_minutes`. Barang di atas max_weight_grams
    // tidak ditawarkan kurir reguler (tetap kargo).
    // Reverse geocoding GPS → kelurahan untuk tombol "ongkir ke lokasi saya"
    // (OpenStreetMap Nominatim; wajib User-Agent yang mengidentifikasi situs).
    // Tracker analitik kunjungan halaman toko (dipasang di semua halaman
    // storefront, termasuk /konsultasi). Kosongkan env untuk mematikan.
    'analytics' => [
        'tracker_url' => env('ANALYTICS_TRACKER_URL', 'https://ridlabs.id/reka/analytics/tracker.js'),
    ],

    'nominatim' => [
        'base_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'),
        'user_agent' => env('NOMINATIM_USER_AGENT', 'EnergiClick/1.0 (+https://energi.click)'),
    ],

    'rajaongkir' => [
        'enabled' => (bool) env('RAJAONGKIR_ENABLED', false),
        'api_key' => env('RAJAONGKIR_API_KEY'),
        'base_url' => env('RAJAONGKIR_BASE_URL', 'https://rajaongkir.komerce.id/api/v1'),
        'origin_id' => env('RAJAONGKIR_ORIGIN_ID'),
        'couriers' => env('RAJAONGKIR_COURIERS', 'jne:jnt'),
        'max_weight_grams' => (int) env('RAJAONGKIR_MAX_WEIGHT_GRAMS', 50000),
        'cache_minutes' => (int) env('RAJAONGKIR_CACHE_MINUTES', 720),
    ],

];
