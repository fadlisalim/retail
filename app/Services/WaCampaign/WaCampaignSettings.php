<?php

namespace App\Services\WaCampaign;

use App\Services\SettingService;
use Carbon\CarbonImmutable;

/**
 * Pengaturan pengiriman campaign (Admin → WA Campaign → Pengaturan). Semua angka
 * adalah batas internal yang bisa diubah — BUKAN jaminan bebas blokir WhatsApp.
 */
class WaCampaignSettings
{
    public const DEFAULTS = [
        'window_start' => '09:00',
        'window_end' => '18:00',
        'timezone' => 'Asia/Jakarta',
        'per_minute' => 4,            // maks. pesan per menit (jeda ±15 detik)
        'per_day' => 200,             // maks. pesan promo per hari (semua campaign)
        'min_gap_days' => 7,          // maks. 1 promo / kontak / 7 hari lintas campaign
        'failure_pause_after' => 5,   // jeda otomatis setelah N kegagalan beruntun
        'max_attempts' => 3,          // retry terbatas untuk error sementara
        'footer' => 'Balas STOP untuk berhenti promo.',
        'test_phone' => '',
        'connection_type' => 'qr',    // qr (WhatsApp biasa via scan QR) | cloud (WhatsApp Cloud API resmi Meta)
        'emergency_stop' => false,
        'send_days' => '1,2,3,4,5,6', // ISO: 1 Senin … 7 Minggu
    ];

    public function __construct(private readonly SettingService $settings) {}

    public function get(string $key): mixed
    {
        $value = $this->settings->get('wa_campaign.'.$key, self::DEFAULTS[$key] ?? null);

        return match ($key) {
            'per_minute', 'per_day', 'min_gap_days', 'failure_pause_after', 'max_attempts' => max(0, (int) $value),
            'emergency_stop' => filter_var($value, FILTER_VALIDATE_BOOL),
            default => $value,
        };
    }

    public function set(string $key, mixed $value): void
    {
        $type = match ($key) {
            'per_minute', 'per_day', 'min_gap_days', 'failure_pause_after', 'max_attempts' => 'integer',
            'emergency_stop' => 'boolean',
            default => 'string',
        };
        $this->settings->set('wa_campaign.'.$key, $value, $type, 'wa_campaign');
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_combine(array_keys(self::DEFAULTS), array_map(fn ($k) => $this->get($k), array_keys(self::DEFAULTS)));
    }

    public function emergencyStop(): bool
    {
        return $this->get('emergency_stop');
    }

    public function isCloudApi(): bool
    {
        return $this->get('connection_type') === 'cloud';
    }

    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->get('timezone') ?: 'Asia/Jakarta');
    }

    /** Di dalam jam kirim (default 09.00–18.00 WIB, Senin–Sabtu)? */
    public function withinWindow(?CarbonImmutable $at = null): bool
    {
        $at = ($at ?? $this->now())->timezone($this->get('timezone') ?: 'Asia/Jakarta');
        $days = array_filter(array_map('intval', explode(',', (string) $this->get('send_days'))));
        if ($days && ! in_array($at->dayOfWeekIso, $days, true)) {
            return false;
        }
        $time = $at->format('H:i');

        return $time >= $this->get('window_start') && $time < $this->get('window_end');
    }

    public function footer(): string
    {
        return trim((string) $this->get('footer'));
    }
}
