<?php

namespace App\Services;

use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Ekspor pesanan LUNAS (diverifikasi Keuangan) yang berasal dari klik iklan Google
 * (gclid tersimpan di kunjungan) ke format "Offline Conversion Import" Google Ads:
 * Google Ads → Goals → Conversions → Uploads. Dengan ini Google mengoptimasi
 * iklan ke pengunjung yang benar-benar membayar, bukan sekadar klik.
 *
 * Format: baris 1 "Parameters:TimeZone=+0700", lalu header
 * Google Click ID, Conversion Name, Conversion Time, Conversion Value, Conversion Currency.
 * Waktu konversi = saat Keuangan memverifikasi (harus setelah waktu klik, maks. 90 hari).
 */
class GoogleAdsOfflineConversions
{
    public function __construct(private readonly SettingService $settings) {}

    public function conversionName(): string
    {
        return trim((string) $this->settings->get('marketing.google_ads_offline_name')) ?: 'Pesanan Lunas (offline)';
    }

    /** @return Collection<int, Order> pesanan lunas terverifikasi dengan gclid, diverifikasi dalam rentang tanggal */
    public function orders(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Order::with('siteVisit')
            ->revenue()
            ->whereHas('siteVisit', fn ($q) => $q->whereNotNull('gclid'))
            ->whereBetween('finance_verified_at', [$from->startOfDay(), $to->endOfDay()])
            ->orderBy('finance_verified_at')
            ->get();
    }

    /** @return list<list<string>> baris CSV (termasuk baris parameter & header) */
    public function rows(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $tz = CarbonImmutable::now(config('app.timezone'))->format('O'); // +0700
        $rows = [
            ['Parameters:TimeZone='.$tz, '', '', '', ''],
            ['Google Click ID', 'Conversion Name', 'Conversion Time', 'Conversion Value', 'Conversion Currency'],
        ];
        $name = $this->conversionName();
        foreach ($this->orders($from, $to) as $order) {
            $rows[] = [
                (string) $order->siteVisit->gclid,
                $name,
                $order->finance_verified_at->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                number_format((float) $order->grand_total, 0, '.', ''),
                'IDR',
            ];
        }

        return $rows;
    }

    public function csv(CarbonImmutable $from, CarbonImmutable $to): string
    {
        $out = fopen('php://temp', 'r+');
        foreach ($this->rows($from, $to) as $row) {
            fputcsv($out, $row, ',', '"', '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }
}
