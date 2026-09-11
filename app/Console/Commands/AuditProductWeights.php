<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Audit berat & dimensi katalog — mencari angka yang bakal bikin ongkir
 * salah hitung: berat kosong, dimensi placeholder 20×20×20 dari seeder demo,
 * volumetrik jauh di atas berat aktual (kabel per meter dengan kotak 20 cm =
 * 1,3 kg/meter!), barang ≥ 50 kg yang belum diset kargo, aksesoris murah
 * dengan berat tidak wajar, dan varian "kembar" (isi/harga beda tapi berat
 * atau dimensinya sama semua). Hanya membaca — perbaikannya lewat Admin →
 * Produk atau Edit Cepat Produk.
 */
class AuditProductWeights extends Command
{
    protected $signature = 'product:audit-weight
        {--all : Sertakan produk draft/arsip}
        {--varian : Daftar semua varian beserta asalnya (seeder / dibuat admin) dan berat-dimensinya}';

    protected $description = 'Cari berat/dimensi produk yang janggal untuk perhitungan ongkir';

    private const DIVISOR = 6000;

    private const HEAVY_GRAMS = 50000;

    /** Pola SKU varian yang berasal dari seeder — sisanya dibuat admin lewat panel. */
    private const SEEDED_VARIANT_SKUS = [
        '/^PAKET-AMAL-\d+$/', '/^PH605-\d+KWP-\d+KWH$/', '/^PAKET-APEX300-B\d$/', '/^AURORA-ECHO-\d+$/',
        '/^AIKO-COMET2U-\d+$/', '/^KBL-PV-\dMM-BLK$/', '/^NYAF-4MM-(MERAH|HITAM)$/', '/^SUNTREE-SL7N-\d+A$/',
        '/^PANEL-BEKAS-\d+$/', '/^PNL-MONO-550-\d+wp$/', '/^BAT-LFP-5K-\d+kwh$/',
    ];

    public function handle(): int
    {
        $query = Product::with(['variants', 'bundleItems.component'])->orderBy('id');
        if (! $this->option('all')) {
            $query->where('status', 'published');
        }

        if ($this->option('varian')) {
            return $this->listVariants($query->where('product_type', 'variable')->get());
        }

        $rows = [];
        foreach ($query->get() as $product) {
            $issues = $this->auditProduct($product);
            if ($issues) {
                $rows[] = $this->row($product->id, $product->name, (int) $product->weight_grams, $this->dims($product), $issues);
            }

            foreach ($product->variants as $variant) {
                $issues = $this->auditVariant($product, $variant);
                if ($issues) {
                    $rows[] = $this->row(
                        $product->id.'/'.$variant->id,
                        $product->name.' — varian '.$variant->name,
                        $variant->weightGrams(),
                        $this->dims($variant),
                        $issues,
                    );
                }
            }
        }

        if (! $rows) {
            $this->info('Tidak ada berat/dimensi yang janggal.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Produk', 'Berat', 'Dimensi (cm)', 'Masalah'], $rows);
        $this->warn(count($rows).' baris perlu dicek — perbaiki lewat Admin → Produk → Edit (Berat & Dimensi / Kargo).');

        return self::SUCCESS;
    }

