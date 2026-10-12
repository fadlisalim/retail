<?php

namespace Database\Seeders;

use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\WarehouseStock;
use App\Services\StockService;
use App\Services\WatermarkService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Katalog Paket PLTS (Okt 2026) dari assets/paket-plts/paket.csv + isi-paket.csv:
 * 46 paket → 18 produk BERVARIAN:
 *  - Paket SIAGA (backup ringan): 1 produk, varian kapasitas 0,5 / 1 / 1,5 / 2 kWp;
 *  - PLTS Hybrid On-Grid 1 & 3 fasa: 1 produk per kapasitas, varian Standard / Premium;
 *  - PLTS Hybrid Off-Grid 1 & 3 fasa: 1 produk per kapasitas, varian Ekonomis / Standard / Premium.
 * Harga jual = harga_material_rp (material, tanpa jasa pasang & ongkir); modal = total_modal_rp.
 * Berat/dimensi/jumlah kolli ESTIMASI dari kWp panel, kWh baterai, kW inverter — rapikan
 * di Edit Cepat Produk bila ada angka nyata. Idempotent: produk/varian yang ada tidak ditimpa.
 */
class PaketPltsSeeder extends Seeder
{
    private const ASSETS = __DIR__.'/assets/paket-plts';

    private int $created = 0;

    private int $variantsCreated = 0;

    private int $skipped = 0;

    public function run(): void
    {
        $rows = $this->csv(self::ASSETS.'/paket.csv');
        $bom = collect($this->csv(self::ASSETS.'/isi-paket.csv'))->groupBy('sku');
        if (! $rows) {
            $this->command?->error('assets/paket-plts/paket.csv tidak ditemukan / kosong.');

            return;
        }

        $categories = Category::pluck('id', 'slug');
        $groups = collect($rows)->where('status', 'aktif')->groupBy(fn ($r) => $this->groupKey($r['sku']));

        foreach ($groups as $key => $variants) {
            $variants = $variants->values();
            $first = $variants->first();
            $meta = $this->productMeta($key, $first, $variants);

            // Produk yang pernah dihapus (soft delete) masih memegang slug/SKU → pulihkan, jangan buat ganda.
            $existing = Product::withTrashed()->where('sku', $key)->orWhere('slug', $meta['slug'])->first();
            if ($existing?->trashed()) {
                $existing->restore();
            }
            $cheapest = $variants->sortBy(fn ($r) => (float) $r['harga_material_rp'])->first();
            $est = $this->estimate($cheapest);

            $product = $existing ?? Product::create([
                'sku' => $key,
                'slug' => $meta['slug'],
                'name' => $meta['name'],
                'category_id' => $categories[$meta['category']] ?? $categories['paket-plts'] ?? null,
                'brand_id' => null,
                'model' => $first['sistem'].' · '.$first['fasa'],
                'product_type' => 'variable',
                'condition' => 'new',
                'short_description' => $meta['short'],
                'description' => $this->description($meta, $variants),
                'specifications' => $this->specifications($first, $bom->get($first['sku']) ?? collect()),
                'price' => (float) $cheapest['harga_material_rp'],
                'sale_price' => null,
                'cost_price' => (float) $cheapest['total_modal_rp'],
                'unit' => 'paket',
                'weight_grams' => $est['weight'],
                'length_cm' => $est['dims'][0], 'width_cm' => $est['dims'][1], 'height_cm' => $est['dims'][2],
                'package_count' => $est['kolli'],
                'requires_freight' => true,
                'warranty' => 'Inverter '.$first['garansi_inverter_tahun'].' th · baterai '.$variants->pluck('garansi_baterai_tahun')->unique()->implode('/').' th · panel 12 th produk / 25 th output',
                'estimated_processing' => '7-14 hari kerja',
                'keywords' => $meta['keywords'],
                'is_new' => true,
                'is_featured' => $key === 'SIAGA' || str_ends_with($key, '-5K') || $key === 'HFG-1P-10',
                'status' => 'published',
                'published_at' => now(),
                'meta_title' => $meta['name'].' — Harga Material, Garansi Resmi',
                'meta_description' => Str::limit($meta['short'], 155),
            ]);

            if ($existing) {
                $this->skipped++;
            } else {
                $this->created++;
                $product->categories()->sync(array_values(array_filter(array_map(fn ($slug) => $categories[$slug] ?? null, $meta['categories']))));
            }

            foreach ($variants as $i => $row) {
                $e = $this->estimate($row);
                $variant = ProductVariant::firstOrCreate(['sku' => $row['sku']], [
                    'product_id' => $product->id,
                    'name' => $this->variantName($key, $row),
                    'option_values' => [$key === 'SIAGA' ? 'Kapasitas' : 'Level' => $this->variantName($key, $row)],
                    'price' => (float) $row['harga_material_rp'],
                    'sale_price' => null,
                    'cost_price' => (float) $row['total_modal_rp'],
                    'weight_grams' => $e['weight'],
                    'length_cm' => $e['dims'][0], 'width_cm' => $e['dims'][1], 'height_cm' => $e['dims'][2],
                    'is_active' => true,
                    'sort_order' => $i,
                ]);
                if ($variant->wasRecentlyCreated) {
                    $this->variantsCreated++;
                    $this->setStock($product, $variant, 3);
                }
            }

            $this->syncImages($product);
        }

        $this->command?->info("Paket PLTS: {$this->created} produk baru, {$this->variantsCreated} varian baru, {$this->skipped} produk sudah ada (tidak diubah).");
        $this->command?->warn('Berat/dimensi/kolli estimasi & stok awal 3/varian placeholder — rapikan di Edit Cepat Produk. Foto hanya untuk SKU yang ada di assets/paket-plts/images — sisanya upload lewat Admin.');
    }

