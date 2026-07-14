<?php

/**
 * Rekasurya Store application configuration.
 *
 * These are safe defaults. Anything an operator should be able to change at
 * runtime (tax rate, WhatsApp number, company info, shipping divisor) is ALSO
 * stored in the `settings` table and read through App\Services\SettingService,
 * which falls back to the values here. Never hardcode secrets — see .env.
 */
return [
    'company' => [
        'legal_name' => env('COMPANY_LEGAL_NAME', 'PT Rekasurya Primadaya'),
        // Storefront brand/store name (shown in the logo, title, etc.). The legal
        // entity above stays PT Rekasurya Primadaya; this is the consumer brand.
        'brand_name' => env('APP_BRAND', 'Energi.Click'),
        'tagline' => env('APP_TAGLINE', 'Energi Cerdas, Tinggal Klik!'),
        'npwp' => env('COMPANY_NPWP', '00.000.000.0-000.000'),
        'address' => env('COMPANY_ADDRESS', 'Jl. Energi Surya No. 1, Jakarta, Indonesia'),
        'email' => env('COMPANY_EMAIL', 'sales@rekasurya.test'),
        'phone' => env('COMPANY_PHONE', '021-0000-0000'),
    ],

    'whatsapp' => [
        'enabled' => env('WHATSAPP_ENABLED', true),
        // Store in international format without "+" or spaces, e.g. 628123456789.
        'number' => env('WHATSAPP_NUMBER', '628123456789'),
        'greeting' => 'Halo Rekasurya, saya ingin berkonsultasi.',
        'hours' => 'Senin–Sabtu, 08.00–17.00 WIB',
    ],

    'tax' => [
        // PPN percentage. Overridable via settings table (key: tax.ppn_percent).
        'ppn_percent' => env('PPN_PERCENT', 11),
        // PPN is OFF by default: catalogue prices already include tax, so nothing is
        // added on top at checkout. Toggle in Admin → Pengaturan or via PPN_ENABLED.
        'enabled' => env('PPN_ENABLED', false),
        'default_price_includes_tax' => true,
    ],

    'shipping' => [
        // Volumetric divisor is configurable per courier/service in the DB;
        // this is only the ultimate fallback.
        'default_volumetric_divisor' => 6000,
        'weight_rounding_kg' => 1, // round billable weight up to the nearest N kg
        'reservation_minutes' => 30, // how long stock is held once payment starts
    ],

    'currency' => [
        'code' => 'IDR',
        'symbol' => 'Rp',
        'decimals' => 0, // Rupiah is displayed without decimals
    ],

    'catalog' => [
        'per_page' => 24,
        'compare_max' => 4,
        'recently_viewed_max' => 12,
        'new_product_days' => 30, // product flagged "new" if published within N days
    ],

    'demo' => [
        // When true, seeded demo credentials are shown on the login screen.
        'expose_credentials' => env('DEMO_EXPOSE_CREDENTIALS', true),
    ],
];
