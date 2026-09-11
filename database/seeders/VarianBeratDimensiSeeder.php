<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

/**
 * Audit varian (Sep 2026): produk bervarian yang variannya beda isi/harga tapi
 * berat & dimensinya "kembar" karena jatuh ke induk — Paket Amal (3 varian
 * 80 kg semua), Paket Bezvolt Power Home, Paket Apex 300, Aurora ECHO, dan
 * varian demo panel/baterai. Seeder ini mengisi berat/dimensi PER VARIAN
 * hanya bila kolomnya masih kosong (angka editan admin di Edit Cepat Produk
 * tidak ditimpa), plus jumlah kolli paket agar biaya forklift kargo tidak
 * salah hitung. Aman dijalankan berulang; SKU yang tidak ada dilewati.
 *
 * Dimensi paket = kotak setara TOTAL volume (bukan satu kolli), alas palet
 * panel 230×115 cm. Semua angka paket & ECHO-16 adalah estimasi kargo —
 * sesuaikan di Admin bila sudah ditimbang.
 */
class VarianBeratDimensiSeeder extends Seeder
{
    /** @var array<string, array{weight: int, dims: array{float, float, float}}> SKU varian => berat (g) & P×L×T (cm) */
    public const SHIPPING = [
        // Paket PLTS Amal: panel ±27,5 kg/pcs + baterai + inverter + proteksi + kabel + mounting.
        'PAKET-AMAL-2000' => ['weight' => 100000, 'dims' => [230, 115, 12]], // 2 panel, baterai 80Ah, inverter 1,2 kW
        'PAKET-AMAL-4000' => ['weight' => 150000, 'dims' => [230, 115, 17]], // 3 panel, baterai 160Ah, inverter 1,6 kW
        'PAKET-AMAL-8000' => ['weight' => 290000, 'dims' => [230, 115, 31]], // 6 panel, 2 baterai 160Ah, inverter 3 kW

        // Paket Bezvolt Power Home 6000W: inverter 6 kW + baterai 5 kWh (±0,13 m³) + panel 605 Wp (±0,12 m³/pcs incl. mounting).
        'PH605-0KWP-5KWH' => ['weight' => 80000, 'dims' => [60, 50, 45]],
        'PH605-2KWP-5KWH' => ['weight' => 230000, 'dims' => [230, 115, 23]],
        'PH605-3KWP-5KWH' => ['weight' => 305000, 'dims' => [230, 115, 28]],
        'PH605-3KWP-10KWH' => ['weight' => 355000, 'dims' => [230, 115, 30]],
        'PH605-4KWP-10KWH' => ['weight' => 430000, 'dims' => [230, 115, 38]],
        'PH605-5KWP-15KWH' => ['weight' => 555000, 'dims' => [230, 115, 49]],

        // Paket BLUETTI Apex 300 + 2 panel 600 Wp: +1 B300K ≈ +29,5 kg / +0,035 m³.
        'PAKET-APEX300-B0' => ['weight' => 85000, 'dims' => [230, 115, 11]],
        'PAKET-APEX300-B1' => ['weight' => 114500, 'dims' => [230, 115, 12]],
        'PAKET-APEX300-B2' => ['weight' => 144000, 'dims' => [230, 115, 14]],
        'PAKET-APEX300-B3' => ['weight' => 173500, 'dims' => [230, 115, 15]],

        // Aurora ECHO all-in-one (dimensi katalog pabrikan; ECHO-16 estimasi).
        'AURORA-ECHO-1' => ['weight' => 14000, 'dims' => [23, 18.7, 30.7]],
        'AURORA-ECHO-2' => ['weight' => 22500, 'dims' => [28.4, 28, 30.7]],
        'AURORA-ECHO-8' => ['weight' => 82500, 'dims' => [54, 27, 84]],
        'AURORA-ECHO-16' => ['weight' => 185000, 'dims' => [60, 40, 120]],

        // Varian demo katalog awal (bila masih ada di produksi).
        'PNL-MONO-550-450wp' => ['weight' => 22500, 'dims' => [190, 105, 3.5]],
        'PNL-MONO-550-550wp' => ['weight' => 27500, 'dims' => [227, 113, 3.5]],
        'PNL-MONO-550-600wp' => ['weight' => 30000, 'dims' => [238, 113, 3.5]],
        'BAT-LFP-5K-512kwh' => ['weight' => 48000, 'dims' => [44, 42, 22]],
        'BAT-LFP-5K-1024kwh' => ['weight' => 92000, 'dims' => [44, 42, 44]], // 2 modul ditumpuk
    ];

    /** @var array<string, int> SKU produk paket => jumlah kolli (untuk aturan forklift kargo) */
    public const PACKAGE_COUNT = [
        'PAKET-AMAL' => 6,              // palet panel, baterai, inverter, proteksi, kabel, mounting
        'PAKET-PH605-SOLAR' => 4,       // palet panel, baterai, inverter, mounting+aksesoris
        'PAKET-APEX300-SOLAR1200' => 4, // Apex 300, B300K, palet panel, bracket+kabel
        'PAKET-ECHO8-4KWP' => 4,        // ECHO-8, palet panel, mounting, proteksi+kabel
    ];

    public function run(): void
    {
        $filled = 0;
        foreach (self::SHIPPING as $sku => $shipping) {
            $variant = ProductVariant::where('sku', $sku)->first();
            if ($variant && self::fillVariant($variant, $shipping)) {
                $filled++;
            }
        }

        $kolli = 0;
        foreach (self::PACKAGE_COUNT as $sku => $count) {
            $product = Product::where('sku', $sku)->first();
            if ($product && (int) $product->package_count <= 1 && $count > 1) {
                $product->forceFill(['package_count' => $count])->save();
                $kolli++;
            }
        }

        $this->command?->info("Berat/dimensi varian diisi: {$filled} varian; jumlah kolli paket diisi: {$kolli} produk (yang sudah diisi admin dibiarkan).");
        $this->command?->warn('Cek hasilnya: php artisan product:audit-weight');
    }

    /**
     * Isi berat/dimensi varian hanya bila masih kosong (jatuh ke induk).
     *
     * @param  array{weight: int, dims: array{float, float, float}}  $shipping
     */
    public static function fillVariant(ProductVariant $variant, array $shipping): bool
    {
        $fill = [];
        if ($variant->weight_grams === null) {
            $fill['weight_grams'] = $shipping['weight'];
        }
        if ($variant->length_cm === null && $variant->width_cm === null && $variant->height_cm === null) {
            [$fill['length_cm'], $fill['width_cm'], $fill['height_cm']] = $shipping['dims'];
        }
        if (! $fill) {
            return false;
        }
        $variant->forceFill($fill)->save();

        return true;
    }
}
