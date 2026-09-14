<?php

namespace App\Console\Commands;

use App\Services\Shopee\MassUploadExporter;
use Illuminate\Console\Command;

/**
 * Ekspor katalog ke file mass upload Shopee (template basic). Hasil default:
 * storage/app/shopee-YYYYMMDD-HHMM.xlsx — unduh lalu upload di Seller Centre
 * → Produk Saya → Mass Upload.
 */
class ShopeeExport extends Command
{
    protected $signature = 'shopee:export {--out= : Path file .xlsx hasil (default storage/app/shopee-<tanggal>.xlsx)}';

    protected $description = 'Ekspor produk ke template Mass Upload Shopee';

    public function handle(MassUploadExporter $exporter): int
    {
        $out = $this->option('out') ?: storage_path('app/shopee-'.now()->format('Ymd-Hi').'.xlsx');

        $count = $exporter->write($out);

        $this->info("{$count} baris ditulis ke {$out}");

        if ($exporter->skipped) {
            $this->line('');
            $this->warn(count($exporter->skipped).' produk dilewati:');
            foreach ($exporter->skipped as $s) {
                $this->line('  - '.$s);
            }
        }
        if ($exporter->warnings) {
            $this->line('');
            $this->warn(count($exporter->warnings).' catatan (cek sebelum upload):');
            foreach ($exporter->warnings as $w) {
                $this->line('  - '.$w);
            }
        }

        $this->line('');
        $this->line('Upload: Seller Centre → Produk Saya → Mass Upload → pilih file ini. Kolom Kategori yang kosong akan direkomendasikan Shopee.');

        return self::SUCCESS;
    }
}
