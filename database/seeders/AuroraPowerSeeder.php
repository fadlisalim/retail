<?php

namespace Database\Seeders;

use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\WarehouseStock;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

/**
 * Seeds the Aurora Power price list (Agustus 2026): 10 inverter hybrid 1 fase,
 * 9 inverter hybrid 3 fase, dan 9 baterai LiFePO4 — plus penyesuaian harga lini
 * ECHO yang sudah tayang.
 *
 * Harga jual = modal ÷ 0,8 (margin 20% dari revenue), dibulatkan ke ATAS ke
 * kelipatan 50.000. Perhatikan: lini ECHO sebelumnya dihargai modal × 1,2
 * (markup 20% dari MODAL = margin 16,7%), jadi konvensinya berbeda dan harganya
 * ikut disesuaikan di sini. Harga modal tidak ditulis di file ini.
 *
 * Spesifikasi berasal dari katalog resmi "2026 Solar System" (ShenZhen YuFai
 * Aurora Opto / GuangDong Sunlux New Energy). Dua item tidak ada di katalog itu
 * — YF-LFP-5KWH-24V dan berat beberapa model — sehingga ditandai terbuka
 * sebagai "menyusul" atau "estimasi", bukan dikarang. Idempotent: baris yang
 * masih memuat penampung akan diperbarui saat data pabrikan masuk, tetapi
 * baris yang sudah disunting admin tidak disentuh.
 */
class AuroraPowerSeeder extends Seeder
{
    private const PLACEHOLDER = 'Menyusul dari pabrikan';

    /** Harga ECHO baru (modal ÷ 0,8). Lihat catatan konvensi di docblock. */
    private const ECHO_PRICES = [
        'AURORA-ECHO-1' => 7050000,
        'AURORA-ECHO-2' => 10700000,
        'AURORA-ECHO-8' => 37450000,
        'AURORA-ECHO-16' => 66600000,
    ];

    public function run(): void
    {
        $brand = Brand::firstOrCreate(['slug' => 'aurora'], ['name' => 'Aurora', 'is_active' => true]);

        $created = 0;
        $upgraded = 0;
        $skipped = 0;

        foreach ($this->products() as $row) {
            $categories = array_values(array_filter(array_map(
                fn (string $slug) => Category::where('slug', $slug)->value('id'),
                $row['categories'],
            )));

            // Kolom dimensi tidak menerima NULL, jadi model tanpa data dimensi
            // dibiarkan memakai nilai bawaan tabel.
            $attributes = array_filter(
                Arr::except($row, ['slug', 'categories', 'stock']),
                fn ($value, $key) => ! (in_array($key, ['length_cm', 'width_cm', 'height_cm'], true) && $value === null),
                ARRAY_FILTER_USE_BOTH,
            );

            $product = Product::firstOrCreate(
                ['slug' => $row['slug']],
                $attributes + [
                    'category_id' => $categories[0] ?? null,
                    'brand_id' => $brand->id,
                    'product_type' => 'simple',
                    'condition' => 'new',
                    'unit' => 'unit',
                    'status' => 'published',
                    'is_new' => true,
                    'published_at' => now(),
                    'estimated_processing' => '3-7 hari kerja',
                ],
            );

            if ($product->wasRecentlyCreated) {
                $product->categories()->sync($categories);
                $this->setStock($product, $row['stock']);
                $created++;
            } elseif ($this->upgradePlaceholder($product, $row)) {
                $upgraded++;
            } else {
                $skipped++;
            }
        }

        $this->command?->info("Produk Aurora Power: {$created} ditambahkan, {$upgraded} diperbarui, {$skipped} dilewati (sudah ada).");

        $this->syncEchoPrices();

        $this->command?->warn('Harga BELUM termasuk PPN & ongkir. Berat yang ditandai "estimasi" belum dirilis pabrikan — sesuaikan di Admin bila sudah ada angkanya.');
    }

    /**
     * Ketika data resmi akhirnya datang, baris yang masih memuat penampung
     * diganti dengan data sungguhan. Baris yang sudah disunting admin (tidak
     * lagi memuat penampung) TIDAK disentuh.
     */
    private function upgradePlaceholder(Product $product, array $row): bool
    {
        if (! str_contains((string) $product->specifications, self::PLACEHOLDER)
            || str_contains($row['specifications'], self::PLACEHOLDER)) {
            return false;
        }

        $product->forceFill(array_filter(Arr::only($row, [
            'short_description', 'description', 'specifications',
            'weight_grams', 'length_cm', 'width_cm', 'height_cm',
            'warranty', 'keywords', 'meta_title', 'meta_description',
        ]), fn ($value) => $value !== null))->save();

        return true;
    }

