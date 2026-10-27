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
        'gap_seconds' => 30,          // jeda antar pesan (detik); >60 = 1 pesan tiap N detik, dibulatkan ke menit cron
        'per_day' => 200,             // maks. pesan promo per hari (semua campaign)
        'min_gap_days' => 7,          // maks. 1 promo / kontak / 7 hari lintas campaign
        'failure_pause_after' => 5,   // jeda otomatis setelah N kegagalan beruntun
        'max_attempts' => 3,          // retry terbatas untuk error sementara
        'footer' => 'Balas STOP untuk berhenti promo.',
        'test_phone' => '',
        'connection_type' => 'qr',    // qr (WhatsApp biasa via scan QR) | cloud (WhatsApp Cloud API resmi Meta)
        'emergency_stop' => false,
        'send_days' => '1,2,3,4,5,6', // ISO: 1 Senin … 7 Minggu
        'stop_pause_percent' => 3,    // jeda otomatis bila balasan STOP > N% dari pesan terkirim (min. 30 terkirim); 0 = mati
        'stop_pause_percent_permission' => 10, // idem untuk pesan minta izin (STOP memang salah satu jawaban yang ditawarkan)
        'sales_names' => '',          // "KODE=Nama" per baris, untuk placeholder {sales}
    ];

    /** Minimal pesan terkirim sebelum persentase STOP dinilai. */
    public const STOP_RATE_MIN_SENT = 30;

    public function __construct(private readonly SettingService $settings) {}

    public function get(string $key): mixed
    {
        $value = $this->settings->get('wa_campaign.'.$key, self::DEFAULTS[$key] ?? null);
        if ($key === 'gap_seconds' && $this->settings->get('wa_campaign.gap_seconds') === null) {
            // Kompatibilitas pengaturan lama "pesan per menit" → jeda detik.
            $perMinute = (int) $this->settings->get('wa_campaign.per_minute', 0);
            $value = $perMinute > 0 ? intdiv(60, $perMinute) : $value;
        }

        return match ($key) {
            'gap_seconds' => max(1, (int) $value),
            'per_day', 'min_gap_days', 'failure_pause_after', 'max_attempts', 'stop_pause_percent', 'stop_pause_percent_permission' => max(0, (int) $value),
            'emergency_stop' => filter_var($value, FILTER_VALIDATE_BOOL),
            default => $value,
        };
    }

    public function set(string $key, mixed $value): void
    {
        $type = match ($key) {
            'gap_seconds', 'per_day', 'min_gap_days', 'failure_pause_after', 'max_attempts', 'stop_pause_percent', 'stop_pause_percent_permission' => 'integer',
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

    /** Jumlah pesan per putaran dispatcher (cron tiap menit) dari jeda antar pesan. */
    public function perRun(): int
    {
        return max(1, intdiv(60, $this->get('gap_seconds')));
    }

    /** Nama sales untuk placeholder {sales} dari kode ridlabs; "*=Nama" = semua kode lain; tanpa padanan → "Admin". */
    public function salesName(?string $code): string
    {
        $default = 'Admin';
        foreach (preg_split('/\R/', (string) $this->get('sales_names')) as $line) {
            [$k, $v] = array_pad(array_map('trim', explode('=', $line, 2)), 2, '');
            if ($k === '*' && $v !== '') {
                $default = $v; // "*=Nama" = untuk semua kode yang tidak tercantum
            } elseif ($code !== null && $k !== '' && $v !== '' && strcasecmp($k, $code) === 0) {
                return $v;
            }
        }

        return $default;
    }

    public function footer(): string
    {
        return trim((string) $this->get('footer'));
    }
}