    /**
     * Daftar varian per produk: asal (seeder / admin), berat & dimensi efektif,
     * dan tanda bila angkanya cuma warisan induk (kolom varian kosong).
     *
     * @param  Collection<int, Product>  $products
     */
    private function listVariants($products): int
    {
        $rows = [];
        $admin = 0;
        foreach ($products as $product) {
            foreach ($product->variants->sortBy('sort_order') as $variant) {
                $seeded = collect(self::SEEDED_VARIANT_SKUS)->contains(fn ($re) => preg_match($re, (string) $variant->sku));
                $admin += $seeded ? 0 : 1;
                $rows[] = [
                    $product->id.'/'.$variant->id,
                    mb_strimwidth($product->name, 0, 40, '…'),
                    mb_strimwidth($variant->name.' ('.$variant->sku.')', 0, 42, '…'),
                    $seeded ? 'seeder' : 'ADMIN',
                    ($variant->is_active ? '' : '[nonaktif] ').number_format($variant->weightGrams() / 1000, 2, ',', '.').' kg'.($variant->weight_grams === null ? ' (induk)' : ''),
                    $this->dims($variant).($variant->length_cm === null && $variant->volumeCm3() > 0 ? ' (induk)' : ''),
                ];
            }
        }

        if (! $rows) {
            $this->info('Tidak ada produk bervarian.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Produk', 'Varian (SKU)', 'Asal', 'Berat', 'Dimensi (cm)'], $rows);
        $this->line(count($rows).' varian; '.$admin.' dibuat lewat admin (bukan seeder). "(induk)" = kolom varian kosong, memakai angka produk induk.');

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function auditProduct(Product $product): array
    {
        $weight = (int) $product->weight_grams;
        $l = (float) $product->length_cm;
        $w = (float) $product->width_cm;
        $h = (float) $product->height_cm;

        $issues = [];

        if ($weight <= 0) {
            $issues[] = 'berat kosong';
        }

        if ($l == 20.0 && $w == 20.0 && $h == 20.0) {
            $issues[] = 'dimensi placeholder 20×20×20 (isi ukuran asli)';
        } elseif ($l <= 0 || $w <= 0 || $h <= 0) {
            if ($weight >= 5000) {
                $issues[] = 'dimensi belum diisi';
            }
        }

        $issues = array_merge($issues, $this->volumetricIssues($weight, $l * $w * $h, $product->unit));

        if ($weight >= self::HEAVY_GRAMS && ! $product->requires_freight && ! $product->pickup_only && ! $product->requires_quotation) {
            $issues[] = sprintf('%s kg tapi belum diset kargo (requires_freight)', number_format($weight / 1000, 0, ',', '.'));
        }

        if ($weight > 200 && (float) $product->price < 30000 && $product->unit !== 'meter') {
            $issues[] = 'aksesoris murah tapi berat > 200 g — cek timbangan';
        }

        return array_merge($issues, $this->twinVariantIssues($product), $this->bundleIssues($product));
    }

    /**
     * Paket tipe bundle: berat paket harus mendekati jumlah berat komponennya
     * (kasus paket demo: 150 kg semua padahal komponennya 246 kg).
     *
     * @return list<string>
     */
    private function bundleIssues(Product $product): array
    {
        if ($product->product_type !== 'bundle' || $product->bundleItems->isEmpty()) {
            return [];
        }

        $components = (int) $product->bundleItems->sum(fn ($item) => (int) ($item->component?->weight_grams ?? 0) * (int) $item->quantity);
        $weight = (int) $product->weight_grams;
        if ($components <= 0 || abs($weight - $components) <= max(5000, $components * 0.2)) {
            return [];
        }

        return [sprintf(
            'berat paket %s kg vs total komponen %s kg (%d komponen) — samakan dengan isi paket',
            number_format($weight / 1000, 1, ',', '.'),
            number_format($components / 1000, 1, ',', '.'),
            $product->bundleItems->count(),
        )];
    }

    /**
     * Varian "kembar": isinya beda (harga jauh berbeda) tapi berat semua varian
     * sama, atau beratnya beda jauh tapi dimensinya sama — tanda angka jatuh ke
     * induk, bukan diisi per varian (kasus Paket Amal: 3 varian 80 kg semua).
     *
     * @return list<string>
     */
    private function twinVariantIssues(Product $product): array
    {
        $variants = $product->variants->where('is_active', true)->values();
        if ($variants->count() < 2) {
            return [];
        }

        $issues = [];
        $weights = $variants->map(fn (ProductVariant $v) => $v->weightGrams());
        $prices = $variants->map(fn (ProductVariant $v) => (float) ($v->sale_price ?: $v->price))->filter(fn ($p) => $p > 0);
        $priceSpread = $prices->count() >= 2 && $prices->min() > 0 ? $prices->max() / $prices->min() : 1.0;

        if ($weights->unique()->count() === 1 && $priceSpread >= 1.15) {
            $issues[] = sprintf(
                '%d varian beda harga (%s–%s) tapi beratnya sama semua (%s kg) — isi berat per varian',
                $variants->count(),
                $this->short($prices->min()),
                $this->short($prices->max()),
                number_format((int) $weights->first() / 1000, 1, ',', '.'),
            );
        }

        $dims = $variants->map(fn (ProductVariant $v) => $v->lengthCm().'×'.$v->widthCm().'×'.$v->heightCm());
        $weightSpread = $weights->min() > 0 ? $weights->max() / $weights->min() : 1.0;
        if ($dims->unique()->count() === 1 && $variants->first()->volumeCm3() > 0 && $weightSpread >= 1.3) {
            $issues[] = sprintf(
                '%d varian beratnya beda (%s–%s kg) tapi dimensinya sama semua (%s) — isi dimensi per varian',
                $variants->count(),
                number_format($weights->min() / 1000, 1, ',', '.'),
                number_format($weights->max() / 1000, 1, ',', '.'),
                $dims->first(),
            );
        }

        return $issues;
    }

    private function short(float $rupiah): string
    {
        return $rupiah >= 1_000_000
            ? rtrim(rtrim(number_format($rupiah / 1_000_000, 1, ',', '.'), '0'), ',').' jt'
            : number_format($rupiah / 1000, 0, ',', '.').' rb';
    }

    /** @return list<string> */
    private function auditVariant(Product $product, ProductVariant $variant): array
    {
        $issues = [];

        if ($variant->weight_grams === null && $product->product_type === 'variable' && $product->unit === 'paket') {
            $issues[] = 'varian paket memakai berat induk ('.number_format((int) $product->weight_grams / 1000, 1, ',', '.').' kg) — isi berat per varian';
        }

        // Varian dengan berat sendiri tapi dimensinya jatuh ke induk 20×20×20.
        if ($variant->weight_grams !== null && $variant->length_cm === null
            && (float) $product->length_cm == 20.0 && (float) $product->width_cm == 20.0 && (float) $product->height_cm == 20.0) {
            $issues[] = 'dimensi varian jatuh ke placeholder induk 20×20×20';
        }

        $issues = array_merge($issues, $this->volumetricIssues($variant->weightGrams(), $variant->volumeCm3(), $product->unit));

        return $issues;
    }

    /** @return list<string> */
    private function volumetricIssues(int $weight, float $volumeCm3, ?string $unit): array
    {
        if ($weight <= 0 || $volumeCm3 <= 0) {
            return [];
        }

        $volumetric = (int) round($volumeCm3 / self::DIVISOR * 1000);
        // Kurir menagih yang lebih besar; selisih kecil wajar (kemasan), tapi
        // 2× lipat berarti dimensinya salah — apalagi untuk barang per meter.
        if ($volumetric > max($weight * 2, $weight + 1000)) {
            return [sprintf(
                'volumetrik %s kg vs berat %s kg%s — ongkir ikut volumetrik',
                number_format($volumetric / 1000, 2, ',', '.'),
                number_format($weight / 1000, 2, ',', '.'),
                $unit === 'meter' ? ' PER METER' : '',
            )];
        }

        return [];
    }

    private function dims(Product|ProductVariant $item): string
    {
        $l = $item instanceof ProductVariant ? $item->lengthCm() : (float) $item->length_cm;
        $w = $item instanceof ProductVariant ? $item->widthCm() : (float) $item->width_cm;
        $h = $item instanceof ProductVariant ? $item->heightCm() : (float) $item->height_cm;

        return ($l > 0 && $w > 0 && $h > 0) ? $l.'×'.$w.'×'.$h : '—';
    }

    /** @param list<string> $issues */
    private function row(string|int $id, string $name, int $weight, string $dims, array $issues): array
    {
        return [
            $id,
            mb_strimwidth($name, 0, 60, '…'),
            $weight >= 1000 ? number_format($weight / 1000, 2, ',', '.').' kg' : $weight.' g',
            $dims,
            implode('; ', $issues),
        ];
    }
}