    /**
     * Menyesuaikan harga lini ECHO yang sudah tayang ke margin 20% dan
     * menambahkan varian ECHO-16. Dilewati bila produk induknya belum ada.
     */
    private function syncEchoPrices(): void
    {
        $product = Product::where('slug', 'aurora-echo-power-station')->first();

        if (! $product) {
            $this->command?->warn('Lini ECHO dilewati: produk aurora-echo-power-station belum ada di database ini.');

            return;
        }

        foreach (['AURORA-ECHO-1', 'AURORA-ECHO-2', 'AURORA-ECHO-8'] as $sku) {
            $variant = ProductVariant::where('sku', $sku)->where('product_id', $product->id)->first();
            if ($variant && (float) $variant->price !== (float) self::ECHO_PRICES[$sku]) {
                $this->command?->line(sprintf(
                    '  %s: Rp %s → Rp %s',
                    $sku,
                    number_format((float) $variant->price, 0, ',', '.'),
                    number_format(self::ECHO_PRICES[$sku], 0, ',', '.'),
                ));
                $variant->forceFill(['price' => self::ECHO_PRICES[$sku], 'sale_price' => null])->save();
            }
        }

        $echo16 = ProductVariant::where('sku', 'AURORA-ECHO-16')->first();
        if (! $echo16) {
            $echo16 = ProductVariant::create([
                'product_id' => $product->id,
                'sku' => 'AURORA-ECHO-16',
                'name' => 'ECHO-16 · 10.000W / 16 kWh',
                'option_values' => ['Unit' => 'ECHO-16 · 10.000W / 16 kWh'],
                'price' => self::ECHO_PRICES['AURORA-ECHO-16'],
                'weight_grams' => 185000,   // katalog: 185 kg ±1 kg
                'is_active' => true,
                'sort_order' => 3,
            ]);
            $this->setVariantStock($product, $echo16, 2);
            $this->command?->line('  AURORA-ECHO-16 ditambahkan: Rp '.number_format(self::ECHO_PRICES['AURORA-ECHO-16'], 0, ',', '.'));
        }

        // Harga "mulai dari" pada produk induk mengikuti varian termurah.
        $product->forceFill(['price' => self::ECHO_PRICES['AURORA-ECHO-1'], 'sale_price' => null])->save();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function products(): array
    {
        return array_merge($this->highFrequencyInverters(), $this->transformerInverters(), $this->threePhaseInverters(), $this->batteries());
    }

    /**
     * Hybrid high-frequency 1 fase. Seri "-D" adalah versi hemat biaya:
     * TANPA filter EMC dan TANPA fungsi paralel — itulah sebabnya 11048-D
     * lebih murah daripada 11048 biasa meski dayanya sama.
     */
    private function highFrequencyInverters(): array
    {
        return [
            $this->inverterHf([
                'sku' => 'YF-FS-HP-1612-D', 'slug' => 'inverter-hybrid-aurora-yf-fs-hp-1612-d-1600w-12v',
                'label' => '1.600W 12V', 'watt' => '1.600 W', 'volt' => '12 VDC',
                'price' => 4550000, 'stock' => 4, 'weight' => 6680, 'dim' => [40.3, 30.4, 11.4],
                'economy' => true, 'gridFeed' => false,
                'peak' => '3.200 VA', 'pvInverter' => '1.600 W', 'mppt' => 'MPPT tunggal',
                'pvPower' => '2.000 W', 'mpptRange' => '40–500 VDC', 'pvCurrent' => '13 A',
                'pvCharge' => '100 A', 'acCharge' => '60 A', 'maxCharge' => '100 A',
                'chargeV' => '14,1 VDC (float 13,5 VDC)', 'netWeight' => '5,76 kg (kotor 6,68 kg)',
            ]),
            $this->inverterHf([
                'sku' => 'YF-FS-HP-4224-D', 'slug' => 'inverter-hybrid-aurora-yf-fs-hp-4224-d-4200w-24v',
                'label' => '4.200W 24V', 'watt' => '4.200 W', 'volt' => '24 VDC',
                'price' => 6350000, 'stock' => 4, 'weight' => 9710, 'dim' => [43.0, 33.0, 13.8],
                'economy' => true, 'gridFeed' => true,
                'peak' => '8.000 VA', 'pvInverter' => '5.000 W', 'mppt' => 'MPPT tunggal',
                'pvPower' => '5.000 W', 'mpptRange' => '60–500 VDC', 'pvCurrent' => '18 A',
                'pvCharge' => '120 A', 'acCharge' => '100 A', 'maxCharge' => '120 A',
                'chargeV' => '28,2 VDC (float 27 VDC)', 'netWeight' => '8,35 kg (kotor 9,71 kg)',
            ]),
            $this->inverterHf([
                'sku' => 'YF-FS-HP-6248', 'slug' => 'inverter-hybrid-aurora-yf-fs-hp-6248-6200w-48v',
                'label' => '6.200W 48V', 'watt' => '6.200 W', 'volt' => '48 VDC',
                'price' => 9450000, 'stock' => 4, 'weight' => 11000, 'dim' => null,
                'economy' => false, 'gridFeed' => true,
                'peak' => '12.400 VA', 'pvInverter' => null, 'mppt' => 'MPPT tunggal',
                'pvPower' => '8.500 W', 'mpptRange' => '60–500 VDC (VMP optimal 360–430 VDC)', 'pvCurrent' => '27 A',
                'pvCharge' => '120 A', 'acCharge' => '100 A', 'maxCharge' => '120 A',
                'chargeV' => '56,4 VDC (float 54 VDC)', 'netWeight' => '±11 kg (estimasi — belum dirilis pabrikan)',
            ]),
            $this->inverterHf([
                'sku' => 'YF-FS-HP-11048', 'slug' => 'inverter-hybrid-aurora-yf-fs-hp-11048-11000w-48v',
                'label' => '11.000W 48V', 'watt' => '11.000 W', 'volt' => '48 VDC',
                'price' => 20250000, 'stock' => 2, 'weight' => 22000, 'dim' => null,
                'economy' => false, 'gridFeed' => true,
                'peak' => '22.000 VA', 'pvInverter' => null, 'mppt' => 'Dual MPPT',
                'pvPower' => '2 × 7.500 W', 'mpptRange' => '90–500 VDC (VMP optimal 360–430 VDC)', 'pvCurrent' => '27 A / 27 A',
                'pvCharge' => '150 A', 'acCharge' => '150 A', 'maxCharge' => '150 A',
                'chargeV' => '56,4 VDC (float 54 VDC)', 'netWeight' => '±22 kg (estimasi — belum dirilis pabrikan)',
            ]),
            $this->inverterHf([
                'sku' => 'YF-FS-HP-11048-D', 'slug' => 'inverter-hybrid-aurora-yf-fs-hp-11048-d-11000w-48v',
                'label' => '11.000W 48V Seri D', 'watt' => '11.000 W', 'volt' => '48 VDC',
                'price' => 16450000, 'stock' => 2, 'weight' => 22460, 'dim' => [60.1, 36.0, 16.3],
                'economy' => true, 'gridFeed' => true,
                'peak' => '22.000 VA', 'pvInverter' => '12.000 W', 'mppt' => 'Dual MPPT',
                'pvPower' => '2 × 5.000 W', 'mpptRange' => '90–500 VDC', 'pvCurrent' => '18 A / 18 A',
                'pvCharge' => '150 A', 'acCharge' => '150 A', 'maxCharge' => '150 A',
                'chargeV' => '56,4 VDC (float 54 VDC)', 'netWeight' => '18,18 kg (kotor 22,46 kg)',
            ]),
        ];
    }

    /** Hybrid transformer-base (low frequency): berat, tahan beban induktif. */
    private function transformerInverters(): array
    {
        return [
            $this->inverterLf([
                'sku' => 'YF-FS-WP1512', 'slug' => 'inverter-hybrid-aurora-yf-fs-wp1512-1200w-12v',
                'label' => '1.200W 12V', 'watt' => '1.200 W', 'volt' => '12 VDC',
                'price' => 3400000, 'stock' => 4, 'weight' => 9150, 'dim' => [43.0, 33.3, 17.3],
                'parallel' => false, 'charger' => 'PWM', 'pvPower' => '800 W',
                'pvRange' => 'PWM 15–50 VDC (Voc maks 50 VDC)', 'solarCharge' => '60 A', 'acCharge' => '40 A',
                'chargeV' => '13,7 VDC', 'netWeight' => '8,46 kg (kotor 9,15 kg)', 'standby' => null,
            ]),
            $this->inverterLf([
                'sku' => 'YF-FS-WM3024', 'slug' => 'inverter-hybrid-aurora-yf-fs-wm3024-2400w-24v',
                'label' => '2.400W 24V', 'watt' => '2.400 W', 'volt' => '24 VDC',
                'price' => 6650000, 'stock' => 4, 'weight' => 16950, 'dim' => [52.7, 33.0, 14.9],
                'parallel' => false, 'charger' => 'MPPT', 'pvPower' => '1.600 W',
                'pvRange' => 'MPPT 30–150 VDC (Voc maks 150 VDC)', 'solarCharge' => '60 A', 'acCharge' => '50 A',
                'chargeV' => '27,4 VDC', 'netWeight' => '15,96 kg (kotor 16,95 kg)', 'standby' => null,
            ]),
            $this->inverterLf([
                'sku' => 'YF-FS-WM6348', 'slug' => 'inverter-hybrid-aurora-yf-fs-wm6348-5000w-48v',
                'label' => '5.000W 48V', 'watt' => '5.000 W', 'volt' => '48 VDC',
                'price' => 9700000, 'stock' => 3, 'weight' => 31060, 'dim' => [54.5, 40.0, 20.0],
                'parallel' => false, 'charger' => 'MPPT', 'pvPower' => '6.400 W',
                'pvRange' => 'MPPT 60–150 VDC (Voc maks 150 VDC)', 'solarCharge' => '120 A', 'acCharge' => '52 A',
                'chargeV' => '54,8 VDC', 'netWeight' => '27,61 kg (kotor 31,06 kg)', 'standby' => null,
            ]),
            $this->inverterLf([
                'sku' => 'YF-FS-WM-6348-P', 'slug' => 'inverter-hybrid-aurora-yf-fs-wm-6348-p-5000w-48v',
                'label' => '5.000W 48V Seri P', 'watt' => '5.000 W', 'volt' => '48 VDC',
                'price' => 10250000, 'stock' => 3, 'weight' => 32000, 'dim' => [52.7, 33.0, 14.9],
                'parallel' => true, 'charger' => 'MPPT', 'pvPower' => '3.200 W',
                'pvRange' => 'MPPT 60–150 VDC (VMP optimal 60–105 VDC)', 'solarCharge' => '60 A', 'acCharge' => '50 A (bawaan 40 A)',
                'chargeV' => '56,4 VDC dapat diatur 48–61 V (float 54 VDC)', 'netWeight' => '±32 kg (estimasi — belum dirilis pabrikan)', 'standby' => '32 W',
            ]),
            $this->inverterLf([
                'sku' => 'YF-FS-WM-12548-P', 'slug' => 'inverter-hybrid-aurora-yf-fs-wm-12548-p-10000w-48v',
                'label' => '10.000W 48V Seri P', 'watt' => '10.000 W', 'volt' => '48 VDC',
                'price' => 21050000, 'stock' => 2, 'weight' => 50000, 'dim' => [54.5, 40.5, 17.5],
                'parallel' => true, 'charger' => 'MPPT', 'pvPower' => '5.400 W',
                'pvRange' => 'MPPT 60–245 VDC (VMP optimal 60–170 VDC)', 'solarCharge' => '100 A', 'acCharge' => '100 A (bawaan 80 A)',
                'chargeV' => '56,4 VDC dapat diatur 48–61 V (float 54 VDC)', 'netWeight' => '±50 kg (estimasi — belum dirilis pabrikan)', 'standby' => '48 W',
            ]),
        ];
    }

    /** Hybrid 3 fase: skala komersial & industri, lewat jalur penawaran. */
    private function threePhaseInverters(): array
    {
        $hv = [
            ['sku' => 'YF-TP-HV3P-40K', 'kw' => '40 kW', 'price' => 150250000, 'battV' => '200 V', 'pv' => '80 kW', 'current' => '60,8 A / 57,7 A'],
            ['sku' => 'YF-TP-HV3P-50K', 'kw' => '50 kW', 'price' => 153500000, 'battV' => '250 V', 'pv' => '100 kW', 'current' => '76 A / 72,2 A'],
            ['sku' => 'YF-TP-HV3P-60K', 'kw' => '60 kW', 'price' => 160500000, 'battV' => '300 V', 'pv' => '100 kW', 'current' => '91,2 A / 86,6 A'],
        ];

        $lv = [
            ['sku' => 'YF-LV-P3E-8048', 'kw' => '8 kW', 'price' => 46850000, 'weight' => 38000, 'dim' => [47.5, 68.3, 25.6], 'charge' => '190 A', 'pv' => '16.000 W', 'onGrid' => '8.800 W', 'group' => 'a'],
            ['sku' => 'YF-LV-P3E-10048', 'kw' => '10 kW', 'price' => 48550000, 'weight' => 38000, 'dim' => [47.5, 68.3, 25.6], 'charge' => '210 A', 'pv' => '20.000 W', 'onGrid' => '11.000 W', 'group' => 'a'],
            ['sku' => 'YF-LV-P3E-12048', 'kw' => '12 kW', 'price' => 50050000, 'weight' => 38000, 'dim' => [47.5, 68.3, 25.6], 'charge' => '250 A', 'pv' => '24.000 W', 'onGrid' => '13.200 W', 'group' => 'a'],
            ['sku' => 'YF-LV-P3E-15048', 'kw' => '15 kW', 'price' => 54800000, 'weight' => 48000, 'dim' => [44.8, 66.0, 26.5], 'charge' => '315 A', 'pv' => '30 kW', 'onGrid' => null, 'group' => 'b'],
            ['sku' => 'YF-LV-P3E-20048', 'kw' => '20 kW', 'price' => 70550000, 'weight' => 48000, 'dim' => [44.8, 66.0, 26.5], 'charge' => '390 A', 'pv' => '40 kW', 'onGrid' => null, 'group' => 'b'],
            ['sku' => 'YF-LV-P3E-24048', 'kw' => '24 kW', 'price' => 76850000, 'weight' => 48000, 'dim' => [44.8, 66.0, 26.5], 'charge' => '415 A', 'pv' => '48 kW', 'onGrid' => null, 'group' => 'b'],
        ];

        return array_merge(
            array_map(fn (array $u) => $this->threePhaseHv($u), $hv),
            array_map(fn (array $u) => $this->threePhaseLv($u), $lv),
        );
    }

    /** Baterai LiFePO4: blok 12/24V, pack 48V, sampai lemari CES. */
    private function batteries(): array
    {
        return [
            $this->batteryBlock([
                'sku' => 'YF-LFP-128200', 'slug' => 'baterai-lithium-lifepo4-aurora-yf-lfp-128200-12v-200ah',
                'volt' => '12,8 VDC', 'ah' => '200 Ah', 'kwh' => '2,56 kWh', 'wh' => '2.560 Wh',
                'price' => 9400000, 'stock' => 4, 'weight' => 21300, 'dim' => [52.2, 21.8, 24.0],
                'opVolt' => '11,2–14 V', 'recCurrent' => '50 A', 'maxCurrent' => '100 A',
                'cycles' => '≥6.000 siklus', 'stack' => 'Maks. 4 seri &amp; 4 paralel', 'netWeight' => '21,3 kg',
            ]),
            $this->batteryBlock([
                'sku' => 'YF-LFP-256100', 'slug' => 'baterai-lithium-lifepo4-aurora-yf-lfp-256100-24v-100ah',
                'volt' => '25,6 VDC', 'ah' => '100 Ah', 'kwh' => '2,56 kWh', 'wh' => '2.560 Wh',
                'price' => 9400000, 'stock' => 4, 'weight' => 21300, 'dim' => [52.2, 21.8, 24.0],
                'opVolt' => '22,4–28 V', 'recCurrent' => '50 A', 'maxCurrent' => '100 A',
                'cycles' => '≥6.000 siklus', 'stack' => 'Maks. 2 seri &amp; 2 paralel', 'netWeight' => '21,3 kg',
            ]),
            $this->batteryBlock([
                'sku' => 'YF-LFP-128314', 'slug' => 'baterai-lithium-lifepo4-aurora-yf-lfp-128314-12v-314ah',
                'volt' => '12,8 VDC', 'ah' => '314 Ah', 'kwh' => '4 kWh', 'wh' => '4.000 Wh',
                'price' => 10650000, 'stock' => 4, 'weight' => 25300, 'dim' => [38.3, 26.7, 20.4],
                'opVolt' => '11,2–14 V', 'recCurrent' => '100 A', 'maxCurrent' => '200 A',
                'cycles' => '≥8.000 siklus', 'stack' => 'Maks. 4 seri &amp; 4 paralel', 'netWeight' => '25,3 kg',
            ]),
            $this->batteryPack([
                'sku' => 'YF-LFP-5KWH-24V', 'slug' => 'battery-pack-lifepo4-aurora-yf-lfp-5kwh-24v',
                'volt' => '24 VDC', 'kwh' => '5 kWh', 'price' => 18750000, 'stock' => 3,
                'weight' => 45000, 'dim' => null, 'ces' => false, 'pending' => true,
                'ah' => null, 'recCurrent' => null, 'maxCurrent' => null, 'cycles' => null,
                'install' => null, 'netWeight' => '±45 kg (estimasi — model ini belum ada di katalog Aurora)',
            ]),
            $this->batteryPack([
                'sku' => 'YF-LFP-5KWH-48V', 'slug' => 'battery-pack-lifepo4-aurora-yf-lfp-5kwh-48v',
                'volt' => '51,2 VDC', 'kwh' => '5,12 kWh', 'price' => 19450000, 'stock' => 3,
                'weight' => 58800, 'dim' => [66.7, 43.0, 23.6], 'ces' => false, 'pending' => false,
                'ah' => '100 Ah', 'recCurrent' => '50 A', 'maxCurrent' => '100 A (puncak 110 A @10 detik)',
                'cycles' => '≥8.000 siklus', 'install' => 'Gantung dinding atau berdiri di lantai', 'netWeight' => '58,8 kg',
            ]),
            $this->batteryPack([
                'sku' => 'YF-LFP-10.5KWH-48V', 'slug' => 'battery-pack-lifepo4-aurora-yf-lfp-10-5kwh-48v',
                'volt' => '51,2 VDC', 'kwh' => '10,5 kWh', 'price' => 35000000, 'stock' => 2,
                'weight' => 96200, 'dim' => [71.5, 43.0, 28.5], 'ces' => false, 'pending' => false,
                'ah' => '206 Ah', 'recCurrent' => '75 A', 'maxCurrent' => '100 A (puncak 110 A @10 detik)',
                'cycles' => '≥8.000 siklus', 'install' => 'Berdiri di lantai (floor standing)', 'netWeight' => '96,2 kg',
            ]),
            $this->batteryPack([
                'sku' => 'YF-LFP-15KWH-48V', 'slug' => 'battery-pack-lifepo4-aurora-yf-lfp-15kwh-48v',
                'volt' => '51,2 VDC', 'kwh' => '15 kWh', 'price' => 40150000, 'stock' => 2,
                'weight' => 122100, 'dim' => [85.7, 43.0, 28.5], 'ces' => false, 'pending' => false,
                'ah' => '280 Ah', 'recCurrent' => '100 A', 'maxCurrent' => '150 A (puncak 160 A @10 detik)',
                'cycles' => '≥8.000 siklus', 'install' => 'Berdiri di lantai (floor standing)', 'netWeight' => '122,1 kg',
            ]),
            $this->batteryPack([
                'sku' => 'YF-LFP-21.5KWH-CES', 'slug' => 'battery-pack-lifepo4-aurora-yf-lfp-21-5kwh-ces',
                'volt' => '51,2 VDC', 'kwh' => '21,5 kWh', 'price' => 76150000, 'stock' => 1,
                'weight' => 197000, 'dim' => [72.4, 27.0, 98.5], 'ces' => true, 'pending' => false,
                'ah' => '420 Ah', 'recCurrent' => '100 A', 'maxCurrent' => '200 A (puncak 210 A @10 detik)',
                'cycles' => '≥8.000 siklus', 'install' => 'Berdiri di lantai, ber-roda (movable)', 'netWeight' => '197 kg',
            ]),
            $this->batteryPack([
                'sku' => 'YF-LFP-30KWH-CES', 'slug' => 'battery-pack-lifepo4-aurora-yf-lfp-30kwh-ces',
                'volt' => '51,2 VDC', 'kwh' => '30 kWh', 'price' => 95150000, 'stock' => 1,
                'weight' => 256000, 'dim' => [86.8, 27.0, 102.5], 'ces' => true, 'pending' => false,
                'ah' => '600 Ah', 'recCurrent' => '100 A', 'maxCurrent' => '200 A (puncak 210 A @10 detik)',
                'cycles' => '≥8.000 siklus', 'install' => 'Berdiri di lantai, ber-roda (movable)', 'netWeight' => '256 kg',
            ]),
        ];
    }

    /** Inverter hybrid high-frequency 1 fase. */
    private function inverterHf(array $u): array
    {
        $name = "Inverter Hybrid Aurora High Frequency {$u['label']} ({$u['sku']})";

        $features = [
            "⚡ Daya kontinu <strong>{$u['watt']}</strong> dengan lonjakan <strong>{$u['peak']}</strong> — kuat menahan arus start peralatan rumah tangga.",
            "🔆 <strong>{$u['mppt']}</strong>, panel surya hingga <strong>{$u['pvPower']}</strong> pada rentang {$u['mpptRange']}.",
            "🔋 Arus pengisian hingga <strong>{$u['maxCharge']}</strong> (surya {$u['pvCharge']} / PLN {$u['acCharge']}), sistem baterai <strong>{$u['volt']}</strong>.",
            '🌊 <strong>Pure sine wave</strong>, efisiensi konversi hingga <strong>98%</strong>, waktu pindah ≤10 ms (mode UPS) — komputer tidak ikut mati saat listrik padam.',
            '🔗 Slot ekspansi untuk <strong>kartu komunikasi BMS baterai lithium</strong>, WiFi, dan dry contact.',
        ];

        if ($u['economy']) {
            $features[] = '💰 <strong>Seri D</strong> — rancangan hemat biaya <strong>tanpa filter EMC</strong> dan <strong>tanpa fungsi paralel</strong>. Harganya lebih murah untuk daya yang sama; pilih seri ini bila cukup satu unit dan tidak berencana menambah unit paralel.';
        } else {
            $features[] = '🔗 <strong>Dapat diparalel hingga 9 unit</strong> — kapasitas bisa ditambah bertahap tanpa mengganti inverter.';
            $features[] = '☀️ <strong>Bisa bekerja tanpa baterai</strong> (mode mandiri) dan mendukung operasi tersambung jaringan PLN.';
        }

        $specs = [
            'Merek' => 'Aurora',
            'Model' => $u['sku'],
            'Tipe' => 'Inverter hybrid high-frequency 1 fase',
            'Daya Kontinu' => $u['watt'],
            'Daya Puncak' => $u['peak'],
            'Tegangan Baterai' => $u['volt'],
            'Tegangan Pengisian' => $u['chargeV'],
            'Input AC' => '220/230/240 VAC; 90–280 VAC ±3V (normal) / 170–280 VAC ±3V (UPS)',
            'Output AC' => '220/230/240 VAC, 50/60 Hz ±0,1%',
            'Bentuk Gelombang' => 'Pure sine wave',
            'Waktu Pindah' => '≤10 ms (UPS) / ≤20 ms (inverter)',
            'Kemampuan Beban Lebih' => 'Mode baterai: 11 detik @105–150%; 2 detik @150–200%; 400 ms @>200%',
            'Efisiensi Konversi Maks' => '98%',
            'Tipe Solar Charger' => $u['mppt'],
            'Daya PV Maks' => $u['pvPower'],
            'Rentang MPPT' => $u['mpptRange'],
            'Tegangan PV Maks' => '500 VDC',
            'Arus Input PV Maks' => $u['pvCurrent'],
            'Arus Pengisian Surya / PLN / Total' => "{$u['pvCharge']} / {$u['acCharge']} / {$u['maxCharge']}",
            'Operasi Tersambung Jaringan' => $u['gridFeed'] ? 'Ya (170–265 VAC, 49–51 Hz)' : 'Tidak',
            'Fungsi Paralel' => $u['economy'] ? 'Tidak tersedia (seri D)' : 'Maks. 9 unit',
            'Filter EMC' => $u['economy'] ? 'Tidak ada (rancangan hemat biaya seri D)' : 'Ada',
            'Layar &amp; Komunikasi' => 'LCD; RS232 (baud 2400); slot kartu BMS lithium / WiFi / dry contact',
            'Suhu Operasi' => '-10°C s/d +50°C',
        ];

        if ($u['pvInverter']) {
            $specs['Daya Inverter dari PV'] = $u['pvInverter'];
        }
        if ($u['dim']) {
            $specs['Dimensi (P×L×T)'] = str_replace('.', ',', implode(' × ', array_map(fn ($d) => $d * 10, $u['dim']))).' mm';
        }
        $specs['Berat'] = $u['netWeight'];
        $specs['Sertifikasi'] = 'EN-IEC 60335-1, EN-IEC 60335-2-29, IEC 62109-1';

        return [
            'slug' => $u['slug'],
            'sku' => $u['sku'],
            'name' => $name,
            'model' => $u['sku'],
            'categories' => ['inverter-hybrid', 'inverter-single-phase'],
            'price' => $u['price'],
            'stock' => $u['stock'],
            'weight_grams' => $u['weight'],
            'length_cm' => $u['dim'][0] ?? null,
            'width_cm' => $u['dim'][1] ?? null,
            'height_cm' => $u['dim'][2] ?? null,
            'requires_freight' => $u['weight'] >= 30000,
            'warranty' => 'Garansi 1 Tahun',
            'short_description' => "Inverter hybrid high-frequency {$u['watt']} sistem {$u['volt']}, {$u['mppt']} hingga {$u['pvPower']}, pure sine wave, efisiensi 98%.".($u['economy'] ? ' Seri D: hemat biaya, tanpa fungsi paralel.' : ' Dapat diparalel hingga 9 unit.'),
            'description' => $this->html(
                "<p><strong>Aurora {$u['sku']}</strong> adalah inverter hybrid <strong>high-frequency {$u['watt']}</strong> untuk sistem baterai <strong>{$u['volt']}</strong>. Rancangan high-frequency membuatnya <strong>jauh lebih ringan dan ringkas</strong> daripada inverter berbasis transformator pada daya yang sama — pilihan umum untuk rumah tangga dan beban elektronik.</p>",
                $features,
                ['Rumah tangga & kontrakan', 'Toko, ruko, dan kantor kecil', 'PLTS hybrid skala rumah', 'Backup elektronik, lampu, dan kulkas'],
            ),
            'specifications' => $this->specs($specs),
            'keywords' => 'inverter hybrid, aurora, '.mb_strtolower($u['sku']).', high frequency, inverter '.$u['watt'].', mppt, plts, solar inverter'.($u['economy'] ? ', seri d, ekonomis' : ', paralel 9 unit'),
            'meta_title' => $name.' — Energi.Click',
            'meta_description' => "Jual inverter hybrid Aurora {$u['sku']} {$u['watt']} sistem {$u['volt']}, {$u['mppt']} {$u['pvPower']}, efisiensi 98%. Garansi 1 tahun.",
        ];
    }

    /** Inverter hybrid transformer-base (low frequency) 1 fase. */
    private function inverterLf(array $u): array
    {
        $name = "Inverter Hybrid Aurora Transformer Base {$u['label']} ({$u['sku']})";

        $features = [
            "⚡ Daya kontinu <strong>{$u['watt']}</strong> pada sistem baterai <strong>{$u['volt']}</strong>, rasio beban puncak <strong>3:1</strong>.",
            '💪 <strong>Transformator toroidal</strong> — menyerap lonjakan arus start pompa air, kompresor kulkas/AC, mesin las, dan motor listrik yang sering membuat inverter high-frequency trip.',
            "🔆 Solar charger <strong>{$u['charger']}</strong>, panel surya hingga <strong>{$u['pvPower']}</strong> ({$u['pvRange']}).",
            "🔋 Arus pengisian surya <strong>{$u['solarCharge']}</strong>, dari PLN <strong>{$u['acCharge']}</strong>.",
            '🌊 <strong>Pure sine wave</strong>, waktu pindah ≤10 ms (mode UPS).',
            '🔌 Mendukung modul <strong>WiFi/GPRS</strong> dan sinyal <strong>start genset otomatis</strong>.',
        ];

        if ($u['parallel']) {
            $features[] = '🔗 <strong>Dapat diparalel hingga 6 unit</strong>; komunikasi RS232, BMS baterai lithium, dan dry contact card.';
        }

        $specs = [
            'Merek' => 'Aurora',
            'Model' => $u['sku'],
            'Tipe' => 'Inverter hybrid transformer base (low frequency) 1 fase',
            'Daya Kontinu' => $u['watt'],
            'Rasio Beban Puncak' => '3:1 (maks)',
            'Tegangan Baterai' => $u['volt'],
            'Tegangan Pengisian' => $u['chargeV'],
            'Tipe Baterai' => 'Lead-acid, gel, aki basah, atau lithium',
            'Input AC' => $u['parallel'] ? '230 VAC; 90–275 VAC ±3V (APL) / 170–275 VAC ±3V (UPS)' : '220 VAC; 154–264 VAC ±3V (normal) / 185–264 VAC ±3V (UPS)',
            'Output AC (mode baterai)' => '220/230 VAC ±10%, 50/60 Hz ±1%',
            'Bentuk Gelombang' => 'Pure sine wave',
            'Waktu Pindah' => $u['parallel'] ? '≤20 ms (APL) / ≤10 ms (UPS)' : '≤10 ms (UPS) / ≤20 ms (inverter)',
            'Tipe Solar Charger' => $u['charger'],
            'Daya PV Maks' => $u['pvPower'],
            'Rentang Input PV' => $u['pvRange'],
            'Arus Pengisian Surya' => $u['solarCharge'],
            'Arus Pengisian PLN' => $u['acCharge'],
            'Fungsi Paralel' => $u['parallel'] ? 'Maks. 6 unit' : 'Tidak tersedia',
            'Komunikasi' => $u['parallel'] ? 'RS232, BMS baterai lithium, antarmuka paralel, dry contact card' : 'Mendukung modul WiFi/GPRS',
            'Suhu Operasi' => '-10°C s/d +50°C',
            'Tingkat Kebisingan' => '<45 dB',
        ];

        if ($u['standby']) {
            $specs['Konsumsi Daya Siaga'] = $u['standby'];
        }
        if ($u['dim']) {
            $specs['Dimensi (P×L×T)'] = str_replace('.', ',', implode(' × ', array_map(fn ($d) => $d * 10, $u['dim']))).' mm';
        }
        $specs['Berat'] = $u['netWeight'];

        return [
            'slug' => $u['slug'],
            'sku' => $u['sku'],
            'name' => $name,
            'model' => $u['sku'],
            'categories' => ['inverter-hybrid', 'inverter-single-phase'],
            'price' => $u['price'],
            'stock' => $u['stock'],
            'weight_grams' => $u['weight'],
            'length_cm' => $u['dim'][0] ?? null,
            'width_cm' => $u['dim'][1] ?? null,
            'height_cm' => $u['dim'][2] ?? null,
            'requires_freight' => $u['weight'] >= 30000,
            'warranty' => 'Garansi 1 Tahun',
            'short_description' => "Inverter hybrid transformer base {$u['watt']} sistem {$u['volt']} — tahan lonjakan beban induktif seperti pompa air, AC, dan motor listrik. Solar charger {$u['charger']} hingga {$u['pvPower']}.",
            'description' => $this->html(
                "<p><strong>Aurora {$u['sku']}</strong> adalah inverter hybrid <strong>transformer base (low frequency)</strong> berkapasitas <strong>{$u['watt']}</strong> untuk sistem baterai <strong>{$u['volt']}</strong>. Transformator besar di dalamnya membuat unit ini <strong>tahan lonjakan beban induktif</strong> — pompa air, kompresor kulkas dan AC, mesin las, serta motor listrik yang menyentak saat start.</p>",
                $features,
                ['Rumah dengan pompa air & AC', 'Bengkel dan usaha dengan motor listrik', 'Lokasi dengan tegangan PLN tidak stabil', 'Instalasi yang dipakai terus-menerus'],
            ),
            'specifications' => $this->specs($specs),
            'keywords' => 'inverter hybrid, aurora, '.mb_strtolower($u['sku']).', transformer base, low frequency, inverter '.$u['watt'].', pompa air, plts',
            'meta_title' => $name.' — Energi.Click',
            'meta_description' => "Jual inverter hybrid transformer base Aurora {$u['sku']} {$u['watt']} sistem {$u['volt']}. Tahan beban induktif, garansi 1 tahun.",
        ];
    }

    /** Inverter hybrid 3 fase berbasis baterai tegangan tinggi. */
    private function threePhaseHv(array $u): array
    {
        $name = "Inverter Hybrid 3 Fase Aurora {$u['kw']} Baterai Tegangan Tinggi ({$u['sku']})";

        return [
            'slug' => 'inverter-hybrid-3-fase-aurora-'.mb_strtolower(str_replace('YF-TP-', '', $u['sku'])),
            'sku' => $u['sku'],
            'name' => $name,
            'model' => $u['sku'],
            'categories' => ['inverter-hybrid', 'inverter-three-phase'],
            'price' => $u['price'],
            'stock' => 1,
            'weight_grams' => 88000,
            'length_cm' => 54.4,
            'width_cm' => 27.8,
            'height_cm' => 88.0,
            'requires_freight' => true,
            // Skala proyek: ongkir dan konfigurasi baterai dihitung per lokasi.
            'requires_quotation' => true,
            'warranty' => 'Garansi 1 Tahun',
            'short_description' => "Inverter hybrid 3 fase {$u['kw']} berbasis baterai tegangan tinggi, 4 MPPT, PV hingga {$u['pv']}, IP66. Untuk PLTS komersial & industri.",
            'description' => $this->html(
                "<p><strong>Aurora {$u['sku']}</strong> adalah inverter hybrid <strong>3 fase {$u['kw']}</strong> berbasis <strong>baterai tegangan tinggi</strong> untuk PLTS komersial dan industri: pabrik, gudang, hotel, rumah sakit, dan gedung perkantoran dengan sambungan PLN 3 fase.</p>",
                [
                    "⚡ Keluaran <strong>{$u['kw']}</strong> pada 380/400 VAC (3W+N+PE) — menyatu dengan instalasi listrik industri.",
                    "🔆 <strong>4 MPPT</strong> (8 string), panel surya hingga <strong>{$u['pv']}</strong>, rentang MPPT 150–850 VDC, tegangan DC maks 1000 V.",
                    "🔋 <strong>Baterai tegangan tinggi</strong> 135–850 V (nominal {$u['battV']}), arus pengisian/pengosongan 2 × 100 A — arus kerja lebih kecil, kabel lebih ramping, rugi daya lebih rendah.",
                    '📈 <strong>Efisiensi MPPT 99,9%</strong>, efisiensi maksimum 98,5%, efisiensi Euro 97,5%.',
                    '🔗 <strong>Paralel hingga 6 unit</strong> untuk kapasitas lebih besar.',
                    '🛡️ <strong>IP66</strong> — dapat dipasang di luar ruangan; pemantauan insulasi, deteksi arus bocor (RCD), dan deteksi gangguan pentanahan.',
                    '⚙️ Kemampuan beban lebih off-grid: 110% selama 30 detik, 120% selama 10 detik, 150% selama 0,2 detik.',
                ],
                ['Pabrik, gudang, dan gedung komersial', 'Hotel, rumah sakit, dan perkantoran', 'PLTS komersial rooftop maupun ground mount', 'Fasilitas dengan sambungan PLN 3 fase'],
            ).'<p><em>Produk skala proyek: pengiriman, konfigurasi baterai, dan pemasangan dihitung per lokasi. Ajukan penawaran agar tim kami menyiapkan perhitungan yang sesuai kebutuhan Anda.</em></p>',
            'specifications' => $this->specs([
                'Merek' => 'Aurora',
                'Model' => $u['sku'],
                'Tipe' => 'Inverter hybrid 3 fase, baterai tegangan tinggi',
                'Daya Terpasang' => $u['kw'],
                'Tegangan &amp; Konfigurasi' => '380/400 VAC, 3W+N+PE, 50/60 Hz',
                'Arus Keluaran EPS' => $u['current'],
                'Tipe Baterai' => 'Lithium-ion / lead-acid',
                'Rentang Tegangan Baterai' => '135–850 V (nominal '.$u['battV'].')',
                'Arus Pengisian/Pengosongan Maks' => '2 × 100 A',
                'Jumlah MPPT / String' => '4 MPPT / 2+2+2+2',
                'Daya PV Maks' => $u['pv'],
                'Tegangan DC Maks' => '1000 V',
                'Rentang MPPT' => '150–850 V (tegangan start 200 V)',
                'Arus Input Maks per String' => '8 × 20 A (Isc maks 8 × 30 A)',
                'Kemampuan Beban Lebih (off-grid)' => '110% / 30 detik; 120% / 10 detik; 150% / 0,2 detik',
                'Efisiensi MPPT' => '99,90%',
                'Efisiensi Maksimum' => '98,50%',
                'Efisiensi Euro' => '97,50%',
                'Fungsi Paralel' => 'Ya, hingga 6 unit',
                'Proteksi Ingress' => 'IP66',
                'Proteksi Lain' => 'Pemantauan insulasi, deteksi arus bocor (RCD), deteksi gangguan pentanahan',
                'Suhu Operasi' => '-25°C s/d +60°C (derating linear di atas +45°C)',
                'Kelembapan' => '0–100% tanpa kondensasi',
                'Layar &amp; Komunikasi' => 'Aplikasi + LED (LCD pada versi -S)',
                'Dimensi (L×T×D)' => '544 × 880 × 278 mm',
                'Berat' => '88 kg',
                'Sertifikasi' => 'EN-IEC 62477-1, EN-IEC 62109-1/2, EN-IEC 61000-6-2, EN50549-1/10, VDE-AR-N 4105',
            ]),
            'keywords' => 'inverter hybrid 3 fase, aurora, '.mb_strtolower($u['sku']).', inverter '.$u['kw'].', high voltage battery, plts industri, plts komersial, ip66',
            'meta_title' => "Inverter Hybrid 3 Fase Aurora {$u['kw']} {$u['sku']} — Energi.Click",
            'meta_description' => "Jual inverter hybrid 3 fase Aurora {$u['sku']} {$u['kw']}, baterai tegangan tinggi, 4 MPPT, PV {$u['pv']}, IP66. Untuk PLTS komersial.",
        ];
    }

    /** Inverter hybrid 3 fase berbasis baterai 48V. */
    private function threePhaseLv(array $u): array
    {
        $name = "Inverter Hybrid 3 Fase Aurora {$u['kw']} Baterai 48V ({$u['sku']})";
        $groupA = $u['group'] === 'a';

        $features = [
            "⚡ Keluaran <strong>{$u['kw']}</strong> pada 380/400 VAC (3W+N+PE) — langsung menyatu dengan instalasi 3 fase.",
            "🔆 <strong>2 MPPT</strong>, panel surya hingga <strong>{$u['pv']}</strong>, tegangan DC maks 1000 V.",
            "🔋 <strong>Baterai 48V standar</strong> — komponen lebih mudah didapat dan biaya awal lebih ringan dibanding sistem tegangan tinggi. Arus pengisian/pengosongan hingga <strong>{$u['charge']}</strong>.",
            '🛡️ <strong>IP66</strong> — dapat dipasang di luar ruangan; proteksi polaritas terbalik PV, deteksi resistansi isolasi, arus bocor, arus lebih, hubung singkat, dan tegangan lebih.',
        ];

        $features[] = $groupA
            ? '🔗 <strong>Paralel hingga 8 unit</strong>; kemampuan beban lebih off-grid >200% selama 15 detik; efisiensi MPPT 98% dan efisiensi Euro 97,5%.'
            : '🔗 <strong>Dapat diparalel</strong> untuk kapasitas lebih besar; waktu pindah <20 ms.';

        $specs = [
            'Merek' => 'Aurora',
            'Model' => $u['sku'],
            'Tipe' => 'Inverter hybrid 3 fase, baterai 48V (low-voltage)',
            'Daya Terpasang' => $u['kw'],
            'Tegangan &amp; Konfigurasi' => '380/400 VAC, 3W+N+PE, 50/60 Hz',
            'Tipe Baterai' => 'Lithium atau lead-acid',
            'Tegangan Baterai' => $groupA ? '48 V (pengisian maks ≤60 V, dapat diatur)' : '40–60 VDC',
            'Arus Pengisian/Pengosongan Maks' => $u['charge'],
            'Jumlah MPPT' => $groupA ? '2 MPPT' : '2 MPPT / 2+2 string',
            'Daya PV Maks' => $u['pv'],
            'Tegangan DC Maks' => '1000 V',
            'Rentang MPPT' => $groupA ? '200–800 V (tegangan start 150 V)' : '150–950 V (tegangan start 180 V)',
            'Proteksi Ingress' => 'IP66',
            'Pendinginan' => $groupA ? 'Intelligent forced air cooling' : 'Forced air cooling',
            'Suhu Operasi' => $groupA ? '-25°C s/d +60°C (derating di atas +45°C)' : '-25°C s/d +60°C',
            'Dimensi (L×T×D)' => str_replace('.', ',', implode(' × ', array_map(fn ($d) => $d * 10, $u['dim']))).' mm',
            'Berat' => str_replace('.', ',', (string) ($u['weight'] / 1000)).' kg',
        ];

        if ($groupA) {
            $specs['Daya Semu Maks (on-grid)'] = $u['onGrid'];
            $specs['Kemampuan Beban Lebih (off-grid)'] = '>200% selama 15 detik';
            $specs['Efisiensi MPPT'] = '98%';
            $specs['Efisiensi Euro'] = '97,5%';
            $specs['Fungsi Paralel'] = 'Maks. 8 unit';
            $specs['Topologi'] = 'HF isolation (sisi baterai)';
            $specs['Komunikasi'] = 'Port BMS bawaan (CAN/RS485)';
        } else {
            $specs['Waktu Pindah'] = '<20 ms';
            $specs['Proteksi Arus Lebih Keluaran'] = '400 VAC / 100 A AC';
        }

        $specs['Sertifikasi'] = 'IEC62109, IEC61000, IEC61727, EN50549-1, VDE-4105';

        return [
            'slug' => 'inverter-hybrid-3-fase-aurora-'.mb_strtolower(str_replace('YF-LV-', '', $u['sku'])),
            'sku' => $u['sku'],
            'name' => $name,
            'model' => $u['sku'],
            'categories' => ['inverter-hybrid', 'inverter-three-phase'],
            'price' => $u['price'],
            'stock' => 1,
            'weight_grams' => $u['weight'],
            'length_cm' => $u['dim'][0],
            'width_cm' => $u['dim'][1],
            'height_cm' => $u['dim'][2],
            'requires_freight' => true,
            'requires_quotation' => true,
            'warranty' => 'Garansi 1 Tahun',
            'short_description' => "Inverter hybrid 3 fase {$u['kw']} berbasis baterai 48V, 2 MPPT, PV hingga {$u['pv']}, IP66. Untuk PLTS komersial & industri.",
            'description' => $this->html(
                "<p><strong>Aurora {$u['sku']}</strong> adalah inverter hybrid <strong>3 fase {$u['kw']}</strong> berbasis <strong>baterai 48V</strong> — pilihan untuk PLTS komersial skala menengah yang ingin memakai baterai 48V yang umum di pasaran, bukan sistem tegangan tinggi yang komponennya lebih terbatas.</p>",
                $features,
                ['Pabrik dan gudang skala menengah', 'Ruko, kantor, dan gedung usaha', 'PLTS komersial rooftop', 'Fasilitas dengan sambungan PLN 3 fase'],
            ).'<p><em>Produk skala proyek: pengiriman, konfigurasi baterai, dan pemasangan dihitung per lokasi. Ajukan penawaran agar tim kami menyiapkan perhitungan yang sesuai kebutuhan Anda.</em></p>',
            'specifications' => $this->specs($specs),
            'keywords' => 'inverter hybrid 3 fase, aurora, '.mb_strtolower($u['sku']).', inverter '.$u['kw'].', baterai 48v, plts komersial, ip66',
            'meta_title' => "Inverter Hybrid 3 Fase Aurora {$u['kw']} {$u['sku']} — Energi.Click",
            'meta_description' => "Jual inverter hybrid 3 fase Aurora {$u['sku']} {$u['kw']}, baterai 48V, 2 MPPT, PV {$u['pv']}, IP66. Untuk PLTS komersial.",
        ];
    }

    /** Baterai blok 12,8V / 25,6V (pengganti aki, sistem kecil). */
    private function batteryBlock(array $u): array
    {
        $name = "Baterai Lithium LiFePO4 Aurora {$u['volt']} {$u['ah']} ({$u['kwh']}) — {$u['sku']}";

        return [
            'slug' => $u['slug'],
            'sku' => $u['sku'],
            'name' => $name,
            'model' => $u['sku'],
            'categories' => ['baterai-lithium-lifepo4'],
            'price' => $u['price'],
            'stock' => $u['stock'],
            'unit' => 'pcs',
            'weight_grams' => $u['weight'],
            'length_cm' => $u['dim'][0],
            'width_cm' => $u['dim'][1],
            'height_cm' => $u['dim'][2],
            'requires_freight' => true,
            'warranty' => 'Garansi 5 Tahun',
            'short_description' => "Baterai LiFePO4 {$u['volt']} {$u['ah']} ({$u['wh']}) dengan smart BMS, IP65, DOD 90%, dan umur {$u['cycles']}. Pengganti aki VRLA yang jauh lebih awet.",
            'description' => $this->html(
                "<p><strong>Aurora {$u['sku']}</strong> adalah baterai <strong>LiFePO4 {$u['volt']} {$u['ah']}</strong> dengan energi <strong>{$u['wh']}</strong>. Dibanding aki basah/VRLA berkapasitas sama, baterai ini boleh dikuras jauh lebih dalam (<strong>DOD 90%</strong>) dan usia pakainya berkali lipat lebih panjang.</p>",
                [
                    "♻️ Umur <strong>{$u['cycles']}</strong> pada DOD 90% — aki VRLA biasanya habis di 300–500 siklus.",
                    "⚡ Arus pengisian/pengosongan kontinu hingga <strong>{$u['maxCurrent']}</strong> (disarankan {$u['recCurrent']}).",
                    '🛡️ <strong>Smart BMS bawaan</strong> — proteksi kelebihan pengisian, tegangan rendah, arus lebih, hubung singkat, suhu, plus fungsi penyeimbang sel (balancing).',
                    '💦 <strong>IP65</strong> — tahan debu dan semprotan air.',
                    "🔗 <strong>{$u['stack']}</strong> — kapasitas dan tegangan bisa ditambah sesuai kebutuhan (selisih tegangan antar unit harus &lt;0,5 V).",
                    '🧰 <strong>Bebas perawatan</strong> — tidak perlu isi air aki, tidak mengeluarkan gas.',
                ],
                ['Sistem solar skala kecil & menengah', 'Backup lampu, elektronik, dan pompa', 'Pengganti aki VRLA/deep-cycle', 'Camper, perahu, dan kendaraan lapangan'],
            ),
            'specifications' => $this->specs([
                'Merek' => 'Aurora',
                'Model' => $u['sku'],
                'Tipe Sel' => 'LiFePO4 (Lithium Iron Phosphate)',
                'Tegangan Nominal' => $u['volt'],
                'Tegangan Kerja' => $u['opVolt'],
                'Kapasitas' => $u['ah'],
                'Energi' => $u['kwh'],
                'Arus Pengisian/Pengosongan Disarankan' => $u['recCurrent'],
                'Arus Pengisian/Pengosongan Kontinu Maks' => $u['maxCurrent'],
                'Depth of Discharge (DOD)' => '90%',
                'Umur Siklus (@25°C)' => $u['cycles'],
                'Seri &amp; Paralel' => $u['stack'].' (selisih tegangan antar unit &lt;0,5 V)',
                'Proteksi' => 'Smart BMS bawaan (balancing, overcharge, tegangan rendah, arus lebih, hubung singkat, suhu)',
                'Proteksi Ingress' => 'IP65',
                'Suhu Pengisian' => '0°C s/d +55°C',
                'Suhu Pengosongan' => '-10°C s/d +55°C',
                'Ketinggian Operasi' => '≤2000 m',
                'Dimensi (P×L×T)' => str_replace('.', ',', implode(' × ', array_map(fn ($d) => $d * 10, $u['dim']))).' mm',
                'Berat' => $u['netWeight'],
                'Garansi Pabrikan' => '5 tahun (0.5C charge/discharge @25°C, 80% DOD)',
            ]),
            'keywords' => 'baterai lithium, lifepo4, aurora, '.mb_strtolower($u['sku']).', '.mb_strtolower($u['volt'].' '.$u['ah']).', baterai solar, pengganti aki, bms, ip65',
            'meta_title' => "Baterai LiFePO4 Aurora {$u['volt']} {$u['ah']} {$u['kwh']} — Energi.Click",
            'meta_description' => "Jual baterai LiFePO4 Aurora {$u['sku']} {$u['volt']} {$u['ah']} ({$u['wh']}), smart BMS, IP65, {$u['cycles']}. Garansi 5 tahun.",
        ];
    }

    /** Battery pack / lemari CES untuk penyimpanan energi rumah & komersial. */
    private function batteryPack(array $u): array
    {
        $ces = $u['ces'];
        $name = "Battery Pack LiFePO4 Aurora {$u['volt']} {$u['kwh']}".($ces ? ' (CES)' : '')." — {$u['sku']}";

        $intro = $ces
            ? "<p><strong>Aurora {$u['sku']}</strong> adalah <strong>Cabinet Energy Storage (CES)</strong> berkapasitas <strong>{$u['kwh']}</strong> pada tegangan <strong>{$u['volt']}</strong> — lemari penyimpanan energi terintegrasi untuk kebutuhan komersial: menekan biaya listrik pada jam beban puncak sekaligus menjaga operasional saat jaringan padam.</p>"
            : "<p><strong>Aurora {$u['sku']}</strong> adalah <strong>battery pack LiFePO4 {$u['kwh']}</strong> pada tegangan <strong>{$u['volt']}</strong> — modul penyimpanan energi siap pakai untuk PLTS rumah dan usaha, tanpa perlu merakit sel dan BMS sendiri.</p>";

        if ($u['pending']) {
            $features = [
                "🔋 Kapasitas <strong>{$u['kwh']}</strong> pada tegangan sistem <strong>{$u['volt']}</strong>.",
                '♻️ Kimia <strong>LiFePO4</strong> — pilihan standar penyimpanan energi karena stabil, aman, dan tahan ribuan siklus.',
                '📦 <strong>Modul siap pakai</strong> — tidak perlu merakit sel, BMS, dan kabel penyeimbang sendiri.',
            ];

            $specs = [
                'Merek' => 'Aurora',
                'Model' => $u['sku'],
                'Tipe Sel' => 'LiFePO4 (Lithium Iron Phosphate)',
                'Tegangan Sistem' => $u['volt'],
                'Kapasitas Energi' => $u['kwh'],
                'Spesifikasi Teknis Rinci' => self::PLACEHOLDER.' — model ini belum tercantum di katalog Aurora 2026; silakan konfirmasi ke CS sebelum memesan',
                'Berat Pengiriman' => $u['netWeight'],
            ];
        } else {
            $features = [
                "🔋 Kapasitas <strong>{$u['kwh']}</strong> ({$u['ah']}) pada tegangan sistem <strong>{$u['volt']}</strong>, DOD <strong>90%</strong>.",
                "♻️ Umur <strong>{$u['cycles']}</strong> — dipakai penuh setiap hari pun bertahun-tahun usianya.",
                "⚡ Arus pengisian/pengosongan kontinu hingga <strong>{$u['maxCurrent']}</strong> (disarankan {$u['recCurrent']}).",
                '🔗 <strong>Paralel hingga 16 unit</strong> — kapasitas bisa tumbuh mengikuti kebutuhan.',
                '📺 <strong>Layar LCD</strong> di unit dan komunikasi <strong>CAN/RS485</strong> — kompatibel dengan inverter hybrid merek utama.',
                '🛡️ <strong>Smart BMS bawaan</strong> dengan proteksi lengkap dan penyeimbangan sel.',
                "🏠 Pemasangan: <strong>{$u['install']}</strong>.",
            ];

            $specs = [
                'Merek' => 'Aurora',
                'Model' => $u['sku'],
                'Tipe Sel' => 'LiFePO4 (Lithium Iron Phosphate)',
                'Tegangan Nominal' => $u['volt'],
                'Tegangan Kerja' => '44,8–56 V',
                'Kapasitas' => $u['ah'],
                'Energi' => $u['kwh'],
                'Arus Pengisian/Pengosongan Disarankan' => $u['recCurrent'],
                'Arus Pengisian/Pengosongan Kontinu Maks' => $u['maxCurrent'],
                'Depth of Discharge (DOD)' => '90%',
                'Umur Siklus' => $u['cycles'],
                'Paralel' => '≤16 unit',
                'Layar' => 'LCD',
                'Komunikasi' => 'CAN / RS485',
                'Proteksi' => 'Smart BMS bawaan',
                'Proteksi Ingress' => 'IP21',
                'Pemasangan' => $u['install'],
                'Suhu Pengisian' => '0°C s/d +60°C',
                'Suhu Pengosongan' => '-20°C s/d +60°C',
                'Ketinggian Operasi' => '≤2000 m',
                'Dimensi (P×L×T)' => str_replace('.', ',', implode(' × ', array_map(fn ($d) => $d * 10, $u['dim']))).' mm',
                'Berat' => $u['netWeight'],
                'Garansi Pabrikan' => '5 tahun (0.5C charge/discharge @25°C, 80% DOD)',
            ];
        }

        return [
            'slug' => $u['slug'],
            'sku' => $u['sku'],
            'name' => $name,
            'model' => $u['sku'],
            'categories' => ['baterai-lithium-lifepo4'],
            'price' => $u['price'],
            'stock' => $u['stock'],
            'weight_grams' => $u['weight'],
            'length_cm' => $u['dim'][0] ?? null,
            'width_cm' => $u['dim'][1] ?? null,
            'height_cm' => $u['dim'][2] ?? null,
            'requires_freight' => true,
            'requires_quotation' => $ces,
            'warranty' => 'Garansi 5 Tahun',
            'short_description' => $ces
                ? "Cabinet Energy Storage Aurora {$u['kwh']} {$u['volt']} — lemari penyimpanan energi komersial, ber-roda, umur {$u['cycles']}. Tersedia lewat penawaran proyek."
                : "Battery pack LiFePO4 Aurora {$u['kwh']} {$u['volt']} — modul penyimpanan energi siap pakai untuk PLTS rumah dan usaha.".($u['pending'] ? '' : " DOD 90%, {$u['cycles']}."),
            'description' => $this->html($intro, $features, $ces
                ? ['Pabrik, gudang, dan gedung komersial', 'Peak shaving / penghematan biaya beban puncak', 'Cadangan daya fasilitas kritis', 'PLTS komersial berskala besar']
                : ['PLTS rumah tangga & usaha', 'Cadangan listrik harian', 'Sistem hybrid yang ingin mandiri saat malam', 'Peningkatan dari bank aki'])
                .($ces ? '<p><em>Produk skala proyek: pengiriman dan pemasangan dihitung per lokasi. Ajukan penawaran agar tim kami menyiapkan perhitungan yang sesuai.</em></p>' : '')
                .($u['pending'] ? '<p><em>Catatan: model ini tercantum di price list Aurora namun belum ada di katalog teknis 2026. Spesifikasi rincinya sedang kami mintakan — hubungi CS sebelum memesan.</em></p>' : ''),
            'specifications' => $this->specs($specs),
            'keywords' => 'battery pack, lifepo4, aurora, '.mb_strtolower($u['sku']).', '.mb_strtolower($u['kwh']).', bess, penyimpanan energi, baterai plts'.($ces ? ', ces, cabinet energy storage' : ''),
            'meta_title' => "Battery Pack LiFePO4 Aurora {$u['kwh']} {$u['volt']} — Energi.Click",
            'meta_description' => "Jual battery pack LiFePO4 Aurora {$u['sku']} {$u['kwh']} {$u['volt']} untuk PLTS. Garansi 5 tahun.",
        ];
    }

    /** Deskripsi produk: paragraf pembuka + daftar keunggulan + peruntukan. */
    private function html(string $intro, array $features, array $useCases = []): string
    {
        $html = $intro."\n<h4>Keunggulan</h4>\n<ul>\n";
        foreach ($features as $feature) {
            $html .= '<li>'.$feature."</li>\n";
        }
        $html .= "</ul>\n";

        if ($useCases !== []) {
            $html .= "<h4>Cocok Untuk</h4>\n<ul>\n";
            foreach ($useCases as $useCase) {
                $html .= '<li>'.$useCase."</li>\n";
            }
            $html .= "</ul>\n";
        }

        return $html;
    }

    /** Tabel spesifikasi dua kolom. */
    private function specs(array $rows): string
    {
        $html = "<table><tbody>\n";
        foreach ($rows as $label => $value) {
            $html .= '<tr><th>'.$label.'</th><td>'.$value."</td></tr>\n";
        }

        return $html.'</tbody></table>';
    }

    private function setStock(Product $product, int $target): void
    {
        $current = (int) WarehouseStock::where('product_id', $product->id)
            ->whereNull('product_variant_id')->sum('quantity_available');
        $delta = $target - $current;
        if ($delta !== 0) {
            app(StockService::class)->adjust($product, null, $delta, StockMovementType::Purchase, note: 'Stok awal (seeder Aurora)');
        }
    }

    private function setVariantStock(Product $product, ProductVariant $variant, int $target): void
    {
        $current = (int) WarehouseStock::where('product_id', $product->id)
            ->where('product_variant_id', $variant->id)->sum('quantity_available');
        $delta = $target - $current;
        if ($delta !== 0) {
            app(StockService::class)->adjust($product, $variant, $delta, StockMovementType::Purchase, note: 'Stok awal (seeder Aurora)');
        }
    }
}
