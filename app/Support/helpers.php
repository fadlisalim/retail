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

if (! function_exists('linkify_buttons')) {
    /**
     * Turn every link inside rich-text HTML into a prominent "KLIK DI SINI" button.
     *
     * Both existing <a> tags and bare http(s) URLs are converted, so a product
     * description that simply pastes a link renders a call-to-action button
     * instead of plain underlined text.
     */
    function linkify_buttons(?string $html): string
    {
        if ($html === null || $html === '') {
            return (string) $html;
        }

        $btnClass = 'not-prose my-1 inline-flex items-center gap-1 rounded-lg bg-brand-600 px-4 py-2 '
            .'text-sm font-semibold text-white no-underline transition hover:bg-brand-700';

        $button = function (string $href) use ($btnClass): string {
            $href = trim($href);
            // Guard against javascript: and other unsafe schemes.
            if (! preg_match('#^https?://#i', $href) && ! str_starts_with($href, '/') && ! str_starts_with($href, 'mailto:')) {
                return e($href);
            }

            return '<a href="'.e($href).'" target="_blank" rel="noopener" class="'.$btnClass.'">'
                .'<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">'
                .'<path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>'
                .'</svg>KLIK DI SINI</a>';
        };

        // Pass 1: replace existing anchor tags.
        $html = preg_replace_callback(
            '/<a\b[^>]*\bhref=(["\'])(.*?)\1[^>]*>.*?<\/a>/is',
            fn ($m) => $button(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5)),
            $html,
        );

        // Pass 2: replace bare http(s) URLs that aren't already inside an attribute/tag.
        $html = preg_replace_callback(
            '/(?<![">\'=\/])\bhttps?:\/\/[^\s<>"\']+/i',
            fn ($m) => $button($m[0]),
            $html,
        );

        return $html;
    }
}