    // ----------------------------------------------------------------- grouping

    private function groupKey(string $sku): string
    {
        if (str_starts_with($sku, 'SIAGA-')) {
            return 'SIAGA';
        }

        return preg_replace('/-(E|S|P)$/', '', $sku);
    }

    private function variantName(string $key, array $r): string
    {
        $bat = $this->fmt($r['baterai_total_kwh']).' kWh';
        if ($key === 'SIAGA') {
            return $this->fmt($r['panel_kwp']).' kWp · Inverter '.$this->fmt($r['inverter_total_kw']).' kW · Baterai '.$bat;
        }

        return $r['level'].' · Inverter '.$this->fmt($r['inverter_total_kw']).' kW · Baterai '.$bat.' · garansi baterai '.$r['garansi_baterai_tahun'].' th';
    }

    /** @return array{slug: string, name: string, short: string, category: string, categories: list<string>, keywords: string} */
    private function productMeta(string $key, array $r, $variants): array
    {
        $kwp = $this->fmt($r['panel_kwp']);
        $inv = $this->fmt($r['inverter_total_kw']);
        if ($key === 'SIAGA') {
            return [
                'slug' => 'paket-siaga-plts-backup-ringan-0-5-2-kwp',
                'name' => 'Paket SIAGA PLTS Backup Ringan 0,5–2 kWp (Lampu, Wi-Fi, Kulkas, CCTV)',
                'short' => 'Backup listrik ringan saat PLN padam: panel surya 0,5–2 kWp, inverter off-grid, baterai LiFePO4, panel proteksi, kabel & grounding. Pilih kapasitas sesuai beban prioritas. Harga material, belum termasuk jasa pasang & ongkir.',
                'category' => 'paket-plts-off-grid',
                'categories' => ['paket-plts-off-grid', 'paket-plts-rumah', 'paket-plts'],
                'keywords' => 'paket plts backup, plts rumah murah, backup listrik padam, inverter off grid, baterai lifepo4, paket siaga, plts 1 kwp, plts 2 kwp',
            ];
        }
        $onGrid = str_starts_with($key, 'HNG');
        $sistem = $onGrid ? 'Hybrid On-Grid' : 'Hybrid Off-Grid';
        $fasa = $r['fasa'];
        $levels = $variants->pluck('level')->implode(' / ');
        // Inverter hanya masuk nama produk sebagai angka tunggal bila sama di semua varian (Off-Grid: beda per level).
        $invs = $variants->pluck('inverter_total_kw')->map(fn ($v) => $this->fmt($v))->unique();
        $invLabel = $invs->count() === 1 ? "Inverter {$inv} kW" : 'Inverter '.$invs->sortBy(fn ($v) => (float) str_replace(',', '.', $v))->implode('–').' kW';

        return [
            'slug' => Str::slug('paket plts '.$sistem.' '.$fasa.' '.$kwp.' kwp'),
            'name' => "Paket PLTS {$sistem} {$fasa} {$kwp} kWp · {$invLabel} ({$levels})",
            'short' => "PLTS {$sistem} {$fasa}: panel surya {$kwp} kWp, ".lcfirst($invLabel).', baterai LiFePO4 '.$variants->pluck('baterai_total_kwh')->map(fn ($v) => $this->fmt($v))->unique()->implode('–').' kWh, panel proteksi lengkap, kabel & grounding. Pilih level '.$levels.'. Harga material, belum termasuk jasa pasang & ongkir.',
            'category' => $onGrid ? 'paket-plts-hybrid' : 'paket-plts-off-grid',
            'categories' => array_values(array_unique([$onGrid ? 'paket-plts-hybrid' : 'paket-plts-off-grid', 'paket-plts-hybrid', $fasa === '3 Fasa' ? 'paket-plts-kantor' : 'paket-plts-rumah', 'paket-plts'])),
            'keywords' => 'paket plts '.strtolower($sistem).', plts '.$kwp.' kwp, plts '.strtolower($fasa).', inverter hybrid '.$inv.' kw, baterai lifepo4, plts atap, harga plts '.$kwp.' kwp, plts dengan baterai',
        ];
    }

