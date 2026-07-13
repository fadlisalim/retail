<?php

use App\Services\SettingService;

if (! function_exists('rupiah')) {
    /**
     * Format a numeric amount as Indonesian Rupiah, e.g. 1500000 -> "Rp 1.500.000".
     */
    function rupiah(float|int|string|null $amount): string
    {
        $amount = (float) ($amount ?? 0);

        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}

if (! function_exists('setting')) {
    /**
     * Read a runtime setting (settings table, then config/rekasurya.php fallback).
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingService::class)->get($key, $default);
    }
}

if (! function_exists('brand')) {
    /**
     * The storefront brand/store name (env APP_BRAND -> config default).
     * Distinct from the legal company name (config rekasurya.company.legal_name).
     */
    function brand(): string
    {
        return (string) config('rekasurya.company.brand_name', 'Energi.Click');
    }
}

if (! function_exists('whatsapp_link')) {
    /**
     * Build a wa.me deep link with a prefilled message.
     */
    function whatsapp_link(string $message, ?string $number = null): string
    {
        $number = $number ?: app(SettingService::class)->whatsappNumber();
        $number = preg_replace('/[^0-9]/', '', (string) $number);

        return 'https://wa.me/'.$number.'?text='.rawurlencode($message);
    }
}
