<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Console\Command;

/**
 * Audit berat & dimensi katalog — mencari angka yang bakal bikin ongkir
 * salah hitung: berat kosong, dimensi placeholder 20×20×20 dari seeder demo,
 * volumetrik jauh di atas berat aktual (kabel per meter dengan kotak 20 cm =
 * 1,3 kg/meter!), barang ≥ 50 kg yang belum diset kargo, dan aksesoris murah
 * dengan berat tidak wajar. Hanya membaca — perbaikannya lewat Admin → Produk.
 */
class AuditProductWeights extends Command
{
    protected $signature = 'product:audit-weight {--all : Sertakan produk draft/arsip}';

    protected $description = 'Cari berat/dimensi produk yang janggal untuk perhitungan ongkir';

    private const DIVISOR = 6000;

    private const HEAVY_GRAMS = 50000;

    public function handle(): int
    {
        $query = Product::with('variants')->orderBy('id');
        if (! $this->option('all')) {
            $query->where('status', 'published');
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

        return $issues;
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
