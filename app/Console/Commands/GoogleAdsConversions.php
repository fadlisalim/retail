<?php

namespace App\Console\Commands;

use App\Services\GoogleAdsOfflineConversions;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/** CSV konversi offline Google Ads (pesanan lunas terverifikasi Keuangan + gclid). */
class GoogleAdsConversions extends Command
{
    protected $signature = 'googleads:konversi
        {--dari= : Tanggal verifikasi awal (Y-m-d, default 30 hari lalu)}
        {--sampai= : Tanggal verifikasi akhir (Y-m-d, default hari ini)}
        {--out= : Simpan ke file (default: tampilkan di layar)}';

    protected $description = 'Ekspor pesanan lunas dari klik iklan Google ke CSV Offline Conversion Import';

    public function handle(GoogleAdsOfflineConversions $export): int
    {
        $to = $this->option('sampai') ? CarbonImmutable::parse($this->option('sampai')) : CarbonImmutable::today();
        $from = $this->option('dari') ? CarbonImmutable::parse($this->option('dari')) : $to->subDays(30);

        $csv = $export->csv($from, $to);
        $count = max(0, substr_count(trim($csv), "\n") - 1);

        if ($out = $this->option('out')) {
            file_put_contents($out, $csv);
            $this->info("{$count} konversi ({$from->format('d/m/Y')} – {$to->format('d/m/Y')}) disimpan ke {$out}. Unggah di Google Ads → Goals → Conversions → Uploads.");
        } else {
            $this->line($csv);
            $this->info("{$count} konversi. Konversi offline hanya diterima Google maks. 90 hari setelah klik — unggah rutin (mis. mingguan).");
        }

        return self::SUCCESS;
    }
}