    // ------------------------------------------------------------------ content

    private function description(array $meta, $variants): string
    {
        $first = $variants->first();
        $rows = '';
        foreach ($variants as $r) {
            $opsi = [];
            if ($r['opsi_garansi_baterai_10th_rp'] !== '') {
                $opsi[] = 'garansi baterai 10 th +'.rupiah((float) $r['opsi_garansi_baterai_10th_rp']);
            }
            if ($r['opsi_nidi_slo_rp'] !== '') {
                $opsi[] = 'NIDI/SLO +'.rupiah((float) $r['opsi_nidi_slo_rp']);
            }
            $rows .= '<tr><td><strong>'.e($this->shortLevel($r)).'</strong></td><td>'.e($r['inverter_spesifikasi']).'</td><td>'.e($r['baterai_spesifikasi']).($r['baterai_jumlah_unit'] > 1 ? ' × '.$r['baterai_jumlah_unit'] : '').'</td><td>Inverter '.$r['garansi_inverter_tahun'].' th · baterai '.$r['garansi_baterai_tahun'].' th</td><td>'.e(implode('; ', $opsi) ?: '—').'</td><td>'.rupiah((float) $r['harga_material_rp']).'</td></tr>';
        }
        $intro = $first['sistem'] === 'Hybrid On-Grid'
            ? '<p><strong>PLTS Hybrid On-Grid</strong> — siang hari panel surya menyuplai rumah/kantor dan mengisi baterai, kelebihan daya mengurangi tagihan PLN; saat PLN padam, inverter otomatis memakai baterai untuk <strong>beban prioritas (jalur backup)</strong>. Cocok untuk yang ingin hemat tagihan sekaligus punya cadangan saat padam.</p>'
            : ($first['level'] === 'Siaga'
                ? '<p><strong>Paket SIAGA</strong> — backup ringan untuk lampu, Wi-Fi, kulkas, dan CCTV saat PLN padam. Panel surya mengisi baterai di siang hari, inverter off-grid menyuplai jalur backup saat listrik padam. Instalasi sederhana, biaya terjangkau.</p>'
                : '<p><strong>PLTS Hybrid Off-Grid</strong> — sistem mandiri dengan baterai besar: panel surya + inverter off-grid/hybrid + baterai LiFePO4 menyuplai beban sepanjang hari, PLN/genset hanya sebagai cadangan pengisian. Cocok untuk lokasi tanpa PLN atau yang ingin lepas dari PLN.</p>');

        return $intro.'
<p><strong>Isi paket (semua varian):</strong> panel surya monocrystalline '.$this->fmt($first['panel_kwp']).' kWp + bracket atap, inverter, baterai lithium LiFePO4, '.e($first['isi_paket_ringkas']).'.</p>
<p><strong>Pilih varian sesuai kebutuhan:</strong></p>
<table><thead><tr><th>Varian</th><th>Inverter</th><th>Baterai</th><th>Garansi</th><th>Opsi tambahan</th><th>Harga material</th></tr></thead><tbody>'.$rows.'</tbody></table>
<ul>
<li>✅ Garansi panel surya 12 tahun produk / 25 tahun output. '.($first['opsi_garansi_baterai_10th_rp'] !== '' ? 'Perpanjangan garansi baterai ke 10 tahun tersedia (lihat tabel).' : '').'</li>
<li>📄 Opsi NIDI/SLO (sertifikasi instalasi listrik) tersedia sebagai tambahan — wajib untuk sistem terhubung PLN di banyak daerah.</li>
<li>🔌 '.e($first['catatan_teknis']).'.</li>
<li>🚚 Harga material; <strong>belum termasuk jasa pemasangan &amp; ongkos kirim</strong>. Pengiriman kargo (ongkir dikonfirmasi admin), jumlah modul panel menyesuaikan Wp modul yang tersedia.</li>
<li>🧑‍🔧 Butuh survei, hitung kebutuhan daya, atau pemasangan? <a href="/konsultasi">Konsultasi gratis</a> atau <a href="/permintaan-penawaran">minta penawaran lengkap</a> (material + jasa).</li>
</ul>';
    }

