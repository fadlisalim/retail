<?php

namespace Database\Seeders;

use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\WarehouseStock;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

/**
 * Seeds the JSD Solar line-up that is ready-stock in Jakarta: 6 off-grid
 * inverters + 1 WiFi module, 4 all-in-one systems, 7 LiFePO4 batteries.
 *
 * Harga jual = harga modal ÷ 0,8 (margin 20% dari revenue), dibulatkan ke ATAS
 * ke kelipatan 50.000 — pembulatan ke bawah akan menggerus margin. Harga di
 * sini BELUM termasuk PPN dan BELUM termasuk ongkir (supplier EXW Jakarta).
 * Harga modal sengaja TIDAK ditulis di file ini; isi lewat Admin → Produk →
 * Harga Modal bila perlu laporan margin.
 *
 * Spesifikasi diambil dari datasheet resmi JSD Solar. Item tanpa brosur
 * (XHP4K30, JHP5000, XHP65K60, J12100, J12200, J24100, WiFi Plug Pro) hanya
 * memuat data yang tercantum di price list — ditandai "menyusul" agar tidak
 * ada klaim yang tidak bisa dipertanggungjawabkan. Idempotent: aman diulang.
 */
class JsdSolarSeeder extends Seeder
{
    public function run(): void
    {
        $brand = Brand::firstOrCreate(
            ['slug' => 'jsd-solar'],
            ['name' => 'JSD Solar', 'is_active' => true],
        );

        $created = 0;
        $upgraded = 0;
        $skipped = 0;

        foreach ($this->products() as $row) {
            $categories = array_values(array_filter(array_map(
                fn (string $slug) => Category::where('slug', $slug)->value('id'),
                $row['categories'],
            )));

            $product = Product::firstOrCreate(
                ['slug' => $row['slug']],
                Arr::except($row, ['slug', 'categories', 'stock', 'requires']) + [
                    'category_id' => $categories[0] ?? null,
                    'brand_id' => $brand->id,
                    'product_type' => 'simple',
                    'condition' => 'new',
                    'unit' => 'unit',
                    'status' => 'published',
                    'is_new' => true,
                    'published_at' => now(),
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

        $this->command?->info("Produk JSD Solar: {$created} ditambahkan, {$upgraded} diperbarui, {$skipped} dilewati (sudah ada).");
        $this->command?->warn('Harga BELUM termasuk PPN & ongkir. Cek stok di Admin → Stok, dan unggah foto + brosur di Admin → Produk → Edit.');
    }

    /**
     * Memperbarui baris yang datanya tertinggal. Dua pemicunya:
     *
     *  1. Baris masih memuat penampung "Menyusul dari pabrikan" sementara data
     *     resminya sudah ada — brosur akhirnya datang.
     *  2. Baris kehilangan fitur yang wajib tercantum (kunci 'requires'),
     *     mis. keterangan WiFi yang sempat terlewat karena datasheet pabrikan
     *     tidak menyebutnya padahal price list menandainya.
     *
     * Baris yang sudah disunting admin sampai penampungnya hilang DAN
     * fiturnya tercantum tidak disentuh, supaya hasil kerja mereka aman.
     */
    private function upgradePlaceholder(Product $product, array $row): bool
    {
        $placeholder = 'Menyusul dari pabrikan';
        $stored = (string) $product->specifications;

        $hasStalePlaceholder = str_contains($stored, $placeholder) && ! str_contains($row['specifications'], $placeholder);

        $missingFeature = collect($row['requires'] ?? [])
            ->contains(fn (string $needle) => ! str_contains($stored, $needle) && str_contains($row['specifications'], $needle));

        if (! $hasStalePlaceholder && ! $missingFeature) {
            return false;
        }

        $product->forceFill(Arr::only($row, [
            'name',
            'short_description', 'description', 'specifications',
            'weight_grams', 'length_cm', 'width_cm', 'height_cm',
            'warranty', 'keywords', 'meta_title', 'meta_description',
        ]))->save();

        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function products(): array
    {
        return array_merge($this->inverters(), $this->allInOne(), $this->batteries());
    }

    /** Inverter off-grid + aksesori monitoring. */
    private function inverters(): array
    {
        return [
            [
                'slug' => 'inverter-off-grid-jsd-solar-j1200hc-1-2kw-12v',
                'sku' => 'JSD-J1200HC',
                'name' => 'Inverter Off-Grid JSD Solar J1200HC 1,2kW 12V (MPPT, Pure Sine Wave)',
                'model' => 'J1200HC',
                'categories' => ['inverter-off-grid'],
                'price' => 3100000,
                'stock' => 5,
                'weight_grams' => 4000,
                'warranty' => 'Garansi 1 Tahun',
                'estimated_processing' => '1-3 hari kerja',
                'short_description' => 'Inverter off-grid 1,2kW 12V dengan MPPT 1000W built-in, pure sine wave, dan LCD. Cocok untuk rumah kecil, warung, atau backup lampu & elektronik ringan.',
                'description' => $this->html(
                    '<p><strong>JSD Solar J1200HC</strong> adalah inverter off-grid <strong>1,2 kW sistem baterai 12V</strong> dengan <strong>solar charge controller MPPT built-in</strong>. Panel surya dan listrik PLN bisa menyuplai beban bersamaan, jadi unit ini bekerja sebagai inverter sekaligus pengisi baterai.</p>',
                    [
                        '⚡ <strong>Daya 1,2 kW</strong> dengan lonjakan (surge) 2,4 kVA — aman untuk beban start-up ringan.',
                        '🔆 <strong>MPPT 1000W</strong>, rentang kerja 18–100 VDC, Voc maksimum 125 V.',
                        '🔌 <strong>Pure sine wave</strong> — aman untuk kulkas, TV, komputer, dan peralatan sensitif.',
                        '🔋 Arus pengisian hingga <strong>120 A gabungan</strong> (PV 60 A + PLN 60 A), mendukung baterai <strong>lithium maupun lead-acid</strong>.',
                        '⏱️ Waktu pindah (switch time) <strong>10 ms</strong> mode APP/UPS — komputer tidak ikut mati saat listrik padam.',
                        '📺 Layar <strong>LCD</strong> + port komunikasi RS232.',
                    ],
                    ['Rumah tangga kecil & kontrakan', 'Warung / kios', 'Backup lampu, kipas, TV, dan router', 'Sistem solar 12V skala kecil'],
                ),
                'specifications' => $this->specs([
                    'Merek' => 'JSD Solar',
                    'Model' => 'J1200HC',
                    'Tipe' => 'Inverter off-grid + MPPT solar charger',
                    'Daya Kontinu' => '1,2 kW (PF 1)',
                    'Daya Puncak' => '2,4 kVA',
                    'Tegangan Baterai' => '12 VDC',
                    'Input AC' => '208/220/230/240 VAC, 90–280 V (mode APP) / 170–280 V (mode UPS)',
                    'Output AC' => '208/220/230/240 VAC, 50/60 Hz ±0,1%',
                    'Bentuk Gelombang' => 'Pure sine wave',
                    'Waktu Pindah' => '10 ms (APP/UPS) / 60 ms (GEN)',
                    'Tipe Solar Charger' => 'MPPT',
                    'Daya PV Maks' => '1000 W (arus maks 14 A)',
                    'Rentang MPPT' => '18–100 VDC',
                    'Tegangan PV Open Circuit Maks' => '125 VDC',
                    'Arus Pengisian PV / AC' => '60 A / 60 A (maks gabungan 120 A)',
                    'Tipe Baterai' => 'Lithium &amp; Lead-acid',
                    'Layar &amp; Komunikasi' => 'LCD, RS232',
                    'Proteksi Ingress' => 'IP21 (pemasangan dalam ruangan)',
                    'Suhu Operasi' => '-10°C s/d +60°C',
                    'Ketinggian Operasi Maks' => '4000 m (derating di atas 1000 m)',
                    'Berat' => '±4 kg (estimasi)',
                ]),
                'keywords' => 'inverter off grid, jsd solar, j1200hc, inverter 1200w, inverter 1.2kw, mppt, 12v, pure sine wave, inverter surya',
                'meta_title' => 'Inverter Off-Grid JSD Solar J1200HC 1,2kW 12V MPPT — Energi.Click',
                'meta_description' => 'Jual inverter off-grid JSD Solar J1200HC 1,2kW 12V dengan MPPT 1000W built-in, pure sine wave, LCD. Garansi 1 tahun.',
            ],
            [
                'slug' => 'inverter-off-grid-jsd-solar-j2500hc-2-5kw-12v',
                'sku' => 'JSD-J2500HC',
                'name' => 'Inverter Off-Grid JSD Solar J2500HC 2,5kW 12V (MPPT 3000W, Pure Sine Wave)',
                'model' => 'J2500HC',
                'categories' => ['inverter-off-grid'],
                'price' => 3750000,
                'stock' => 5,
                'weight_grams' => 4500,
                'length_cm' => 38.2,
                'width_cm' => 15.5,
                'height_cm' => 31.0,
                'warranty' => 'Garansi 1 Tahun',
                'estimated_processing' => '1-3 hari kerja',
                'short_description' => 'Inverter off-grid 2,5kW 12V dengan MPPT 3000W (Voc 450V), pure sine wave, dan komunikasi baterai lithium RS485/CAN. Efisiensi 91%.',
                'description' => $this->html(
                    '<p><strong>JSD Solar J2500HC</strong> adalah inverter off-grid <strong>2,5 kW sistem 12V</strong> dengan <strong>MPPT 3000W</strong> — kapasitas panel jauh di atas kelasnya, sehingga baterai terisi lebih cepat meski cuaca berawan.</p>',
                    [
                        '⚡ <strong>2,5 kW kontinu</strong>, lonjakan <strong>4 kVA</strong>, sanggup beban lebih 250% selama 10 detik.',
                        '🔆 <strong>MPPT 3000W</strong> dengan tegangan PV open circuit hingga <strong>450 VDC</strong> — rangkaian panel bisa lebih panjang, kabel lebih hemat.',
                        '🔋 Arus pengisian <strong>100 A</strong> (PV / PLN / gabungan), pengosongan hingga 150 A.',
                        '🔗 Komunikasi <strong>RS485 / CAN / RS232</strong> — bisa "berbicara" dengan BMS baterai lithium.',
                        '📶 Monitoring <strong>WiFi</strong> (opsional/built-in/eksternal), dukungan CT &amp; meter opsional.',
                        '⏱️ Waktu pindah <strong>10 ms</strong>, efisiensi maksimum <strong>91%</strong>.',
                    ],
                    ['Rumah tangga 900–1300 VA', 'Toko & usaha kecil', 'Backup pompa air, kulkas, dan lampu', 'Sistem solar 12V dengan panel besar'],
                ),
                'specifications' => $this->specs([
                    'Merek' => 'JSD Solar',
                    'Model' => 'J2500HC',
                    'Tipe' => 'Inverter off-grid + MPPT solar charger',
                    'Daya Kontinu' => '2,5 kW / 2,5 kVA (PF 1)',
                    'Daya Puncak' => '4 kVA',
                    'Tegangan Baterai' => '12 VDC',
                    'Input AC' => '208/220/230/240 VAC, 90–280 V (APP/GEN) / 170–264 V (UPS)',
                    'Output AC' => '208/220/230/240 VAC, 50/60 Hz',
                    'Bentuk Gelombang' => 'Pure sine wave',
                    'Kemampuan Beban Lebih' => '102–125% jangka panjang; 125–250% selama 10 detik',
                    'Efisiensi Maks' => '91% @12 VDC',
                    'Waktu Pindah' => '10 ms (APP/UPS) / 20 ms (GEN)',
                    'Tipe Solar Charger' => 'MPPT',
                    'Daya PV Maks' => '3000 W (arus maks 18 A)',
                    'Rentang MPPT' => '30–400 VDC',
                    'Tegangan PV Open Circuit Maks' => '450 VDC',
                    'Arus Pengisian PV / AC / Gabungan' => '100 A / 100 A / 100 A',
                    'Arus Pengosongan Maks' => '150 A',
                    'Tipe Baterai' => 'Lithium &amp; Lead-acid',
                    'Layar &amp; Komunikasi' => 'LCD; RS485 / CAN / RS232; CT &amp; meter (opsional)',
                    'Monitoring' => 'WiFi (opsional / built-in / eksternal)',
                    'Proteksi Ingress' => 'IP21 (pemasangan dalam ruangan)',
                    'Suhu Operasi' => '-10°C s/d +60°C',
                    'Dimensi (T×L×D)' => '382 × 310 × 155 mm (tanpa bracket)',
                    'Berat' => '4,5 kg',
                ]),
                'keywords' => 'inverter off grid, jsd solar, j2500hc, inverter 2500w, inverter 2.5kw, mppt 3000w, 12v, pure sine wave',
                'meta_title' => 'Inverter Off-Grid JSD Solar J2500HC 2,5kW 12V MPPT 3000W — Energi.Click',
                'meta_description' => 'Jual inverter off-grid JSD Solar J2500HC 2,5kW 12V, MPPT 3000W Voc 450V, pure sine wave, komunikasi RS485/CAN. Garansi 1 tahun.',
            ],
            [
                'slug' => 'inverter-off-grid-jsd-solar-j4000e-4kw-24v-wifi',
                'sku' => 'JSD-J4000E',
                'name' => 'Inverter Off-Grid JSD Solar J4000E 4kW 24V (MPPT 5000W, WiFi Built-in)',
                'model' => 'J4000E',
                'categories' => ['inverter-off-grid'],
                // Price list JSD menandai model ini "INCLUDING WIFI".
                'requires' => ['WiFi'],
                'price' => 6200000,
                'stock' => 5,
                'weight_grams' => 6200,
                'length_cm' => 43.5,
                'width_cm' => 10.3,
                'height_cm' => 28.4,
                'warranty' => 'Garansi 1 Tahun',
                'estimated_processing' => '1-3 hari kerja',
                'short_description' => 'Inverter off-grid 4kW 24V dengan MPPT 5000W (Voc 500V) dan WiFi monitoring built-in. Surge 7,2kVA, efisiensi 92%.',
                'description' => $this->html(
                    '<p><strong>JSD Solar J4000E</strong> adalah inverter off-grid <strong>4 kW sistem 24V</strong> dengan <strong>MPPT 5000W</strong> dan <strong>modul WiFi sudah terpasang</strong> — pantau produksi surya dan status baterai langsung dari HP, tanpa beli dongle terpisah.</p>',
                    [
                        '⚡ <strong>4 kW / 4 kVA kontinu</strong> dengan lonjakan <strong>7,2 kVA</strong>.',
                        '🔆 <strong>MPPT 5000W</strong>, rentang kerja 40–450 VDC, Voc maksimum 500 VDC.',
                        '📶 <strong>WiFi built-in</strong> — monitoring lewat aplikasi tanpa aksesori tambahan.',
                        '🔋 Pengisian hingga <strong>100 A</strong> dari PV maupun PLN, mendukung lithium &amp; lead-acid.',
                        '🛡️ Perlindungan beban lebih bertingkat: 60 detik @102–110%, 10 detik @110–130%, 3 detik @130–150%.',
                        '⏱️ Waktu pindah <strong>10 ms</strong>, efisiensi maksimum <strong>92%</strong>.',
                    ],
                    ['Rumah tangga 2200–3500 VA', 'Ruko & kantor kecil', 'Sistem off-grid 24V', 'Backup AC 1 PK, pompa, dan kulkas'],
                ),
                'specifications' => $this->specs([
                    'Merek' => 'JSD Solar',
                    'Model' => 'J4000E',
                    'Tipe' => 'Inverter off-grid + MPPT solar charger (WiFi built-in)',
                    'Daya Kontinu' => '4 kW / 4 kVA (PF 1)',
                    'Daya Puncak' => '7,2 kVA',
                    'Tegangan Baterai' => '24 VDC (float 27 V, proteksi overcharge 30,5 V)',
                    'Input AC' => '208/220/230/240 VAC, 90–280 V (APP) / 170–280 V (UPS)',
                    'Output AC' => '208/220/230/240 VAC, 50/60 Hz ±0,1%',
                    'Bentuk Gelombang' => 'Pure sine wave',
                    'Kemampuan Beban Lebih' => '60s @102–110%; 10s @110–130%; 3s @130–150%',
                    'Efisiensi Maks' => '92% @24 VDC',
                    'Waktu Pindah' => '10 ms (APP/UPS) / 20 ms (GEN)',
                    'Tipe Solar Charger' => 'MPPT',
                    'Daya PV Maks' => '5000 W (arus maks 18 A)',
                    'Rentang MPPT' => '40–450 VDC',
                    'Tegangan PV Open Circuit Maks' => '500 VDC',
                    'Arus Pengisian PV / AC / Gabungan' => '100 A / 100 A / 100 A',
                    'Tipe Baterai' => 'Lithium &amp; Lead-acid',
                    'Layar &amp; Komunikasi' => 'LCD; RS485 / RS232 / CAN',
                    'Monitoring' => 'WiFi (built-in)',
                    'Proteksi Ingress' => 'IP21 (pemasangan dalam ruangan)',
                    'Suhu Operasi' => '-10°C s/d +60°C',
                    'Dimensi (L×T×D)' => '434,5 × 284 × 103 mm',
                    'Berat' => '6,2 kg',
                ]),
                'keywords' => 'inverter off grid, jsd solar, j4000e, inverter 4kw, inverter 4000w, mppt 5000w, 24v, wifi, pure sine wave',
                'meta_title' => 'Inverter Off-Grid JSD Solar J4000E 4kW 24V MPPT 5000W WiFi — Energi.Click',
                'meta_description' => 'Jual inverter off-grid JSD Solar J4000E 4kW 24V dengan MPPT 5000W Voc 500V dan WiFi built-in. Surge 7,2kVA, garansi 1 tahun.',
            ],
            [
                'slug' => 'inverter-off-grid-jsd-solar-j6500hc-6-5kw-48v-wifi',
                'sku' => 'JSD-J6500HC',
                'name' => 'Inverter Off-Grid JSD Solar J6500HC 6,5kW 48V (MPPT 9000W, WiFi)',
                'model' => 'J6500HC',
                'categories' => ['inverter-off-grid'],
                // Price list JSD menandai model ini "INCLUDING WIFI".
                'requires' => ['WiFi'],
                'price' => 7350000,
                'stock' => 5,
                'weight_grams' => 10000,
                'length_cm' => 41.0,
                'width_cm' => 11.0,
                'height_cm' => 33.6,
                'warranty' => 'Garansi 1 Tahun',
                'estimated_processing' => '1-3 hari kerja',
                'short_description' => 'Inverter off-grid 6,5kW 48V dengan MPPT 9000W (Voc 500V), surge 12kVA, efisiensi 94%, WiFi monitoring, dan dry contact.',
                'description' => $this->html(
                    '<p><strong>JSD Solar J6500HC</strong> adalah inverter off-grid <strong>6,5 kW sistem 48V</strong> dengan <strong>MPPT 9000W</strong> — salah satu rasio panel-per-inverter paling lega di kelasnya, sehingga panen surya tetap tinggi saat mendung.</p>',
                    [
                        '⚡ <strong>6,5 kW kontinu</strong> dengan lonjakan <strong>12 kVA</strong>, efisiensi maksimum <strong>94%</strong>.',
                        '🔆 <strong>MPPT 9000W</strong> (arus 27 A), rentang kerja 60–450 VDC, Voc maksimum 500 VDC.',
                        '🔋 Arus pengisian <strong>120 A</strong> dari PV maupun PLN — baterai 5–10 kWh terisi dalam hitungan jam.',
                        '📶 Monitoring <strong>WiFi</strong>; komunikasi RS232 / RS485 / USB / dry contact untuk integrasi genset atau alarm.',
                        '📺 Layar <strong>LCD</strong> (opsi RGB).',
                        '⏱️ Waktu pindah <strong>10 ms</strong> baik mode normal maupun UPS.',
                    ],
                    ['Rumah tangga 3500–5500 VA', 'Ruko, kantor, dan bengkel', 'Sistem off-grid/hybrid 48V dengan baterai lithium', 'Backup AC, pompa, dan peralatan usaha'],
                ),
                'specifications' => $this->specs([
                    'Merek' => 'JSD Solar',
                    'Model' => 'J6500HC',
                    'Tipe' => 'Inverter off-grid + MPPT solar charger',
                    'Daya Kontinu' => '6,5 kW (PF 1)',
                    'Daya Puncak' => '12 kVA',
                    'Tegangan Baterai' => '48 VDC (float 54 V, proteksi overcharge 61 V)',
                    'Input AC' => '208/220/230/240 VAC, 90–280 V (normal) / 170–280 V (UPS)',
                    'Output AC' => '208/220/230/240 VAC, 50/60 Hz ±0,1%',
                    'Bentuk Gelombang' => 'Pure sine wave',
                    'Kemampuan Beban Lebih' => '10 detik @ >150% beban',
                    'Efisiensi Maks' => '94% @48 VDC',
                    'Waktu Pindah' => '10 ms (normal &amp; UPS)',
                    'Tipe Solar Charger' => 'MPPT',
                    'Daya PV Maks' => '9000 W (arus maks 27 A)',
                    'Rentang MPPT' => '60–450 VDC',
                    'Tegangan PV Open Circuit Maks' => '500 VDC',
                    'Arus Pengisian PV / AC / Gabungan' => '120 A / 120 A / 120 A',
                    'Tipe Baterai' => 'Lithium &amp; Lead-acid',
                    'Layar &amp; Komunikasi' => 'LCD (opsi RGB); RS232 / RS485 / USB / dry contact',
                    'Monitoring' => 'WiFi',
                    'Proteksi Ingress' => 'IP21 (pemasangan dalam ruangan)',
                    'Suhu Operasi' => '-10°C s/d +50°C',
                    'Dimensi (L×T×D)' => '410 × 336 × 110 mm',
                    'Berat' => '10 kg',
                ]),
                'keywords' => 'inverter off grid, jsd solar, j6500hc, inverter 6.5kw, inverter 6500w, mppt 9000w, 48v, wifi, pure sine wave',
                'meta_title' => 'Inverter Off-Grid JSD Solar J6500HC 6,5kW 48V MPPT 9000W — Energi.Click',
                'meta_description' => 'Jual inverter off-grid JSD Solar J6500HC 6,5kW 48V dengan MPPT 9000W, surge 12kVA, efisiensi 94%, WiFi. Garansi 1 tahun.',
            ],
            [
                'slug' => 'inverter-off-grid-jsd-solar-j6200hp-6-2kw-48v-paralel',
                'sku' => 'JSD-J6200HP',
                'name' => 'Inverter Off-Grid JSD Solar J6200HP 6,2kW 48V (MPPT 120A, Paralel 12 Unit)',
                'model' => 'J6200HP',
                'categories' => ['inverter-off-grid'],
                'price' => 8450000,
                'stock' => 5,
                'weight_grams' => 12000,
                'length_cm' => 45.0,
                'width_cm' => 30.0,
                'height_cm' => 13.0,
                'warranty' => 'Garansi 1 Tahun',
                'estimated_processing' => '1-3 hari kerja',
                'short_description' => 'Inverter off-grid seri M 6,2kW 48V yang bisa diparalel hingga 12 unit (1 fase atau 3 fase), MPPT 120A/6500W, dan sanggup bekerja tanpa baterai.',
                'description' => $this->html(
                    '<p><strong>JSD Solar J6200HP</strong> (seri M) adalah inverter off-grid/hybrid <strong>6,2 kW sistem 48V</strong> yang dirancang untuk <strong>tumbuh bersama kebutuhan</strong>: bisa diparalel hingga <strong>12 unit</strong> dalam konfigurasi 1 fase maupun 3 fase — total daya hingga 74 kW.</p>',
                    [
                        '🔗 <strong>Paralel hingga 12 unit</strong>, mendukung keluaran 1 fase maupun 3 fase.',
                        '🔆 <strong>MPPT 120 A / 6500 W</strong>, rentang PV 60–450 VDC, tegangan maksimum 500 VDC.',
                        '🔋 <strong>Aktivasi baterai lithium</strong> otomatis (lewat PV atau PLN) + komunikasi BMS via RS485.',
                        '☀️ <strong>Bisa bekerja tanpa baterai</strong> — panel surya langsung menyuplai beban siang hari.',
                        '⚙️ Prioritas output bisa dipilih: <strong>UTL / SOL / SBU / SUB</strong>, plus fungsi EQ untuk memperpanjang umur baterai.',
                        '🧹 <strong>Penutup debu yang bisa dilepas</strong> — dirancang untuk lingkungan berdebu.',
                        '📶 Dukungan <strong>dongle WiFi colok langsung</strong> (opsional) dan koneksi genset.',
                    ],
                    ['Rumah besar & vila', 'Usaha/UMKM dengan beban 5–6 kW', 'Sistem yang direncanakan bertahap (mulai 1 unit, tambah kemudian)', 'Lokasi tanpa jaringan PLN yang andal'],
                ),
                'specifications' => $this->specs([
                    'Merek' => 'JSD Solar',
                    'Model' => 'J6200HP (seri M / M6200-48L)',
                    'Tipe' => 'Inverter off-grid hybrid + MPPT solar charger',
                    'Daya Kontinu' => '6,2 kVA / 6,2 kW',
                    'Daya Puncak' => '12,4 kVA',
                    'Kemampuan Paralel' => 'Ya, hingga 12 unit (1 fase / 3 fase)',
                    'Tegangan Baterai' => '48 VDC (float 54 V, proteksi overcharge 63 V)',
                    'Aktivasi Baterai Lithium' => 'Ya (lewat PV atau PLN), komunikasi RS485',
                    'Input AC' => '230 VAC; 170–280 V (komputer) / 90–280 V (peralatan rumah)',
                    'Output AC' => '220/230 VAC ±5%, 50/60 Hz',
                    'Bentuk Gelombang' => 'Pure sine wave',
                    'Efisiensi Puncak' => '94%',
                    'Kemampuan Beban Lebih' => '5 detik @ ≥140% beban; 10 detik @ 110–140%',
                    'Waktu Pindah' => '10 ms (komputer) / 20 ms (peralatan rumah)',
                    'Tipe Solar Charger' => 'MPPT',
                    'Daya PV Maks' => '6500 W (arus input maks 27 A)',
                    'Rentang MPPT' => '60–450 VDC (maks 500 VDC)',
                    'Arus Pengisian PV / AC / Maks' => '120 A / 80 A / 120 A',
                    'Mode Prioritas Output' => 'UTL / SOL / SBU / SUB',
                    'Komunikasi' => 'RS232 / RS485; port paralel',
                    'Suhu Operasi' => '-10°C s/d +50°C',
                    'Dimensi (D×L×T)' => '450 × 300 × 130 mm',
                    'Berat' => '12 kg',
                ]),
                'keywords' => 'inverter off grid, jsd solar, j6200hp, m6200, inverter 6.2kw, paralel 12 unit, 3 fase, mppt 120a, 48v',
                'meta_title' => 'Inverter Off-Grid JSD Solar J6200HP 6,2kW 48V Paralel 12 Unit — Energi.Click',
                'meta_description' => 'Jual inverter off-grid JSD Solar J6200HP 6,2kW 48V, paralel hingga 12 unit 1/3 fase, MPPT 120A, bisa jalan tanpa baterai. Garansi 1 tahun.',
            ],
            [
                'slug' => 'inverter-off-grid-jsd-solar-j11100hpc-11kw-48v-dual-mppt',
                'sku' => 'JSD-J11100HPC',
                'name' => 'Inverter Off-Grid JSD Solar J11100HPC 11kW 48V (Dual MPPT, WiFi, Paralel 6 Unit)',
                'model' => 'J11100HPC',
                'categories' => ['inverter-off-grid'],
                // Price list JSD menandai model ini "INCLUDING WIFI"; datasheet
                // pabrikan hanya menyebut RS232/RS485/dry contact.
                'requires' => ['WiFi'],
                'price' => 12200000,
                'stock' => 3,
                'weight_grams' => 16100,
                'length_cm' => 55.0,
                'width_cm' => 46.3,
                'height_cm' => 12.0,
                'warranty' => 'Garansi 1 Tahun',
                'estimated_processing' => '1-3 hari kerja',
                'short_description' => 'Inverter off-grid 11kW 48V dengan dua MPPT (2 × 5500W), surge 22kVA, WiFi monitoring, paralel hingga 6 unit, dan aktivasi baterai lithium otomatis.',
                'description' => $this->html(
                    '<p><strong>JSD Solar J11100HPC</strong> adalah inverter off-grid <strong>11 kVA / 11 kW sistem 48V</strong> dengan <strong>dua jalur MPPT independen (2 × 5500 W)</strong> — dua orientasi atap (misalnya timur dan barat) bisa dipasang terpisah tanpa saling menurunkan performa.</p>',
                    [
                        '⚡ <strong>11 kW kontinu</strong> dengan lonjakan <strong>22 kVA</strong> — sanggup start motor listrik dan kompresor besar.',
                        '🔆 <strong>Dual MPPT 2 × 5500 W</strong>, rentang 60–500 VDC, arus input 18 A per jalur.',
                        '🔗 <strong>Paralel hingga 6 unit</strong> untuk total 66 kW.',
                        '🔋 Arus pengisian hingga <strong>160 A</strong>, pengosongan hingga 220 A; <strong>aktivasi baterai lithium</strong> otomatis + komunikasi RS485.',
                        '📶 <strong>WiFi monitoring sudah termasuk</strong> — pantau produksi surya dan status baterai dari HP tanpa beli dongle terpisah.',
                        '📊 Efisiensi puncak <strong>94%</strong>, layar LCD, komunikasi RS232/RS485/dry contact.',
                        '⚠️ Murni <strong>off-grid</strong> — tidak melakukan ekspor ke jaringan PLN (non grid-tie).',
                    ],
                    ['Rumah besar & vila (>7700 VA)', 'Kantor, hotel kecil, dan resort', 'Workshop / industri ringan', 'Sistem PLTS off-grid skala besar'],
                ),
                'specifications' => $this->specs([
                    'Merek' => 'JSD Solar',
                    'Model' => 'J11100HPC (datasheet pabrikan: J11000HP-W)',
                    'Tipe' => 'Inverter off-grid + dual MPPT solar charger',
                    'Daya Kontinu' => '11 kVA / 11 kW',
                    'Daya Puncak' => '22 kVA',
                    'Kemampuan Paralel' => 'Ya, hingga 6 unit',
                    'Tegangan Baterai' => '48 VDC (float 54 V, proteksi overcharge 63 V)',
                    'Aktivasi Baterai Lithium' => 'Ya (lewat PV atau PLN), komunikasi RS485',
                    'Input AC' => '230 VAC; 170–280 V (komputer) / 90–280 V (peralatan rumah)',
                    'Output AC' => '220/230/240 VAC, 50/60 Hz',
                    'Bentuk Gelombang' => 'Pure sine wave',
                    'Efisiensi Puncak' => '94%',
                    'Kemampuan Beban Lebih' => '5 detik @ ≥140% beban; 10,5 detik @ 101–140%',
                    'Waktu Pindah' => '10 ms (komputer) / 20 ms (peralatan rumah)',
                    'Tipe Solar Charger' => 'MPPT (2 jalur)',
                    'Daya PV Maks' => '5500 W × 2 (arus input 18 A × 2)',
                    'Rentang MPPT' => '60–500 VDC (Voc maks 500 VDC)',
                    'Arus Pengisian Surya / AC / Maks' => '160 A / 120 A / 160 A',
                    'Arus Pengosongan Maks' => '220 A',
                    'Operasi Grid-Tie' => 'Tidak (murni off-grid)',
                    'Monitoring' => 'WiFi (sudah termasuk)',
                    'Komunikasi' => 'RS232 / RS485 / dry contact, LCD',
                    'Suhu Operasi' => '-10°C s/d +50°C',
                    'Dimensi (D×L×T)' => '550 × 463 × 120 mm',
                    'Berat' => '16,1 kg',
                ]),
                'keywords' => 'inverter off grid, jsd solar, j11100hpc, j11000hp, inverter 11kw, dual mppt, paralel 6 unit, 48v, wifi',
                'meta_title' => 'Inverter Off-Grid JSD Solar J11100HPC 11kW 48V Dual MPPT WiFi — Energi.Click',
                'meta_description' => 'Jual inverter off-grid JSD Solar J11100HPC 11kW 48V, dual MPPT 2×5500W, surge 22kVA, WiFi, paralel 6 unit. Garansi 1 tahun.',
            ],
            [
                'slug' => 'modul-wifi-jsd-solar-wifi-plug-pro-monitoring-inverter',
                'sku' => 'JSD-WIFI-PLUG-PRO',
                'name' => 'Modul WiFi JSD Solar WiFi Plug Pro (Monitoring Inverter 6,2kW)',
                'model' => 'WIFI PLUG PRO',
                'categories' => ['inverter-off-grid'],
                'price' => 575000,
                'stock' => 10,
                'weight_grams' => 200,
                'unit' => 'pcs',
                'warranty' => 'Garansi 1 Tahun',
                'estimated_processing' => '1-3 hari kerja',
                'short_description' => 'Dongle WiFi colok-langsung untuk memantau inverter JSD Solar 6,2kW (J6200HP) lewat aplikasi HP — produksi surya, status baterai, dan beban.',
                'description' => $this->html(
                    '<p><strong>JSD Solar WiFi Plug Pro</strong> adalah modul monitoring <strong>colok-langsung (plug &amp; play)</strong> untuk inverter <strong>JSD Solar 6,2 kW (J6200HP)</strong>. Setelah tersambung ke WiFi rumah, produksi panel surya, status baterai, dan pemakaian beban bisa dipantau dari HP.</p>',
                    [
                        '📶 Terhubung ke jaringan WiFi rumah — pemantauan jarak jauh lewat aplikasi.',
                        '🔌 <strong>Plug &amp; play</strong> pada port komunikasi inverter, tanpa pembongkaran.',
                        '📈 Menampilkan produksi surya, status pengisian baterai, dan konsumsi beban.',
                    ],
                    ['Pemilik inverter JSD Solar J6200HP', 'Instalasi yang ingin dipantau dari jauh'],
                ).'<p><em>Catatan: modul ini diperuntukkan bagi inverter 6,2 kW (J6200HP). Untuk model lain, tanyakan kompatibilitasnya ke CS terlebih dahulu — spesifikasi teknis lengkap menyusul dari pabrikan.</em></p>',
                'specifications' => $this->specs([
                    'Merek' => 'JSD Solar',
                    'Model' => 'WiFi Plug Pro',
                    'Fungsi' => 'Modul monitoring WiFi untuk inverter',
                    'Kompatibilitas' => 'Inverter JSD Solar 6,2 kW (J6200HP)',
                    'Pemasangan' => 'Plug &amp; play pada port komunikasi inverter',
                    'Spesifikasi Teknis Rinci' => 'Menyusul dari pabrikan',
                ]),
                'keywords' => 'wifi plug pro, jsd solar, modul wifi inverter, monitoring inverter, dongle wifi, j6200hp',
                'meta_title' => 'Modul WiFi JSD Solar WiFi Plug Pro untuk Inverter 6,2kW — Energi.Click',
                'meta_description' => 'Jual modul WiFi JSD Solar WiFi Plug Pro untuk monitoring inverter J6200HP lewat aplikasi HP. Plug & play, garansi 1 tahun.',
            ],
        ];
    }

    /** All-in-one solar system (inverter + baterai dalam satu unit). */
    private function allInOne(): array
    {
        return [
            [
                'slug' => 'all-in-one-solar-system-jsd-solar-xhp12k15-1-2kw',
                'sku' => 'JSD-XHP12K15',
                'name' => 'All-in-One Solar System JSD Solar XHP12K15 1,2kW (Baterai 1,5kWh Built-in)',
                'model' => 'XHP12K15',
                'categories' => ['baterai-all-in-one-ess', 'portable-power'],
                'price' => 10450000,
                'stock' => 3,
                'weight_grams' => 20700,
                'length_cm' => 13.2,
                'width_cm' => 37.0,
                'height_cm' => 50.4,
                'warranty' => 'Garansi 2 Tahun',
                'estimated_processing' => '1-3 hari kerja',
                'short_description' => 'Sistem all-in-one 1,2kW: inverter, MPPT, dan baterai LiFePO4 1.536Wh dalam satu unit. Tinggal colok panel surya — tanpa merakit komponen terpisah.',
                'description' => $this->html(
                    '<p><strong>JSD Solar XHP12K15</strong> menggabungkan <strong>inverter 1.200 W</strong>, <strong>solar charger MPPT</strong>, dan <strong>baterai LiFePO4 1.536 Wh</strong> dalam satu bodi. Tidak perlu memilih dan merakit inverter, baterai, kabel, dan MCB satu per satu — cukup sambungkan panel surya dan beban.</p>',
                    [
                        '📦 <strong>Semua dalam satu unit</strong> — inverter + MPPT + baterai + soket, langsung pakai.',
                        '⚡ Output <strong>1.200 W / 230 VAC</strong>, input AC hingga 2.000 W untuk pengisian dari PLN.',
                        '🔋 Baterai <strong>LiFePO4 12,8 V / 1.536 Wh</strong> — aman, awet, dan bebas perawatan.',
                        '🔆 <strong>MPPT hingga 1.000 W</strong> (PV 15–125 VDC), efisiensi pelacakan hingga 99,5%.',
                        '🔌 Dilengkapi <strong>soket multi-fungsi</strong> dengan port pengisian ponsel.',
                        '🌬️ <strong>Pendinginan pintar</strong> (smart fan) dan layar LCD informatif.',
                        '🧳 <strong>Pegangan angkat</strong> — mudah dipindah antar ruangan atau dibawa ke lokasi lain.',
                    ],
                    ['Rumah kecil / kontrakan tanpa PLN', 'Backup lampu, TV, kipas, dan router', 'Warung, kios, dan lapak pasar', 'Rumah kebun & saung'],
                ),
                'specifications' => $this->specs([
                    'Merek' => 'JSD Solar',
                    'Model' => 'XHP12K15',
                    'Tipe' => 'All-in-one solar system (inverter + MPPT + baterai)',
                    'Tegangan Baterai' => '12,8 VDC',
                    'Kapasitas Baterai' => '1.536 Wh (LiFePO4)',
                    'Daya Output Inverter' => '1.200 W',
                    'Tegangan Output' => '230 VAC, 50/60 Hz (otomatis)',
                    'Daya Input AC Maks' => '2.000 W',
                    'Rentang Tegangan Input AC' => '90–280 VAC',
                    'Daya PV Maks' => '1.000 W',
                    'Rentang MPPT' => '20–100 VDC',
                    'Tegangan Input PV' => '15–125 VDC',
                    'Arus Pengisian' => '0–60 A',
                    'Efisiensi MPPT' => 'Hingga 99,5%',
                    'Pendinginan' => 'Kipas pintar (smart fan)',
                    'Suhu Operasi' => '-20°C s/d +50°C',
                    'Kelembapan' => '0–90% tanpa kondensasi',
                    'Dimensi (L×P×T)' => '370 × 132 × 504 mm',
                    'Berat' => '19,5 kg (kotor 20,7 kg)',
                ]),
                'keywords' => 'all in one solar, jsd solar, xhp12k15, ess, inverter baterai jadi satu, 1.2kw, lifepo4, energy storage',
                'meta_title' => 'All-in-One Solar System JSD Solar XHP12K15 1,2kW + Baterai 1,5kWh — Energi.Click',
                'meta_description' => 'Jual JSD Solar XHP12K15: inverter 1.200W, MPPT, dan baterai LiFePO4 1.536Wh dalam satu unit. Garansi 2 tahun.',
            ],
            [
                'slug' => 'all-in-one-solar-system-jsd-solar-xhp4k30-4kw',
                'sku' => 'JSD-XHP4K30',
                'name' => 'All-in-One Solar System JSD Solar XHP4K30 4kW',
                'model' => 'XHP4K30',
                'categories' => ['baterai-all-in-one-ess'],
                'price' => 17850000,
                'stock' => 2,
                'weight_grams' => 40000,
                'requires_freight' => true,
                'warranty' => 'Garansi 2 Tahun',
                'estimated_processing' => '1-3 hari kerja',
                'short_description' => 'Sistem all-in-one 4kW dari JSD Solar — inverter, solar charger, dan baterai terintegrasi dalam satu unit. Spesifikasi rinci menyusul dari pabrikan.',
                'description' => $this->html(
                    '<p><strong>JSD Solar XHP4K30</strong> adalah <strong>all-in-one solar system 4 kW</strong> — inverter, solar charger, dan penyimpanan baterai terintegrasi dalam satu unit, sehingga pemasangan jauh lebih ringkas dibanding merakit komponen terpisah.</p>',
                    [
                        '📦 <strong>Satu unit terintegrasi</strong> — inverter + solar charger + baterai.',
                        '⚡ Kelas daya <strong>4 kW</strong>, sesuai untuk rumah tangga menengah.',
                        '🛡️ Garansi <strong>2 tahun</strong> untuk seluruh sistem all-in-one.',
                    ],
                    ['Rumah tangga 2200–3500 VA', 'Ruko & kantor kecil', 'Lokasi yang ingin instalasi ringkas'],
                ).'<p><em>Catatan: brosur teknis resmi untuk model ini sedang kami mintakan ke pabrikan. Untuk kebutuhan spesifik (kapasitas baterai, kapasitas MPPT, dimensi), silakan hubungi CS agar kami konfirmasikan langsung ke JSD Solar sebelum Anda memesan.</em></p>',
                'specifications' => $this->specs([
                    'Merek' => 'JSD Solar',
                    'Model' => 'XHP4K30',
                    'Tipe' => 'All-in-one solar system (inverter + solar charger + baterai)',
                    'Kelas Daya' => '4 kW',
                    'Garansi' => '2 tahun',
                    'Spesifikasi Teknis Rinci' => 'Menyusul dari pabrikan — silakan konfirmasi ke CS sebelum memesan',
                    'Berat Pengiriman' => '±40 kg (estimasi, dikonfirmasi ulang sebelum pengiriman)',
                ]),
                'keywords' => 'all in one solar, jsd solar, xhp4k30, ess, 4kw, inverter baterai jadi satu, energy storage',
                'meta_title' => 'All-in-One Solar System JSD Solar XHP4K30 4kW — Energi.Click',
                'meta_description' => 'Jual JSD Solar XHP4K30 all-in-one solar system 4kW: inverter, solar charger, dan baterai terintegrasi. Garansi 2 tahun.',
            ],
            [
                'slug' => 'all-in-one-solar-system-jsd-solar-jhp5000-5kw-layar-sentuh',
                'sku' => 'JSD-JHP5000',
                'name' => 'All-in-One Solar System JSD Solar JHP5000 5kW (Layar Sentuh)',
                'model' => 'JHP5000',
                'categories' => ['baterai-all-in-one-ess'],
                'price' => 28150000,
                'stock' => 2,
                'weight_grams' => 60000,
                'requires_freight' => true,
                'warranty' => 'Garansi 2 Tahun',
                'estimated_processing' => '1-3 hari kerja',
                'short_description' => 'Sistem all-in-one 5kW dengan panel kontrol layar sentuh — inverter, solar charger, dan baterai dalam satu unit. Spesifikasi rinci menyusul dari pabrikan.',
                'description' => $this->html(
                    '<p><strong>JSD Solar JHP5000</strong> adalah <strong>all-in-one solar system 5 kW</strong> dengan <strong>panel kontrol layar sentuh</strong> — status sistem, produksi surya, dan pengaturan mode kerja diakses langsung dari layar, tanpa menghafal kombinasi tombol.</p>',
                    [
                        '📱 <strong>Layar sentuh</strong> — pemantauan dan pengaturan yang jauh lebih mudah dibaca.',
                        '📦 <strong>Satu unit terintegrasi</strong> — inverter + solar charger + baterai.',
                        '⚡ Kelas daya <strong>5 kW</strong> untuk rumah tangga besar dan usaha.',
                        '🛡️ Garansi <strong>2 tahun</strong> untuk seluruh sistem all-in-one.',
                    ],
                    ['Rumah besar & vila', 'Kantor dan ruko', 'Usaha yang butuh listrik stabil', 'Pengganti genset harian'],
                ).'<p><em>Catatan: brosur teknis resmi untuk model ini sedang kami mintakan ke pabrikan. Untuk kebutuhan spesifik (kapasitas baterai, kapasitas MPPT, dimensi), silakan hubungi CS agar kami konfirmasikan langsung ke JSD Solar sebelum Anda memesan.</em></p>',
                'specifications' => $this->specs([
                    'Merek' => 'JSD Solar',
                    'Model' => 'JHP5000',
                    'Tipe' => 'All-in-one solar system (inverter + solar charger + baterai)',
                    'Kelas Daya' => '5 kW',
                    'Antarmuka' => 'Panel kontrol layar sentuh',
                    'Garansi' => '2 tahun',
                    'Spesifikasi Teknis Rinci' => 'Menyusul dari pabrikan — silakan konfirmasi ke CS sebelum memesan',
                    'Berat Pengiriman' => '±60 kg (estimasi, dikonfirmasi ulang sebelum pengiriman)',
                ]),
                'keywords' => 'all in one solar, jsd solar, jhp5000, ess, 5kw, layar sentuh, touchscreen, energy storage',
                'meta_title' => 'All-in-One Solar System JSD Solar JHP5000 5kW Layar Sentuh — Energi.Click',
                'meta_description' => 'Jual JSD Solar JHP5000 all-in-one solar system 5kW dengan layar sentuh. Inverter, solar charger, dan baterai terintegrasi. Garansi 2 tahun.',
            ],
            [
                'slug' => 'all-in-one-solar-system-jsd-solar-xhp65k60-6-5kw',
                'sku' => 'JSD-XHP65K60',
                'name' => 'All-in-One Solar System JSD Solar XHP65K60 6,5kW',
                'model' => 'XHP65K60',
                'categories' => ['baterai-all-in-one-ess'],
                'price' => 28750000,
                'stock' => 2,
                'weight_grams' => 70000,
                'requires_freight' => true,
                'warranty' => 'Garansi 2 Tahun',
                'estimated_processing' => '1-3 hari kerja',
                'short_description' => 'Sistem all-in-one 6,5kW dari JSD Solar — kapasitas terbesar di seri ini, inverter dan baterai terintegrasi. Spesifikasi rinci menyusul dari pabrikan.',
                'description' => $this->html(
                    '<p><strong>JSD Solar XHP65K60</strong> adalah <strong>all-in-one solar system 6,5 kW</strong> — kapasitas terbesar pada seri all-in-one JSD Solar, dengan inverter, solar charger, dan baterai terintegrasi dalam satu unit.</p>',
                    [
                        '📦 <strong>Satu unit terintegrasi</strong> — inverter + solar charger + baterai.',
                        '⚡ Kelas daya <strong>6,5 kW</strong> untuk beban rumah besar maupun usaha.',
                        '🛡️ Garansi <strong>2 tahun</strong> untuk seluruh sistem all-in-one.',
                    ],
                    ['Rumah besar & vila (>5500 VA)', 'Kantor, hotel kecil, dan resort', 'Usaha dengan beban harian tinggi'],
                ).'<p><em>Catatan: brosur teknis resmi untuk model ini sedang kami mintakan ke pabrikan. Untuk kebutuhan spesifik (kapasitas baterai, kapasitas MPPT, dimensi), silakan hubungi CS agar kami konfirmasikan langsung ke JSD Solar sebelum Anda memesan.</em></p>',
                'specifications' => $this->specs([
                    'Merek' => 'JSD Solar',
                    'Model' => 'XHP65K60',
                    'Tipe' => 'All-in-one solar system (inverter + solar charger + baterai)',
                    'Kelas Daya' => '6,5 kW',
                    'Garansi' => '2 tahun',
                    'Spesifikasi Teknis Rinci' => 'Menyusul dari pabrikan — silakan konfirmasi ke CS sebelum memesan',
                    'Berat Pengiriman' => '±70 kg (estimasi, dikonfirmasi ulang sebelum pengiriman)',
                ]),
                'keywords' => 'all in one solar, jsd solar, xhp65k60, ess, 6.5kw, inverter baterai jadi satu, energy storage',
                'meta_title' => 'All-in-One Solar System JSD Solar XHP65K60 6,5kW — Energi.Click',
                'meta_description' => 'Jual JSD Solar XHP65K60 all-in-one solar system 6,5kW: inverter, solar charger, dan baterai terintegrasi. Garansi 2 tahun.',
            ],
        ];
    }

    /** Baterai LiFePO4: 12V/24V portabel sampai rack & wall-mount 48V. */
    private function batteries(): array
    {
        return [
            [
                'slug' => 'baterai-lithium-lifepo4-jsd-solar-j12100-12v-100ah',
                'sku' => 'JSD-J12100',
                'name' => 'Baterai Lithium LiFePO4 JSD Solar J12100 12V 100Ah (1,28 kWh)',
                'model' => 'J12100',
                'categories' => ['baterai-lithium-lifepo4'],
                'price' => 3850000,
                'stock' => 8,
                'weight_grams' => 12000,
                'length_cm' => 26.0,
                'width_cm' => 16.8,
                'height_cm' => 21.1,
                'unit' => 'pcs',
                'warranty' => 'Garansi 3 Tahun',
                'estimated_processing' => '1-3 hari kerja',
                'short_description' => 'Baterai LiFePO4 12,8V 100Ah (1.280 Wh) dengan BMS 100A, IP65, proteksi suhu rendah, dan pemantauan Bluetooth. Umur 6.000 siklus.',
                'description' => $this->html(
                    '<p><strong>JSD Solar J12100</strong> adalah baterai <strong>LiFePO4 12,8V 100Ah</strong> (energi <strong>1.280 Wh</strong>) dengan <strong>BMS 100A</strong> dan sel <strong>prismatik</strong>. Dibanding aki basah/VRLA berkapasitas sama, baterai ini jauh lebih ringan, boleh dikuras sampai habis, dan usia pakainya berkali lipat lebih panjang.</p>',
                    [
                        '♻️ <strong>6.000 siklus</strong> pada 100% DOD — dipakai penuh setiap hari pun bertahun-tahun umurnya. Aki VRLA biasanya habis di 300–500 siklus.',
                        '⚡ <strong>Keluaran kontinu 1.280 W</strong> (arus 100 A) — sanggup menyalakan beban besar sesaat tanpa BMS memutus.',
                        '📱 <strong>Bluetooth</strong> — pindai QR di bodi baterai, lalu pantau tegangan, arus, dan sisa kapasitas dari HP.',
                        '❄️ <strong>Proteksi suhu rendah</strong> — pengisian otomatis diblokir saat sel terlalu dingin, penyebab kerusakan permanen yang sering luput.',
                        '💦 <strong>IP65</strong>, casing ABS tahan api — aman di gudang, kendaraan, maupun ruang lembap.',
                        '🔩 Terminal <strong>M8×1.25</strong>, sudah termasuk 2 baut terminal + tutup isolasi.',
                        '🔌 Impedansi internal <strong>≤40 mΩ</strong> — rugi daya kecil, panas rendah.',
                    ],
                    ['Sistem solar 12V', 'Backup lampu, TV, kipas, dan router', 'Pengganti aki VRLA/deep-cycle', 'Kendaraan camper, perahu, dan food truck'],
                ).'<p><em>Catatan penting: baterai ini <strong>bukan aki starter</strong> — jangan dipakai untuk menghidupkan mesin kendaraan. Untuk penyimpanan lama, simpan pada kondisi ±50% dan isi ulang tiap 3 bulan. Berat unit tidak dicantumkan pabrikan; kami konfirmasikan sebelum pengiriman.</em></p>',
                'specifications' => $this->specs([
                    'Merek' => 'JSD Solar',
                    'Model' => 'J12100',
                    'Tipe Sel' => 'LiFePO4 prismatik (Lithium Iron Phosphate)',
                    'Tegangan Nominal' => '12,8 VDC',
                    'Kapasitas' => '100 Ah',
                    'Energi' => '1.280 Wh',
                    'Impedansi Internal' => '≤40 mΩ',
                    'Umur Siklus' => '6.000 siklus (25°C, 0.2C, 100% DOD)',
                    'BMS' => '100 A',
                    'Metode Pengisian' => 'CC/CV',
                    'Tegangan Pengisian' => '14,4 V ±0,2 V',
                    'Arus Pengisian Disarankan' => '20 A (0.2C)',
                    'Arus Pengisian Kontinu Maks' => '100 A',
                    'Arus Pengosongan Kontinu Maks' => '100 A',
                    'Daya Keluaran Kontinu Maks' => '1.280 W',
                    'Terminal' => 'M8 × 1.25 (baut terminal &amp; tutup isolasi termasuk)',
                    'Bahan Casing' => 'ABS tahan api',
                    'Proteksi Ingress' => 'IP65',
                    'Proteksi Suhu Rendah' => 'Ya',
                    'Pemantauan' => 'Bluetooth (aplikasi, pindai QR di bodi baterai)',
                    'Suhu Pengisian' => '0°C s/d +50°C',
                    'Suhu Pengosongan' => '-20°C s/d +60°C',
                    'Suhu Penyimpanan' => '-10°C s/d +50°C',
                    'Dimensi (P×L×T)' => '260 × 168 × 211 mm',
                    'Garansi' => '3 tahun',
                    'Berat Pengiriman' => '±12 kg (estimasi — tidak dicantumkan pabrikan)',
                ]),
                'keywords' => 'baterai lithium, lifepo4, jsd solar, j12100, 12v 100ah, 1280wh, baterai solar, pengganti aki, bms, bluetooth, ip65, 6000 siklus',
                'meta_title' => 'Baterai Lithium LiFePO4 JSD Solar J12100 12,8V 100Ah Bluetooth — Energi.Click',
                'meta_description' => 'Jual baterai LiFePO4 JSD Solar J12100 12,8V 100Ah (1.280 Wh), BMS 100A, IP65, Bluetooth, 6.000 siklus. Garansi 3 tahun.',
            ],
            [
                'slug' => 'baterai-lithium-lifepo4-jsd-solar-j12200-12v-200ah',
                'sku' => 'JSD-J12200',
                'name' => 'Baterai Lithium LiFePO4 JSD Solar J12200 12V 200Ah (2,56 kWh)',
                'model' => 'J12200',
                'categories' => ['baterai-lithium-lifepo4'],
                'price' => 7450000,
                'stock' => 6,
                'weight_grams' => 22000,
                'length_cm' => 48.4,
                'width_cm' => 17.0,
                'height_cm' => 24.0,
                'unit' => 'pcs',
                'warranty' => 'Garansi 3 Tahun',
                'estimated_processing' => '1-3 hari kerja',
                'short_description' => 'Baterai LiFePO4 12,8V 200Ah (2.560 Wh) dengan BMS 200A dan keluaran kontinu 2.560 W. IP65, proteksi suhu rendah, umur 6.000 siklus.',
                'description' => $this->html(
                    '<p><strong>JSD Solar J12200</strong> adalah baterai <strong>LiFePO4 12,8V 200Ah</strong> (energi <strong>2.560 Wh</strong>) dengan <strong>BMS 200A</strong>. Satu unit menggantikan dua baterai 100Ah yang diparalel — kabel lebih sedikit, titik sambungan lebih sedikit, dan risiko gangguan lebih kecil.</p>',
                    [
                        '🔋 <strong>2.560 Wh dalam satu unit</strong> — tidak perlu memparalel dua baterai beserta kabel penyeimbangnya.',
                        '⚡ <strong>Keluaran kontinu 2.560 W</strong> (arus 200 A) — sanggup menopang inverter kelas 2 kW tanpa BMS memutus.',
                        '♻️ <strong>6.000 siklus</strong> pada 100% DOD. Aki VRLA biasanya habis di 300–500 siklus.',
                        '❄️ <strong>Proteksi suhu rendah</strong> — pengisian otomatis diblokir saat sel terlalu dingin.',
                        '💦 <strong>IP65</strong>, casing ABS tahan api — aman di gudang, kendaraan, maupun ruang lembap.',
                        '🔌 Impedansi internal <strong>≤40 mΩ</strong> — rugi daya kecil, panas rendah.',
                    ],
                    ['Sistem solar 12V berkapasitas lebih besar', 'Backup rumah tangga kecil', 'Pengganti bank aki VRLA', 'Camper, perahu, dan mobil dinas lapangan'],
                ).'<p><em>Catatan penting: baterai ini <strong>bukan aki starter</strong> — jangan dipakai untuk menghidupkan mesin kendaraan. Model ini <strong>tidak dilengkapi Bluetooth</strong> (tersedia pada J12100). Berat unit tidak dicantumkan pabrikan; kami konfirmasikan sebelum pengiriman.</em></p>',
                'specifications' => $this->specs([
                    'Merek' => 'JSD Solar',
                    'Model' => 'J12200',
                    'Tipe Sel' => 'LiFePO4 prismatik (Lithium Iron Phosphate)',
                    'Tegangan Nominal' => '12,8 VDC',
                    'Kapasitas' => '200 Ah',
                    'Energi' => '2.560 Wh',
                    'Impedansi Internal' => '≤40 mΩ',
                    'Umur Siklus' => '6.000 siklus (25°C, 0.2C, 100% DOD)',
                    'BMS' => '200 A',
                    'Metode Pengisian' => 'CC/CV',
                    'Tegangan Pengisian' => '14,4 V ±0,2 V',
                    'Arus Pengisian Disarankan' => '40 A (0.2C)',
                    'Arus Pengisian Kontinu Maks' => '200 A',
                    'Arus Pengosongan Kontinu Maks' => '200 A',
                    'Daya Keluaran Kontinu Maks' => '2.560 W',
                    'Bahan Casing' => 'ABS tahan api',
                    'Proteksi Ingress' => 'IP65',
                    'Proteksi Suhu Rendah' => 'Ya',
                    'Pemantauan Bluetooth' => 'Tidak tersedia pada model ini',
                    'Suhu Pengisian' => '0°C s/d +50°C',
                    'Suhu Pengosongan' => '-20°C s/d +60°C',
                    'Suhu Penyimpanan' => '-10°C s/d +50°C',
                    'Dimensi (P×L×T)' => '484 × 170 × 240 mm',
                    'Garansi' => '3 tahun',
                    'Berat Pengiriman' => '±22 kg (estimasi — tidak dicantumkan pabrikan)',
                ]),
                'keywords' => 'baterai lithium, lifepo4, jsd solar, j12200, 12v 200ah, 2560wh, baterai solar, pengganti aki, bms 200a, ip65, 6000 siklus',
                'meta_title' => 'Baterai Lithium LiFePO4 JSD Solar J12200 12,8V 200Ah — Energi.Click',
                'meta_description' => 'Jual baterai LiFePO4 JSD Solar J12200 12,8V 200Ah (2.560 Wh), BMS 200A, keluaran 2.560 W, IP65, 6.000 siklus. Garansi 3 tahun.',
            ],
            [
                'slug' => 'baterai-lithium-lifepo4-jsd-solar-j24100-24v-100ah',
                'sku' => 'JSD-J24100',
                'name' => 'Baterai Lithium LiFePO4 JSD Solar J24100 24V 100Ah (2,56 kWh)',
                'model' => 'J24100',
                'categories' => ['baterai-lithium-lifepo4'],
                'price' => 7450000,
                'stock' => 6,
                'weight_grams' => 22000,
                'length_cm' => 48.4,
                'width_cm' => 17.0,
                'height_cm' => 24.0,
                'unit' => 'pcs',
                'warranty' => 'Garansi 3 Tahun',
                'estimated_processing' => '1-3 hari kerja',
                'short_description' => 'Baterai LiFePO4 25,6V 100Ah (2.560 Wh) dengan BMS 100A — pasangan tepat untuk inverter sistem 24V seperti JSD J4000E. IP65, 6.000 siklus.',
                'description' => $this->html(
                    '<p><strong>JSD Solar J24100</strong> adalah baterai <strong>LiFePO4 25,6V 100Ah</strong> (energi <strong>2.560 Wh</strong>) dengan <strong>BMS 100A</strong> — dirancang untuk inverter sistem 24V, misalnya <strong>JSD Solar J4000E</strong>. Karena tegangannya dua kali lipat sistem 12V, arus yang mengalir jadi separuh: kabel lebih kecil dan rugi daya lebih rendah pada energi tersimpan yang sama.</p>',
                    [
                        '⚡ Sistem 24V — pada energi yang sama, <strong>arusnya separuh sistem 12V</strong>: kabel lebih kecil, panas dan rugi daya lebih rendah.',
                        '🔋 <strong>2.560 Wh</strong> energi tersimpan, keluaran kontinu <strong>2.560 W</strong>.',
                        '♻️ <strong>6.000 siklus</strong> pada 100% DOD. Aki VRLA biasanya habis di 300–500 siklus.',
                        '❄️ <strong>Proteksi suhu rendah</strong> — pengisian otomatis diblokir saat sel terlalu dingin.',
                        '💦 <strong>IP65</strong>, casing ABS tahan api.',
                        '🔌 Impedansi internal <strong>≤40 mΩ</strong> — rugi daya kecil, panas rendah.',
                    ],
                    ['Sistem solar 24V', 'Pasangan inverter JSD Solar J4000E', 'Backup rumah tangga', 'Peningkatan dari bank aki 24V'],
                ).'<p><em>Catatan penting: baterai ini <strong>bukan aki starter</strong> — jangan dipakai untuk menghidupkan mesin kendaraan. Model ini <strong>tidak dilengkapi Bluetooth</strong> (tersedia pada J12100). Berat unit tidak dicantumkan pabrikan; kami konfirmasikan sebelum pengiriman.</em></p>',
                'specifications' => $this->specs([
                    'Merek' => 'JSD Solar',
                    'Model' => 'J24100',
                    'Tipe Sel' => 'LiFePO4 prismatik (Lithium Iron Phosphate)',
                    'Tegangan Nominal' => '25,6 VDC',
                    'Kapasitas' => '100 Ah',
                    'Energi' => '2.560 Wh',
                    'Impedansi Internal' => '≤40 mΩ',
                    'Umur Siklus' => '6.000 siklus (25°C, 0.2C, 100% DOD)',
                    'BMS' => '100 A',
                    'Metode Pengisian' => 'CC/CV',
                    'Tegangan Pengisian' => '28,8 V ±0,2 V',
                    'Arus Pengisian Disarankan' => '20 A (0.2C)',
                    'Arus Pengisian Kontinu Maks' => '100 A',
                    'Arus Pengosongan Kontinu Maks' => '100 A',
                    'Daya Keluaran Kontinu Maks' => '2.560 W',
                    'Bahan Casing' => 'ABS tahan api',
                    'Proteksi Ingress' => 'IP65',
                    'Proteksi Suhu Rendah' => 'Ya',
                    'Pemantauan Bluetooth' => 'Tidak tersedia pada model ini',
                    'Suhu Pengisian' => '0°C s/d +50°C',
                    'Suhu Pengosongan' => '-20°C s/d +60°C',
                    'Suhu Penyimpanan' => '-10°C s/d +50°C',
                    'Dimensi (P×L×T)' => '484 × 170 × 240 mm',
                    'Garansi' => '3 tahun',
                    'Berat Pengiriman' => '±22 kg (estimasi — tidak dicantumkan pabrikan)',
                ]),
                'keywords' => 'baterai lithium, lifepo4, jsd solar, j24100, 24v 100ah, 2560wh, baterai solar, bms, j4000e, ip65, 6000 siklus',
                'meta_title' => 'Baterai Lithium LiFePO4 JSD Solar J24100 25,6V 100Ah — Energi.Click',
                'meta_description' => 'Jual baterai LiFePO4 JSD Solar J24100 25,6V 100Ah (2.560 Wh), BMS 100A, IP65, 6.000 siklus. Cocok untuk inverter 24V, garansi 3 tahun.',
            ],
            [
                'slug' => 'baterai-lithium-lifepo4-jsd-solar-bg48100-5-12kwh-wall-mounted',
                'sku' => 'JSD-BG48100',
                'name' => 'Baterai Lithium LiFePO4 JSD Solar BG48100 51,2V 100Ah 5,12 kWh (Wall Mounted)',
                'model' => 'BG48100',
                'categories' => ['baterai-wall-mounted', 'baterai-lithium-lifepo4'],
                'price' => 15000000,
                'stock' => 3,
                'weight_grams' => 42500,
                'length_cm' => 36.3,
                'width_cm' => 16.5,
                'height_cm' => 57.4,
                'requires_freight' => true,
                'warranty' => 'Garansi 5 Tahun',
                'estimated_processing' => '1-3 hari kerja',
                'short_description' => 'Power wall LiFePO4 51,2V 100Ah (5,12 kWh) dengan BMS pintar, komunikasi RS485/RS232/CAN, dan siklus >6000. Bisa digantung di dinding atau berdiri.',
                'description' => $this->html(
                    '<p><strong>JSD Solar BG48100</strong> adalah baterai penyimpan energi <strong>LiFePO4 51,2V 100Ah (5,12 kWh)</strong> berbentuk <strong>power wall</strong> — bisa digantung di dinding atau diletakkan berdiri di lantai. Dilengkapi <strong>BMS pintar</strong> yang menyeimbangkan pengisian dan pengosongan antar sel agar umur pakai maksimal.</p>',
                    [
                        '🔋 <strong>51,2 V / 100 Ah = 5,12 kWh</strong> energi tersimpan.',
                        '♻️ Umur pakai <strong>lebih dari 6.000 siklus</strong> @80% DOD — bertahun-tahun pemakaian harian.',
                        '🔗 <strong>Paralel hingga 15 unit</strong> — kapasitas total bisa tumbuh sampai 76,8 kWh.',
                        '🔌 Komunikasi <strong>RS485 / RS232 / CAN</strong> — kompatibel dengan hampir semua merek inverter hybrid & off-grid.',
                        '⚡ Arus pengisian/pengosongan nominal <strong>50 A</strong>, maksimum <strong>100 A</strong> (5.120 W).',
                        '🛡️ Proteksi lengkap: kelebihan pengisian, pengosongan berlebih, hubung singkat; saklar satu tombol.',
                        '📜 Sertifikasi <strong>CE, RoHS, UN38.3, MSDS, ISO</strong>.',
                    ],
                    ['Penyimpanan energi PLTS rumah tangga', 'Backup harian rumah & ruko', 'Pasangan inverter hybrid/off-grid 48V', 'Sistem yang direncanakan bertambah kapasitas'],
                ).'<p><em>Catatan: dimensi, berat, dan detail modul mengacu pada datasheet power wall 5 kWh JSD Solar. Bila Anda memerlukan konfirmasi tertulis dari pabrikan sebelum memesan, silakan hubungi CS.</em></p>',
                'specifications' => $this->specs([
                    'Merek' => 'JSD Solar',
                    'Model' => 'BG48100',
                    'Tipe Sel' => 'LiFePO4 (Lithium Iron Phosphate)',
                    'Tegangan Nominal' => '51,2 VDC',
                    'Kapasitas' => '100 Ah',
                    'Energi' => '5.120 Wh (5,12 kWh)',
                    'Tegangan Pengisian' => '58,4 VDC',
                    'Tegangan Pengosongan' => '44 VDC',
                    'Arus Pengisian/Pengosongan Nominal' => '50 A (2.560 W)',
                    'Arus Pengisian/Pengosongan Maks' => '100 A (5.120 W)',
                    'Arus Hubung Singkat' => '350 A / 3 ms',
                    'Umur Siklus' => '>6.000 siklus @80% DOD',
                    'Skalabilitas' => 'Paralel hingga 15 unit (maks 76,8 kWh)',
                    'Komunikasi' => 'RS485 / RS232 / CAN; indikator SOC &amp; LED',
                    'Pemasangan' => 'Gantung dinding atau berdiri di lantai',
                    'Suhu Pengisian' => '0°C s/d +55°C',
                    'Suhu Pengosongan' => '-20°C s/d +60°C',
                    'Proteksi Ingress' => 'IP21',
                    'Sertifikasi' => 'CE, RoHS, UN38.3, MSDS, ISO',
                    'Dimensi (P×L×T)' => '363 × 165 × 574 mm',
                    'Berat' => '42,5 kg',
                ]),
                'keywords' => 'baterai lithium, lifepo4, jsd solar, bg48100, power wall, 48v 100ah, 5kwh, wall mounted, ess, baterai plts',
                'meta_title' => 'Baterai LiFePO4 JSD Solar BG48100 5,12 kWh Power Wall 48V — Energi.Click',
                'meta_description' => 'Jual baterai power wall LiFePO4 JSD Solar BG48100 51,2V 100Ah (5,12 kWh), >6000 siklus, RS485/CAN, paralel 15 unit. Garansi 5 tahun.',
            ],
            [
                'slug' => 'baterai-lithium-lifepo4-jsd-solar-j4u48100-5kwh-rack-19-inci',
                'sku' => 'JSD-J4U48100',
                'name' => 'Baterai Lithium LiFePO4 JSD Solar J4U48100 51,2V 100Ah 5 kWh (Rack 19")',
                'model' => 'J4U48100',
                'categories' => ['baterai-rack-mounted', 'baterai-lithium-lifepo4'],
                'price' => 15000000,
                'stock' => 3,
                'weight_grams' => 45000,
                'length_cm' => 48.3,
                'width_cm' => 46.6,
                'height_cm' => 21.7,
                'requires_freight' => true,
                'warranty' => 'Garansi 5 Tahun',
                'estimated_processing' => '1-3 hari kerja',
                'short_description' => 'Baterai rack 19" LiFePO4 51,2V 100Ah (5 kWh) dengan kabel plug-and-play, bracket susun, dan kompatibilitas lintas merek inverter. Siklus >6000.',
                'description' => $this->html(
                    '<p><strong>JSD Solar J4U48100</strong> adalah baterai <strong>rack-mount 19 inci</strong> <strong>LiFePO4 51,2V 100Ah (5 kWh)</strong> — bentuk standar industri yang rapi untuk instalasi bertingkat di dalam kabinet maupun berdiri di lantai.</p>',
                    [
                        '🔌 <strong>Kabel plug-and-play</strong> — terminal tertutup, tidak ada kabel telanjang yang terekspos; pemasangan lebih aman dan cepat.',
                        '🧱 <strong>Bracket susun sudah termasuk</strong> — modul bisa ditumpuk rapi tanpa membeli rak terpisah.',
                        '♻️ Umur pakai <strong>lebih dari 6.000 siklus</strong>.',
                        '⚡ Arus pengisian/pengosongan maksimum <strong>100 A</strong>.',
                        '🔗 Kompatibel dengan <strong>hampir semua merek inverter</strong> hybrid & off-grid dunia (Deye, Growatt-class, Victron, SMA, Sungrow, Huawei, Solis, Goodwe, dan lainnya).',
                        '🛡️ Rating <strong>IP21 &amp; IP65</strong>, komunikasi RS232 / RS485 / CAN.',
                    ],
                    ['Instalasi rack/kabinet PLTS', 'Rumah, ruko, dan kantor', 'Sistem bertingkat yang akan ditambah bertahap', 'Backup server & telekomunikasi skala kecil'],
                ),
                'specifications' => $this->specs([
                    'Merek' => 'JSD Solar',
                    'Model' => 'J4U48100',
                    'Tipe Sel' => 'LiFePO4 (Lithium Iron Phosphate)',
                    'Tegangan Nominal' => '51,2 VDC',
                    'Kapasitas' => '100 Ah',
                    'Energi' => '5 kWh',
                    'Arus Pengisian/Pengosongan Maks' => '100 A',
                    'Tegangan Pengisian Atas' => '57,6 ±0,1 V',
                    'Tegangan Pengosongan Bawah' => '43,2 ±0,1 V',
                    'Impedansi Pack (tanpa BMS)' => '&lt;20 mΩ',
                    'Umur Siklus' => '>6.000 siklus',
                    'Komunikasi' => 'RS232 / RS485 / CAN',
                    'Kompatibilitas Inverter' => 'Mendukung merek inverter hybrid &amp; off-grid utama dunia',
                    'Pemasangan' => 'Rack/kabinet 19" atau berdiri di lantai (bracket susun termasuk)',
                    'Proteksi Ingress' => 'IP21 &amp; IP65',
                    'Suhu Pengisian' => '0°C s/d +55°C',
                    'Suhu Pengosongan' => '-15°C s/d +55°C',
                    'Dimensi (P×L×T)' => '482,6 × 466,45 × 217 mm',
                    'Berat' => '45 kg',
                ]),
                'keywords' => 'baterai lithium, lifepo4, jsd solar, j4u48100, rack mounted, 19 inci, 48v 100ah, 5kwh, ess, baterai plts',
                'meta_title' => 'Baterai LiFePO4 JSD Solar J4U48100 5 kWh Rack 19" 48V — Energi.Click',
                'meta_description' => 'Jual baterai rack 19" LiFePO4 JSD Solar J4U48100 51,2V 100Ah (5 kWh), plug-and-play, >6000 siklus, IP65. Garansi 5 tahun.',
            ],
            [
                'slug' => 'baterai-lithium-lifepo4-jsd-solar-lfp48200-10-24kwh',
                'sku' => 'JSD-LFP48200',
                'name' => 'Baterai Lithium LiFePO4 JSD Solar LFP48200 51,2V 200Ah 10,24 kWh',
                'model' => 'LFP48200',
                'categories' => ['baterai-wall-mounted', 'baterai-lithium-lifepo4'],
                'price' => 28750000,
                'stock' => 2,
                'weight_grams' => 89000,
                'length_cm' => 82.0,
                'width_cm' => 49.4,
                'height_cm' => 14.5,
                'requires_freight' => true,
                'warranty' => 'Garansi 5 Tahun',
                'estimated_processing' => '1-3 hari kerja',
                'short_description' => 'Power wall LiFePO4 51,2V 200Ah (10,24 kWh) — satu unit untuk kebutuhan harian rumah. Siklus >6000, paralel hingga 15 unit (150 kWh).',
                'description' => $this->html(
                    '<p><strong>JSD Solar LFP48200</strong> adalah baterai penyimpan energi <strong>LiFePO4 51,2V 200Ah (10,24 kWh)</strong> — <strong>dua kali kapasitas</strong> unit 5 kWh dalam satu bodi, sehingga satu unit saja sudah cukup untuk menutup pemakaian malam hari sebuah rumah tangga.</p>',
                    [
                        '🔋 <strong>51,2 V / 200 Ah = 10,24 kWh</strong> energi tersimpan.',
                        '♻️ Umur pakai <strong>lebih dari 6.000 siklus</strong> @80% DOD (25°C, 0.5C).',
                        '🔗 <strong>Paralel hingga 15 unit</strong> — total kapasitas hingga 150 kWh.',
                        '⚡ Arus pengisian/pengosongan nominal <strong>100 A</strong>, maksimum <strong>200 A</strong> (10.240 W).',
                        '🔌 Komunikasi <strong>RS232 / RS485 / CAN</strong> — kompatibel dengan hampir semua merek inverter.',
                        '🏠 Pemasangan <strong>gantung dinding atau berdiri di lantai</strong>.',
                        '📜 Sertifikasi <strong>CE, RoHS, UN38.3, MSDS, ISO</strong>.',
                    ],
                    ['Penyimpanan energi PLTS rumah tangga besar', 'Ruko, kantor, dan usaha', 'Sistem hybrid yang ingin mandiri saat malam', 'Pengganti bank aki berkapasitas besar'],
                ).'<p><em>Catatan: dimensi, berat, dan detail modul mengacu pada datasheet power wall 10 kWh JSD Solar. Bila Anda memerlukan konfirmasi tertulis dari pabrikan sebelum memesan, silakan hubungi CS.</em></p>',
                'specifications' => $this->specs([
                    'Merek' => 'JSD Solar',
                    'Model' => 'LFP48200',
                    'Tipe Sel' => 'LiFePO4 (Lithium Iron Phosphate)',
                    'Tegangan Nominal' => '51,2 VDC',
                    'Kapasitas' => '200 Ah',
                    'Energi' => '10.240 Wh (10,24 kWh)',
                    'Tegangan Pengisian' => '56 VDC',
                    'Tegangan Pengosongan' => '44 VDC',
                    'Arus Pengisian/Pengosongan Nominal' => '100 A (5.120 W)',
                    'Arus Pengisian/Pengosongan Maks' => '200 A (10.240 W)',
                    'Arus Hubung Singkat' => '540 A / 3 ms',
                    'Umur Siklus' => '>6.000 siklus @80% DOD (25°C, 0.5C)',
                    'Skalabilitas' => 'Paralel hingga 15 unit (maks 150 kWh)',
                    'Komunikasi' => 'RS232 / RS485 / CAN; indikator SOC &amp; LED',
                    'Pemasangan' => 'Gantung dinding atau berdiri di lantai',
                    'Suhu Pengisian' => '0°C s/d +55°C',
                    'Suhu Pengosongan' => '-20°C s/d +60°C',
                    'Proteksi Ingress' => 'IP21',
                    'Sertifikasi' => 'CE, RoHS, UN38.3, MSDS, ISO',
                    'Dimensi (L×D×T)' => '820 × 494 × 145 mm',
                    'Berat' => '89 kg',
                ]),
                'keywords' => 'baterai lithium, lifepo4, jsd solar, lfp48200, power wall, 48v 200ah, 10kwh, ess, baterai plts, wall mounted',
                'meta_title' => 'Baterai LiFePO4 JSD Solar LFP48200 10,24 kWh Power Wall 48V — Energi.Click',
                'meta_description' => 'Jual baterai power wall LiFePO4 JSD Solar LFP48200 51,2V 200Ah (10,24 kWh), >6000 siklus, paralel 15 unit. Garansi 5 tahun.',
            ],
            [
                'slug' => 'baterai-lithium-lifepo4-jsd-solar-ld48314-16kwh-layar-warna',
                'sku' => 'JSD-LD48314',
                'name' => 'Baterai Lithium LiFePO4 JSD Solar LD48314 51,2V 314Ah 16 kWh (Layar Warna)',
                'model' => 'LD48314',
                'categories' => ['baterai-lithium-lifepo4', 'baterai-wall-mounted'],
                'price' => 34600000,
                'stock' => 2,
                'weight_grams' => 118000,
                'length_cm' => 56.0,
                'width_cm' => 23.5,
                'height_cm' => 70.0,
                'requires_freight' => true,
                'warranty' => 'Garansi 5 Tahun',
                'estimated_processing' => '1-3 hari kerja',
                'short_description' => 'Baterai berdiri LiFePO4 51,2V 314Ah (16 kWh) dengan layar warna dan Bluetooth. Kapasitas terbesar di lini JSD Solar, siklus >6000.',
                'description' => $this->html(
                    '<p><strong>JSD Solar LD48314</strong> adalah baterai penyimpan energi <strong>LiFePO4 51,2V 314Ah — 16 kWh</strong>, kapasitas terbesar di lini JSD Solar. Dilengkapi <strong>layar warna</strong> dan <strong>Bluetooth</strong>, sehingga status baterai bisa dibaca langsung di unit maupun lewat HP.</p>',
                    [
                        '🔋 <strong>51,2 V / 314 Ah = 16 kWh</strong> — cukup untuk kebutuhan harian rumah besar.',
                        '🖥️ <strong>Layar warna</strong> + <strong>Bluetooth</strong> — pemantauan SOC, tegangan, dan arus tanpa alat tambahan.',
                        '⚡ Arus pengisian/pengosongan maksimum <strong>200 A</strong>.',
                        '♻️ Umur pakai <strong>lebih dari 6.000 siklus</strong>.',
                        '🔌 Komunikasi <strong>CAN / RS232 / RS485</strong> — kompatibel dengan hampir semua merek inverter hybrid & off-grid.',
                        '📜 Sertifikasi <strong>CE, RoHS, UN38.3, MSDS, ISO</strong>.',
                        '🏠 Desain <strong>berdiri (floor standing)</strong> dengan roda pemindah.',
                    ],
                    ['Rumah besar & vila dengan pemakaian tinggi', 'Kantor, hotel kecil, dan resort', 'Sistem PLTS hybrid mandiri malam hari', 'Backup usaha yang tidak boleh padam'],
                ),
                'specifications' => $this->specs([
                    'Merek' => 'JSD Solar',
                    'Model' => 'LD48314',
                    'Tipe Sel' => 'LiFePO4 (Lithium Iron Phosphate)',
                    'Tegangan Nominal' => '51,2 VDC',
                    'Kapasitas' => '314 Ah',
                    'Energi' => '16 kWh',
                    'Arus Pengisian/Pengosongan Maks' => '200 A',
                    'Umur Siklus' => '>6.000 siklus',
                    'Layar' => 'Layar warna (color display)',
                    'Komunikasi' => 'CAN / RS232 / RS485 / Bluetooth',
                    'Kompatibilitas Inverter' => 'Mendukung merek inverter hybrid &amp; off-grid utama dunia',
                    'Pemasangan' => 'Berdiri di lantai (floor standing)',
                    'Proteksi Ingress' => 'IP21',
                    'Sertifikasi' => 'CE, RoHS, UN38.3, MSDS, ISO',
                    'Dimensi (P×L×T)' => '560 × 235 × 700 mm',
                    'Berat' => '118 kg',
                ]),
                'keywords' => 'baterai lithium, lifepo4, jsd solar, ld48314, 16kwh, 48v 314ah, layar warna, bluetooth, ess, baterai plts',
                'meta_title' => 'Baterai LiFePO4 JSD Solar LD48314 16 kWh 51,2V 314Ah — Energi.Click',
                'meta_description' => 'Jual baterai LiFePO4 JSD Solar LD48314 51,2V 314Ah (16 kWh) dengan layar warna & Bluetooth, >6000 siklus. Garansi 5 tahun.',
            ],
        ];
    }

    /** Deskripsi produk: paragraf pembuka + daftar keunggulan + daftar peruntukan. */
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
            app(StockService::class)->adjust($product, null, $delta, StockMovementType::Purchase, note: 'Stok awal (seeder JSD Solar)');
        }
    }
}
