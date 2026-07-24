<?php

namespace Database\Seeders;

use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\WarehouseStock;
use App\Services\StockService;
use Illuminate\Database\Seeder;

/**
 * Upgrades the existing Aurora Echo listing (slug aurora-echo-power-station)
 * to the official "All In One Solar Generator ECHO Series" datasheet:
 *  - Repriced from the supplier cost sheet +20% margin:
 *      ECHO-1 (500W / 1kWh)  : modal 5.800.000  → jual 6.960.000
 *      ECHO-2 (1kW / 2kWh)   : modal 8.700.000  → jual 10.440.000
 *      ECHO-8 (4kVA / 8kWh)  : modal 26.575.000 → jual 31.890.000 (varian BARU)
 *  - Specs replaced with the official Aurora datasheet values.
 * Idempotent: absolute values, safe to re-run. ECHO-8 initial stock 5
 * (mengikuti stok lini Echo sebelumnya — sesuaikan di Admin bila beda).
 */
class AuroraEchoAllInOneSeeder extends Seeder
{
    public function run(): void
    {
        $product = Product::where('slug', 'aurora-echo-power-station')->first();
        if (! $product) {
            $this->command?->warn('Produk aurora-echo-power-station tidak ditemukan — jalankan AuroraEchoSeeder dulu.');

            return;
        }

        $description = <<<'HTML'
<p><strong>Aurora ECHO Series — All In One Solar Generator.</strong> Satu unit berisi <strong>inverter Pure Sine Wave + baterai LiFePO4 + solar charger + BMS pintar</strong>: tinggal colok panel surya, langsung jadi sistem listrik mandiri. Baterai <strong>Grade A ≥8.000 siklus</strong> (umur sel ±10 tahun), garansi resmi 2 tahun.</p>
<h4>Pilih Kapasitas (Varian)</h4>
<ul>
<li>⛺ <strong>ECHO-1 · 500W / 1 kWh</strong> — plug &amp; play, 13 kg. Camping, jualan outdoor, backup lampu + HP + laptop.</li>
<li>🚐 <strong>ECHO-2 · 1.000W / 2 kWh</strong> — campervan, backup rumah kecil (kulkas + lampu + TV), beban lebih besar.</li>
<li>🏠 <strong>ECHO-8 · 4.000VA / 8 kWh</strong> — tower lantai ber-roda dengan <strong>MPPT besar (PV hingga 5.000W)</strong>: jantung PLTS rumah — backup hampir seisi rumah, siap sistem off-grid/hybrid.</li>
</ul>
<h4>Keunggulan</h4>
<ul>
<li>🔋 <strong>LiFePO4 Grade A, ≥8.000 siklus, DOD 90%</strong> — jauh lebih awet dari Li-ion biasa.</li>
<li>🌊 <strong>Pure Sine Wave</strong> — aman untuk elektronik sensitif (laptop, kamera, alat medis).</li>
<li>☀️ <strong>Solar-ready</strong> — ECHO-1/2 tinggal colok panel (300W / 550W); ECHO-8 pakai MPPT 120–500VDC hingga 5.000W.</li>
<li>🛡️ <strong>BMS pintar</strong> + sertifikasi (EN-IEC / UN38.3), proteksi lengkap.</li>
<li>✅ <strong>Garansi resmi 2 tahun</strong>, after-sales by Rekasurya.</li>
</ul>
<p><em>Bingung pilih kapasitas? Konsultasi gratis — admin bantu hitung kebutuhan daya Kakak. Harga belum termasuk panel surya &amp; ongkir (admin konfirmasi total sebelum pembayaran).</em></p>
HTML;

        $specifications = <<<'HTML'
<table><tbody>
<tr><th>Spesifikasi</th><th>ECHO-1</th><th>ECHO-2</th><th>ECHO-8</th></tr>
<tr><th>Daya Output (AC)</th><td>500 W</td><td>1.000 W</td><td>4.000 VA</td></tr>
<tr><th>Kapasitas Baterai</th><td>1 kWh</td><td>2 kWh</td><td>8 kWh</td></tr>
<tr><th>Input PV Maks</th><td>300 W (11–55VDC)</td><td>550 W (11–55VDC)</td><td>5.000 W (MPPT 120–500VDC)</td></tr>
<tr><th>Output AC</th><td colspan="2">50 Hz · 220/230 Vac · single-phase</td><td>50/60 Hz · 220/230 Vac · single-phase</td></tr>
<tr><th>Tipe Baterai</th><td colspan="3">LiFePO4 Grade A · 314 Ah · ≥8.000 siklus (umur sel ±10 thn)</td></tr>
<tr><th>DOD / BMS</th><td colspan="3">DOD 90% · Built-in smart BMS</td></tr>
<tr><th>Bentuk / Instalasi</th><td>Portable, bebas instalasi</td><td>Portable, bebas instalasi</td><td>Tower lantai ber-roda (movable)</td></tr>
<tr><th>Suhu Operasi</th><td colspan="2">-10°C s/d +40°C</td><td>Charge 0~55°C · Discharge -10~55°C</td></tr>
<tr><th>Sertifikasi</th><td colspan="2">EN-IEC 60335-1, EN-IEC 60335-2-29, IEC 62109-1</td><td>UN38.3</td></tr>
<tr><th>Dimensi</th><td>230×187×307 mm</td><td>284×280×307 mm</td><td>540×270×770 mm (840 mm dgn roda)</td></tr>
<tr><th>Berat Unit</th><td>±13 kg</td><td>±21,2 kg</td><td>±72,5 kg</td></tr>
<tr><th>Garansi</th><td colspan="3">2 Tahun (0.5C charge/discharge @25°C, 80% DOD)</td></tr>
</tbody></table>
HTML;

        $product->forceFill([
            'name' => 'Aurora ECHO All-in-One Solar Generator (Inverter + Baterai LiFePO4)',
            'short_description' => 'Aurora ECHO Series: inverter + baterai LiFePO4 + solar charger dalam satu unit. Pilih ECHO-1 (500W/1kWh), ECHO-2 (1kW/2kWh), atau ECHO-8 (4kVA/8kWh, MPPT 5.000W). Garansi resmi 2 tahun.',
            'description' => $description,
            'specifications' => $specifications,
            'price' => 6960000,
            'sale_price' => null,
            'is_promo' => false,
            'weight_grams' => 14000,
            'warranty' => 'Garansi 2 Tahun',
            'keywords' => 'aurora, echo, all in one, solar generator, power station, inverter baterai, lifepo4, echo-1, echo-2, echo-8, plts rumah, backup listrik',
            'meta_title' => 'Aurora ECHO All-in-One Solar Generator — ECHO-1 / ECHO-2 / ECHO-8 (LiFePO4)',
            'meta_description' => 'Jual Aurora ECHO Series all-in-one solar generator: ECHO-1 500W/1kWh Rp 6.960.000, ECHO-2 1kW/2kWh Rp 10.440.000, ECHO-8 4kVA/8kWh Rp 31.890.000. LiFePO4 ≥8.000 siklus, garansi 2 tahun.',
        ])->save();

        // Also surface under Paket PLTS → Off-Grid (it's an off-grid-ready system).
        $extra = Category::where('slug', 'paket-plts-off-grid')->first();
        $ids = collect([$product->category_id, $extra?->id])->filter()->unique()->all();
        $product->categories()->syncWithoutDetaching($ids);

        $variants = [
            ['sku' => 'AURORA-ECHO-1', 'name' => 'ECHO-1 · 500W / 1 kWh', 'price' => 6960000, 'weight' => 14000],
            ['sku' => 'AURORA-ECHO-2', 'name' => 'ECHO-2 · 1.000W / 2 kWh', 'price' => 10440000, 'weight' => 22500],
            ['sku' => 'AURORA-ECHO-8', 'name' => 'ECHO-8 · 4.000VA / 8 kWh', 'price' => 31890000, 'weight' => 82500],
        ];

        foreach ($variants as $i => $v) {
            $variant = ProductVariant::updateOrCreate(
                ['sku' => $v['sku']],
                [
                    'product_id' => $product->id,
                    'name' => $v['name'],
                    'option_values' => ['Unit' => $v['name']],
                    'price' => $v['price'],
                    'sale_price' => null,
                    'weight_grams' => $v['weight'],
                    'is_active' => true,
                    'sort_order' => $i,
                ],
            );

            // Only seed stock for the NEW ECHO-8 variant; existing stock is
            // admin-managed and must not be reset here.
            if ($variant->wasRecentlyCreated) {
                $this->setVariantStock($product, $variant, 5);
            }
        }

        $this->command?->info('Aurora ECHO diperbarui: ECHO-1 Rp 6.960.000 · ECHO-2 Rp 10.440.000 · ECHO-8 Rp 31.890.000 (modal +20%).');
        $this->command?->warn('Stok awal ECHO-8 = 5 (asumsi, sesuaikan di Admin). Upload foto ECHO-8 lewat Admin → Produk.');
    }

    private function setVariantStock(Product $product, ProductVariant $variant, int $target): void
    {
        $current = (int) WarehouseStock::where('product_variant_id', $variant->id)->sum('quantity_available');
        $delta = $target - $current;
        if ($delta !== 0) {
            app(StockService::class)->adjust($product, $variant, $delta, StockMovementType::Purchase, note: 'Stok awal (seeder)');
        }
    }
}