    private function specifications(array $r, $bom): string
    {
        $html = '<table><tbody>
<tr><th>Sistem</th><td>'.e($r['sistem']).' · '.e($r['fasa']).'</td></tr>
<tr><th>Panel surya</th><td>'.$this->fmt($r['panel_kwp']).' kWp monocrystalline + bracket atap (genteng/metal)</td></tr>
<tr><th>Inverter</th><td>'.e($r['inverter_spesifikasi']).' — '.$r['inverter_jumlah_unit'].' unit × '.$this->fmt($r['inverter_kw_per_unit']).' kW (varian lain lihat tabel di deskripsi)</td></tr>
<tr><th>Baterai</th><td>'.e($r['baterai_spesifikasi']).' — '.$r['baterai_jumlah_unit'].' unit, total '.$this->fmt($r['baterai_total_kwh']).' kWh (varian lain lihat tabel)</td></tr>
<tr><th>Garansi</th><td>Inverter '.$r['garansi_inverter_tahun'].' th · baterai '.$r['garansi_baterai_tahun'].' th · panel '.e($r['garansi_panel']).'</td></tr>
<tr><th>Catatan teknis</th><td>'.e($r['catatan_teknis']).'</td></tr>
</tbody></table>';

        if ($bom->isNotEmpty()) {
            $html .= '<p><strong>Rincian isi paket (varian '.e($this->shortLevel($r)).'; jumlah perangkat utama pada varian lain sesuai tabel deskripsi):</strong></p><table><thead><tr><th>Kelompok</th><th>Komponen</th><th>Spesifikasi</th><th>Qty</th></tr></thead><tbody>';
            foreach ($bom->sortBy(fn ($b) => (int) $b['urutan']) as $b) {
                $html .= '<tr><td>'.e($b['kelompok']).'</td><td>'.e($b['komponen']).'</td><td>'.e($b['spesifikasi']).'</td><td>'.e($b['qty'].' '.$b['satuan']).'</td></tr>';
            }
            $html .= '</tbody></table>';
        }

        return $html;
    }

    private function shortLevel(array $r): string
    {
        return $r['level'] === 'Siaga' ? $this->fmt($r['panel_kwp']).' kWp' : $r['level'];
    }

    // ---------------------------------------------------------------- estimates

