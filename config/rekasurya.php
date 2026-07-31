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

    // Ambil-di-gudang (pickup) info shown at checkout. Overridable via the
    // admin settings page (keys pickup.address / pickup.maps_url).
    'pickup' => [
        'address' => env('PICKUP_ADDRESS', 'Rekasurya Eco Building — Komp Ruko, Jl. Terusan Jakarta / Jl. Puri Dago Raya No. 342 Kav 31, Sukamiskin, Kec. Arcamanik, Kota Bandung, Jawa Barat 40293'),
        'maps_url' => env('PICKUP_MAPS_URL', 'https://www.google.com/maps/place/PT.+Rekasurya+Prima+Daya+(Rekasurya+Eco+Building)/data=!4m2!3m1!1s0x0:0xe0acea1e94ea46ef'),
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

    'media' => [
        // Uploaded product images are downscaled so the longest side is at most
        // this many pixels (0 = keep original size). 1280px stays crisp for the
        // gallery + zoom while keeping files light.
        'max_image_dimension' => (int) env('MEDIA_MAX_IMAGE_DIMENSION', 1280),
        // Re-encode quality (0–100) for the optimised WebP/JPEG output. 80 keeps
        // photos sharp ("tidak pecah") at a fraction of the original file size.
        'image_quality' => (int) env('MEDIA_IMAGE_QUALITY', 80),
        // Max accepted upload size (KB) for a product/variant image. The original
        // can be large (it's re-encoded to a few hundred KB WebP afterwards), so
        // this only needs to clear the biggest source file. NOTE: the server's PHP
        // upload_max_filesize / post_max_size must be >= this too.
        'max_upload_kb' => (int) env('MEDIA_MAX_UPLOAD_KB', 15360),
        // Max accepted short-product-video size (KB). Default 20 MB.
        'max_video_kb' => (int) env('MEDIA_MAX_VIDEO_KB', 20480),
    ],

    'orders' => [
        // Unpaid orders awaiting payment are auto-cancelled after this many hours
        // (stock released, commissions voided). 0 disables auto-cancel.
        'payment_window_hours' => (int) env('ORDER_PAYMENT_WINDOW_HOURS', 24),
    ],

    'demo' => [
        // When true, seeded demo credentials are shown on the login screen.
        'expose_credentials' => env('DEMO_EXPOSE_CREDENTIALS', true),
    ],

    // How long the traffic-source log (site_visits) is kept, in days.
    // Pruned nightly by `visits:prune`.
    'traffic_retention_days' => (int) env('TRAFFIC_RETENTION_DAYS', 90),
];
