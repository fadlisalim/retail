<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Works out where a visitor came from, using (in order of trust):
 *  1. UTM parameters we control — the ad links we publish carry them.
 *  2. Click ids the platforms append themselves (fbclid, gclid, ttclid).
 *  3. The HTTP referrer host.
 * Falls back to "direct" when nothing identifies the origin.
 */
class TrafficAttribution
{
    /** Display labels for the stored source keys. */
    public const LABELS = [
        'meta_ads' => 'Meta Ads (FB/IG berbayar)',
        'google_ads' => 'Google Ads',
        'tiktok_ads' => 'TikTok Ads',
        'instagram' => 'Instagram (organik)',
        'facebook' => 'Facebook (organik)',
        'tiktok' => 'TikTok (organik)',
        'google' => 'Google (pencarian)',
        'bing' => 'Bing / mesin pencari lain',
        'whatsapp' => 'WhatsApp',
        'telegram' => 'Telegram',
        'youtube' => 'YouTube',
        'marketplace' => 'Marketplace (Tokopedia/Shopee/dll)',
        'email' => 'Email / Newsletter',
        'affiliate' => 'Afiliator',
        'referral' => 'Website lain',
        'direct' => 'Langsung / ketik URL',
    ];

    /** Tailwind classes per source, so charts stay readable (no interpolation). */
    public const COLORS = [
        'meta_ads' => 'bg-blue-500',
        'google_ads' => 'bg-amber-500',
        'tiktok_ads' => 'bg-pink-500',
        'instagram' => 'bg-fuchsia-500',
        'facebook' => 'bg-blue-400',
        'tiktok' => 'bg-pink-400',
        'google' => 'bg-emerald-500',
        'bing' => 'bg-emerald-400',
        'whatsapp' => 'bg-green-500',
        'telegram' => 'bg-sky-500',
        'youtube' => 'bg-red-500',
        'marketplace' => 'bg-orange-500',
        'email' => 'bg-indigo-500',
        'affiliate' => 'bg-violet-500',
        'referral' => 'bg-slate-400',
        'direct' => 'bg-gray-400',
    ];

    /**
     * @return array{source: string, medium: ?string, campaign: ?string, content: ?string, referrer_host: ?string}
     */
    public function resolve(Request $request): array
    {
        $utmSource = $this->clean($request->query('utm_source'));
        $utmMedium = mb_strtolower((string) $this->clean($request->query('utm_medium')));
        $campaign = $this->clean($request->query('utm_campaign'));
        $content = $this->clean($request->query('utm_content')) ?: $this->clean($request->query('utm_term'));

        $referrerHost = $this->referrerHost($request);
        $paidHint = $this->isPaidMedium($utmMedium)
            || $request->filled('fbclid') || $request->filled('gclid') || $request->filled('ttclid');

        $source = match (true) {
            // Explicit UTM wins — these are the links we publish ourselves.
            $utmSource !== null => $this->fromUtmSource(mb_strtolower($utmSource), $paidHint),
            // Platform click ids: the visitor definitely came from an ad click.
            $request->filled('gclid') => 'google_ads',
            $request->filled('ttclid') => 'tiktok_ads',
            $request->filled('fbclid') => $this->fromReferrerHost($referrerHost) === 'instagram' ? 'instagram' : 'facebook',
            $request->filled(\App\Services\AffiliateService::COOKIE) => 'affiliate',
            $referrerHost !== null => $this->fromReferrerHost($referrerHost),
            default => 'direct',
        };

        return [
            'source' => $source,
            'medium' => $utmMedium !== '' ? Str::limit($utmMedium, 30, '') : $this->impliedMedium($source),
            'campaign' => $campaign ? Str::limit($campaign, 120, '') : null,
            'content' => $content ? Str::limit($content, 120, '') : null,
            'referrer_host' => $referrerHost,
        ];
    }

    /** Map a utm_source value onto our source keys, honouring the paid hint. */
    private function fromUtmSource(string $utm, bool $paid): string
    {
        $isMeta = in_array($utm, ['fb', 'facebook', 'ig', 'instagram', 'meta'], true);
        $isGoogle = in_array($utm, ['google', 'gads', 'adwords'], true);
        $isTiktok = in_array($utm, ['tiktok', 'tt'], true);

        return match (true) {
            $isMeta && $paid => 'meta_ads',
            $isMeta => in_array($utm, ['ig', 'instagram'], true) ? 'instagram' : 'facebook',
            $isGoogle && $paid => 'google_ads',
            $isGoogle => 'google',
            $isTiktok && $paid => 'tiktok_ads',
            $isTiktok => 'tiktok',
            in_array($utm, ['whatsapp', 'wa'], true) => 'whatsapp',
            $utm === 'telegram' => 'telegram',
            $utm === 'youtube' => 'youtube',
            in_array($utm, ['email', 'newsletter', 'mailchimp'], true) => 'email',
            $utm === 'affiliate' => 'affiliate',
            default => 'referral',
        };
    }

    /** Classify by referring host (organic traffic has no UTM). */
    private function fromReferrerHost(?string $host): string
    {
        if ($host === null) {
            return 'direct';
        }

        return match (true) {
            str_contains($host, 'instagram.') => 'instagram',
            str_contains($host, 'facebook.') || str_contains($host, 'fb.') => 'facebook',
            str_contains($host, 'tiktok.') => 'tiktok',
            str_contains($host, 'google.') => 'google',
            str_contains($host, 'bing.') || str_contains($host, 'yahoo.') || str_contains($host, 'duckduckgo.') => 'bing',
            str_contains($host, 'whatsapp.') || str_contains($host, 'wa.me') => 'whatsapp',
            str_contains($host, 't.co') || str_contains($host, 'telegram.') => 'telegram',
            str_contains($host, 'youtube.') || str_contains($host, 'youtu.be') => 'youtube',
            str_contains($host, 'tokopedia.') || str_contains($host, 'shopee.') || str_contains($host, 'bukalapak.')
                || str_contains($host, 'lazada.') || str_contains($host, 'blibli.') => 'marketplace',
            default => 'referral',
        };
    }

    private function impliedMedium(string $source): ?string
    {
        return match ($source) {
            'meta_ads', 'google_ads', 'tiktok_ads' => 'paid',
            'instagram', 'facebook', 'tiktok', 'youtube' => 'social',
            'google', 'bing' => 'organic',
            'whatsapp', 'telegram' => 'chat',
            'direct' => 'none',
            default => 'referral',
        };
    }

    private function isPaidMedium(string $medium): bool
    {
        return in_array($medium, ['cpc', 'ppc', 'paid', 'paidsocial', 'paid_social', 'ads', 'cpm'], true);
    }

    /** Referring host without "www.", ignoring our own domain (internal nav). */
    private function referrerHost(Request $request): ?string
    {
        $referrer = (string) $request->headers->get('referer');
        if ($referrer === '') {
            return null;
        }

        $host = mb_strtolower((string) parse_url($referrer, PHP_URL_HOST));
        if ($host === '') {
            return null;
        }

        $host = preg_replace('/^www\./', '', $host);
        $self = preg_replace('/^www\./', '', mb_strtolower((string) parse_url(config('app.url'), PHP_URL_HOST)));

        return ($self !== '' && $host === $self) ? null : Str::limit($host, 120, '');
    }

    private function clean(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(strip_tags($value));

        return $value === '' ? null : $value;
    }

    public static function label(string $source): string
    {
        return self::LABELS[$source] ?? ucfirst(str_replace('_', ' ', $source));
    }

    public static function color(string $source): string
    {
        return self::COLORS[$source] ?? 'bg-gray-300';
    }
}