    /**
     * Estimasi logistik: modul ≈ 550 Wp @ 28 kg + bracket, baterai ≈ 11 kg/kWh,
     * inverter ≈ 4,5 kg/kW + 8 kg per unit, proteksi + kabel 15 kg + 3 kg/kWp.
     *
     * @return array{weight: int, dims: array{int,int,int}, kolli: int}
     */
    private function estimate(array $r): array
    {
        $kwp = (float) $r['panel_kwp'];
        $kwh = (float) $r['baterai_total_kwh'];
        $kw = (float) $r['inverter_total_kw'];
        $invUnits = max(1, (int) $r['inverter_jumlah_unit']);
        $batUnits = max(1, (int) $r['baterai_jumlah_unit']);
        $modules = max(1, (int) ceil($kwp * 1000 / 550));

        $weight = $modules * 28 + $kwp * 8 + $kwh * 11 + $kw * 4.5 + $invUnits * 8 + 15 + $kwp * 3;
        $kolli = $modules + $batUnits + $invUnits + 2; // + bracket + box proteksi/kabel
        // Dimensi paket terbesar (palet panel): 230×115 cm, tinggi tumpukan ≈ 4 cm/modul + palet.
        $dims = [230, 115, (int) min(120, 15 + $modules * 4)];

        return ['weight' => (int) round($weight * 1000), 'dims' => $dims, 'kolli' => $kolli];
    }

    /**
     * Gambar promosi per varian: assets/paket-plts/images/<sku kecil>.jpg (mis. siaga-500.jpg).
     * Sudah berlogo Energi.click → hanya dioptimalkan ke webp (tanpa watermark). Idempotent:
     * foto varian & utama hanya diisi bila kosong, galeri tidak digandakan.
     */
    private function syncImages(Product $product): void
    {
        $watermark = app(WatermarkService::class);
        $disk = Storage::disk('public');
        foreach ($product->variants()->orderBy('sort_order')->get() as $variant) {
            $src = self::ASSETS.'/images/'.strtolower($variant->sku).'.jpg';
            if (! is_file($src)) {
                continue;
            }
            $stem = 'products/paket-plts/'.strtolower($variant->sku);
            if ($disk->exists("{$stem}.webp")) {
                $final = "{$stem}.webp";
            } else {
                if (! $disk->exists("{$stem}.jpg")) {
                    $disk->put("{$stem}.jpg", (string) file_get_contents($src));
                }
                $final = $watermark->isSupported() ? ($watermark->optimize("{$stem}.jpg") ?? "{$stem}.jpg") : "{$stem}.jpg";
            }
            if (! $variant->image_path) {
                $variant->forceFill(['image_path' => $final])->save();
            }
            if (! $product->images()->where('path', $final)->exists()) {
                $product->images()->create(['path' => $final, 'alt' => $product->name.' — '.$variant->name, 'sort_order' => (int) $variant->sort_order, 'watermarked_at' => now()]);
            }
            if (! $product->main_image_path) {
                $product->forceFill(['main_image_path' => $final])->save();
            }
        }
    }

    private function setStock(Product $product, ProductVariant $variant, int $target): void
    {
        $current = (int) WarehouseStock::where('product_id', $product->id)->where('product_variant_id', $variant->id)->sum('quantity_available');
        if ($target - $current !== 0) {
            app(StockService::class)->adjust($product, $variant, $target - $current, StockMovementType::Purchase, note: 'Stok awal (seeder)');
        }
    }

    // ------------------------------------------------------------------- utils

    private function fmt(string|float $n): string
    {
        $v = (float) $n;

        return rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
    }

    /** @return list<array<string, string>> */
    private function csv(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }
        $h = fopen($path, 'r');
        $header = fgetcsv($h, 0, ',', '"', '');
        if (! $header) {
            return [];
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        $rows = [];
        while (($line = fgetcsv($h, 0, ',', '"', '')) !== false) {
            if (count($line) < count($header)) {
                continue;
            }
            $rows[] = array_combine($header, array_slice($line, 0, count($header)));
        }
        fclose($h);

        return $rows;
    }
}
