<?php

namespace Database\Seeders;

use App\Enums\StockMovementType;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    private StockService $stock;

    private $categories;

    private $brands;

    private $attributes;

    public function run(): void
    {
        $this->stock = app(StockService::class);
        $this->categories = Category::pluck('id', 'slug');
        $this->brands = Brand::pluck('id', 'slug');
        $this->attributes = Attribute::pluck('id', 'slug');

        $this->seedPanels();
        $this->seedInverters();
        $this->seedBatteries();
        $this->seedChargeControllers();
        $this->seedAccessories();
        $this->seedPackages();
        $this->seedSurplus();
        $this->seedQuotationProducts();
    }

    private function seedPanels(): void
    {
        // Panel with wattage variants + full attributes.
        $panel = $this->make([
            'sku' => 'PNL-MONO-550', 'name' => 'Panel Surya Monocrystalline 550Wp Tier-1',
            'category' => 'panel-surya-monocrystalline', 'brand' => 'suryagen',
            'price' => 1450000, 'sale_price' => 1299000, 'weight_grams' => 27500,
            'length_cm' => 227, 'width_cm' => 113, 'height_cm' => 3.5, 'requires_freight' => true,
            'is_featured' => true, 'is_promo' => true, 'warranty' => '12 tahun produk, 25 tahun performa',
            'short' => 'Panel surya monocrystalline 550Wp efisiensi 21% dengan sertifikasi tier-1.',
            'stock' => 120,
            'attributes' => ['Spesifikasi Panel Surya' => [
                'Daya Maksimum' => 550, 'Efisiensi Modul' => 21.3, 'Tegangan Open Circuit' => 49.5,
                'Arus Short Circuit' => 13.9, 'Jenis Sel' => 'Monocrystalline PERC', 'Garansi Produk' => 12, 'Garansi Performa' => 25,
            ]],
        ]);
        $this->addVariants($panel, 'Daya', [
            ['450Wp', 1099000, 40, 22500], ['550Wp', 1299000, 60, 27500], ['600Wp', 1499000, 20, 30000],
        ]);

        $this->make([
            'sku' => 'PNL-BIF-560', 'name' => 'Panel Surya Bifacial 560Wp Double Glass',
            'category' => 'panel-surya-bifacial', 'brand' => 'suryagen',
            'price' => 1650000, 'weight_grams' => 32000, 'length_cm' => 227, 'width_cm' => 113, 'height_cm' => 3.5,
            'requires_freight' => true, 'is_featured' => true, 'warranty' => '15 tahun produk, 30 tahun performa',
            'short' => 'Panel bifacial double-glass, produksi energi ekstra dari sisi belakang.', 'stock' => 60,
            'attributes' => ['Spesifikasi Panel Surya' => ['Daya Maksimum' => 560, 'Efisiensi Modul' => 21.6, 'Jenis Sel' => 'Bifacial Mono PERC']],
        ]);

        $this->make([
            'sku' => 'PNL-FLEX-200', 'name' => 'Panel Surya Flexible 200Wp',
            'category' => 'panel-surya-flexible', 'brand' => 'suryagen',
            'price' => 850000, 'weight_grams' => 3500, 'length_cm' => 120, 'width_cm' => 60, 'height_cm' => 2,
            'is_new' => true, 'short' => 'Panel fleksibel ringan untuk atap lengkung, karavan, dan kapal.', 'stock' => 8, 'min_stock' => 10,
        ]);
    }

    private function seedInverters(): void
    {
        $this->make([
            'sku' => 'INV-HYB-5K', 'name' => 'Inverter Hybrid 5kW Single Phase',
            'category' => 'inverter-hybrid', 'brand' => 'bezvolt',
            'price' => 12500000, 'sale_price' => 11750000, 'weight_grams' => 14000,
            'length_cm' => 60, 'width_cm' => 40, 'height_cm' => 20, 'requires_freight' => true,
            'is_featured' => true, 'is_promo' => true, 'warranty' => '5 tahun',
            'short' => 'Inverter hybrid 5kW dengan MPPT ganda dan dukungan baterai lithium.', 'stock' => 35,
            'attributes' => ['Spesifikasi Inverter' => [
                'Daya Output' => 5000, 'Tegangan Input PV' => '120-500V', 'Jumlah MPPT' => 2,
                'Tegangan Baterai' => 48, 'Fasa' => 'Single Phase', 'Efisiensi' => 97.6, 'IP Rating' => 'IP65',
            ]],
        ]);

        $this->make([
            'sku' => 'INV-HYB-6K', 'name' => 'Inverter Hybrid 6kW',
            'category' => 'inverter-hybrid', 'brand' => 'voltamax',
            'price' => 15900000, 'weight_grams' => 16000, 'length_cm' => 62, 'width_cm' => 42, 'height_cm' => 22,
            'requires_freight' => true, 'is_featured' => true, 'warranty' => '5 tahun',
            'short' => 'Inverter hybrid 6kW untuk rumah besar dan usaha kecil.', 'stock' => 18,
            'attributes' => ['Spesifikasi Inverter' => ['Daya Output' => 6000, 'Fasa' => 'Single Phase', 'Jumlah MPPT' => 2, 'Efisiensi' => 97.8]],
        ]);

        $this->make([
            'sku' => 'INV-ONG-3K', 'name' => 'Inverter On-Grid 3kW',
            'category' => 'inverter-on-grid', 'brand' => 'voltamax',
            'price' => 6900000, 'weight_grams' => 9000, 'length_cm' => 50, 'width_cm' => 35, 'height_cm' => 18,
            'warranty' => '5 tahun', 'short' => 'Inverter on-grid 3kW efisien untuk ekspor-impor PLN.', 'stock' => 50,
            'attributes' => ['Spesifikasi Inverter' => ['Daya Output' => 3000, 'Fasa' => 'Single Phase', 'Efisiensi' => 97.2]],
        ]);

        $this->make([
            'sku' => 'INV-OFF-2K', 'name' => 'Inverter Off-Grid 2kW 24V',
            'category' => 'inverter-off-grid', 'brand' => 'bezvolt',
            'price' => 4200000, 'weight_grams' => 8000, 'length_cm' => 45, 'width_cm' => 30, 'height_cm' => 16,
            'warranty' => '2 tahun', 'short' => 'Inverter off-grid pure sine wave 2kW untuk sistem mandiri.', 'stock' => 0,
            'attributes' => ['Spesifikasi Inverter' => ['Daya Output' => 2000, 'Fasa' => 'Single Phase', 'Tegangan Baterai' => 24]],
        ]);

        $this->make([
            'sku' => 'INV-3PH-10K', 'name' => 'Inverter Hybrid Three Phase 10kW',
            'category' => 'inverter-three-phase', 'brand' => 'voltamax',
            'price' => 28500000, 'weight_grams' => 26000, 'length_cm' => 70, 'width_cm' => 50, 'height_cm' => 25,
            'requires_freight' => true, 'warranty' => '5 tahun', 'short' => 'Inverter hybrid 3 fasa 10kW untuk industri & kantor.', 'stock' => 6,
            'attributes' => ['Spesifikasi Inverter' => ['Daya Output' => 10000, 'Fasa' => 'Three Phase', 'Jumlah MPPT' => 2]],
        ]);
    }

    private function seedBatteries(): void
    {
        $batt = $this->make([
            'sku' => 'BAT-LFP-5K', 'name' => 'Baterai Lithium LiFePO4 5.12kWh 48V',
            'category' => 'baterai-lithium-lifepo4', 'brand' => 'lumencell',
            'price' => 18500000, 'sale_price' => 17900000, 'weight_grams' => 48000,
            'length_cm' => 44, 'width_cm' => 42, 'height_cm' => 22, 'requires_freight' => true,
            'is_featured' => true, 'is_promo' => true, 'warranty' => '8 tahun',
            'short' => 'Baterai LiFePO4 5.12kWh dengan BMS pintar dan komunikasi CAN/RS485.', 'stock' => 40,
            'attributes' => ['Spesifikasi Baterai' => [
                'Kapasitas' => 5.12, 'Tegangan Nominal' => 48, 'Kapasitas Ah' => 100,
                'Jenis Sel' => 'LiFePO4', 'Cycle Life' => 6000, 'Depth of Discharge' => 95, 'BMS' => 'Smart BMS 100A',
            ]],
        ]);
        $this->addVariants($batt, 'Kapasitas', [
            ['5.12kWh', 17900000, 25, 48000], ['10.24kWh', 33900000, 15, 92000],
        ]);

        $this->make([
            'sku' => 'BAT-RACK-15K', 'name' => 'Baterai Rack Mounted 15kWh',
            'category' => 'baterai-rack-mounted', 'brand' => 'bezvolt',
            'price' => 49000000, 'weight_grams' => 140000, 'length_cm' => 48, 'width_cm' => 55, 'height_cm' => 120,
            'requires_freight' => true, 'pickup_only' => false, 'warranty' => '10 tahun',
            'short' => 'Sistem baterai rack 15kWh untuk backup skala komersial.', 'stock' => 5,
            'attributes' => ['Spesifikasi Baterai' => ['Kapasitas' => 15, 'Tegangan Nominal' => 48, 'Jenis Sel' => 'LiFePO4']],
        ]);

        $this->make([
            'sku' => 'BAT-WALL-5K', 'name' => 'Baterai Wall Mounted 5kWh',
            'category' => 'baterai-wall-mounted', 'brand' => 'lumencell',
            'price' => 19500000, 'weight_grams' => 46000, 'length_cm' => 60, 'width_cm' => 40, 'height_cm' => 18,
            'requires_freight' => true, 'is_new' => true, 'warranty' => '8 tahun',
            'short' => 'Baterai wall-mounted desain elegan untuk rumah modern.', 'stock' => 12,
        ]);
    }

    private function seedChargeControllers(): void
    {
        $this->make([
            'sku' => 'SCC-MPPT-60', 'name' => 'Solar Charge Controller MPPT 60A',
            'category' => 'solar-charge-controller-mppt', 'brand' => 'gridtech',
            'price' => 2450000, 'weight_grams' => 2500, 'length_cm' => 25, 'width_cm' => 18, 'height_cm' => 10,
            'warranty' => '2 tahun', 'short' => 'SCC MPPT 60A efisiensi 99% dengan layar LCD.', 'stock' => 45,
        ]);
        $this->make([
            'sku' => 'SCC-PWM-30', 'name' => 'Solar Charge Controller PWM 30A',
            'category' => 'solar-charge-controller-pwm', 'brand' => 'gridtech',
            'price' => 450000, 'weight_grams' => 800, 'length_cm' => 18, 'width_cm' => 12, 'height_cm' => 6,
            'short' => 'SCC PWM 30A ekonomis untuk sistem kecil.', 'stock' => 90,
        ]);
    }

    private function seedAccessories(): void
    {
        $this->make(['sku' => 'ACC-KBL-6', 'name' => 'Kabel Solar PV 6mm² (per meter)', 'category' => 'aksesoris-kabel', 'brand' => 'kabelsurya', 'price' => 18000, 'weight_grams' => 80, 'length_cm' => 15, 'width_cm' => 15, 'height_cm' => 2, 'unit' => 'meter', 'short' => 'Kabel PV 6mm² tahan UV bersertifikat TUV.', 'stock' => 5000, 'min_purchase' => 10]);
        $this->make(['sku' => 'ACC-MC4', 'name' => 'Konektor MC4 (sepasang)', 'category' => 'aksesoris-konektor', 'brand' => 'kabelsurya', 'price' => 25000, 'weight_grams' => 60, 'length_cm' => 6, 'width_cm' => 4, 'height_cm' => 3, 'short' => 'Konektor MC4 IP67 original.', 'stock' => 800]);
        $this->make(['sku' => 'ACC-MNT-RAIL', 'name' => 'Rail Mounting Aluminium 4.2m', 'category' => 'aksesoris-mounting', 'brand' => 'solarprime', 'price' => 320000, 'weight_grams' => 4200, 'length_cm' => 420, 'width_cm' => 4, 'height_cm' => 4, 'requires_freight' => true, 'short' => 'Rail aluminium anodized untuk struktur panel.', 'stock' => 200]);
        $this->make(['sku' => 'ACC-CMB-4', 'name' => 'Combiner Box 4 String DC', 'category' => 'aksesoris-combiner-box', 'brand' => 'gridtech', 'price' => 1250000, 'weight_grams' => 3500, 'short' => 'Combiner box 4 string dengan proteksi SPD & fuse.', 'stock' => 30]);
    }

    private function seedPackages(): void
    {
        $components = Product::whereIn('sku', ['PNL-MONO-550', 'INV-HYB-5K', 'BAT-LFP-5K', 'SCC-MPPT-60', 'ACC-MNT-RAIL'])->get()->keyBy('sku');

        $packages = [
            ['PKG-ONGRID-3K', 'Paket PLTS On-Grid 3kW Rumah', 'paket-plts-on-grid', 32000000, 29900000, 15],
            ['PKG-OFFGRID-2K', 'Paket PLTS Off-Grid 2kW', 'paket-plts-off-grid', 45000000, null, 8],
            ['PKG-HYBRID-5K', 'Paket PLTS Hybrid 5kW + Baterai', 'paket-plts-hybrid', 68000000, 64900000, 10],
            ['PKG-BACKUP-3K', 'Paket Backup Listrik Rumah 3kW', 'paket-plts-rumah', 38000000, null, 12],
            ['PKG-INDUSTRI-10K', 'Paket PLTS Industri 10kW Three Phase', 'paket-plts-industri', 145000000, null, 4],
        ];

        foreach ($packages as [$sku, $name, $categorySlug, $price, $sale, $stock]) {
            $pkg = $this->make([
                'sku' => $sku, 'name' => $name, 'category' => $categorySlug, 'brand' => 'bezvolt',
                'price' => $price, 'sale_price' => $sale, 'product_type' => 'bundle',
                'weight_grams' => 150000, 'requires_freight' => true, 'is_featured' => true,
                'warranty' => 'Sesuai komponen', 'short' => "Solusi lengkap $name termasuk instalasi & survei.",
                'stock' => $stock,
                'description' => '<p>Paket lengkap siap pasang. Hasil produksi energi bergantung pada lokasi, cuaca, orientasi, dan kondisi instalasi.</p>',
            ]);

            // Bundle components only for a newly created package (don't reset existing composition).
            $map = ! $pkg->wasRecentlyCreated ? [] : [
                'Panel Surya' => ['PNL-MONO-550', 6, false],
                'Inverter' => ['INV-HYB-5K', 1, true],
                'Baterai' => ['BAT-LFP-5K', 1, true],
                'Charge Controller' => ['SCC-MPPT-60', 1, false],
                'Mounting' => ['ACC-MNT-RAIL', 4, false],
            ];
            $order = 0;
            foreach ($map as $label => [$componentSku, $qty, $replaceable]) {
                if ($component = $components->get($componentSku)) {
                    $pkg->bundleItems()->updateOrCreate(
                        ['component_product_id' => $component->id],
                        ['quantity' => $qty, 'is_replaceable' => $replaceable, 'component_label' => $label, 'sort_order' => $order++],
                    );
                }
            }
        }
    }

    private function seedSurplus(): void
    {
        // Surplus is now expressed via product condition (not a category), so each
        // item lives in its real product category and carries the right condition.
        $items = [
            ['SRP-PNL-01', 'Panel Surya 450Wp (Sisa Proyek)', 'panel-surya-monocrystalline', 'new_project_surplus', 950000, 30, false, 'Kelebihan stok proyek PLTS 100kWp.'],
            ['SRP-INV-01', 'Inverter Hybrid 5kW (Open Box)', 'inverter-hybrid', 'open_box', 9900000, 3, true, 'Kardus pernah dibuka, unit tidak pernah dipakai.'],
            ['SRP-INV-02', 'Inverter On-Grid 3kW (Bekas Display)', 'inverter-on-grid', 'display_unit', 4900000, 2, true, 'Bekas pajangan showroom, fungsi normal.'],
            ['SRP-BAT-01', 'Baterai LiFePO4 5kWh (Bekas Pakai)', 'baterai-lithium-lifepo4', 'used', 12000000, 4, true, 'Bekas pakai 1 tahun, SOH 92%.'],
            ['SRP-SCC-01', 'MPPT 60A (Open Box)', 'solar-charge-controller-mppt', 'open_box', 1950000, 6, false, 'Open box, garansi toko 6 bulan.'],
            ['SRP-PNL-02', 'Panel Bifacial 560Wp (Sisa Proyek)', 'panel-surya-bifacial', 'new_project_surplus', 1350000, 20, false, 'Sisa proyek komersial, kondisi baru.'],
            ['SRP-MNT-01', 'Rail Mounting (Bekas Pakai)', 'mounting-rangka-atap-rooftop', 'used', 180000, 50, false, 'Bekas bongkaran, masih kokoh.'],
            ['SRP-CMB-01', 'Combiner Box (Bekas Display)', 'kabel-konektor-proteksi-combiner-box', 'display_unit', 850000, 3, false, 'Bekas display pameran.'],
        ];

        foreach ($items as [$sku, $name, $categorySlug, $condition, $price, $stock, $negotiable, $reason]) {
            $product = $this->make([
                'sku' => $sku, 'name' => $name, 'category' => $categorySlug, 'brand' => 'bezvolt',
                'price' => $price, 'condition' => $condition, 'is_clearance' => true,
                'weight_grams' => 20000, 'requires_freight' => true, 'short' => $reason, 'stock' => $stock,
            ]);

            if ($product->wasRecentlyCreated) {
                $product->conditionDetail()->updateOrCreate([], [
                    'reason_for_sale' => $reason,
                    'item_location' => 'Gudang Jakarta',
                    'available_quantity' => $stock,
                    'purchase_year' => now()->year - 1,
                    'remaining_warranty' => str_starts_with($condition, 'new') ? '12 tahun' : '3-6 bulan garansi toko',
                    'completeness' => 'Unit + manual',
                    'defect_notes' => $condition === 'used' ? 'Terdapat goresan pemakaian wajar.' : 'Tidak ada cacat fungsi.',
                    'is_returnable' => str_starts_with($condition, 'new'),
                    'is_negotiable' => $negotiable,
                    'pickup_required' => false,
                    'auto_shipping' => true,
                ]);
            }
        }
    }

    private function seedQuotationProducts(): void
    {
        $this->make([
            'sku' => 'PJU-ATS-60', 'name' => 'PJU Tenaga Surya All-in-One 60W',
            'category' => 'pju-tenaga-surya', 'brand' => 'enerflow',
            'price' => 3500000, 'price_status' => 'call_for_price', 'requires_quotation' => true, 'is_purchasable' => false,
            'weight_grams' => 15000, 'requires_freight' => true,
            'short' => 'Lampu PJU tenaga surya 60W untuk pengadaan skala proyek. Minta penawaran untuk harga kuantitas.', 'stock' => 0,
        ]);

        $this->make([
            'sku' => 'PMP-SURYA-2HP', 'name' => 'Pompa Air Tenaga Surya 2HP',
            'category' => 'pompa-air-tenaga-surya', 'brand' => 'enerflow',
            'price' => 18000000, 'requires_quotation' => true, 'is_purchasable' => false,
            'weight_grams' => 35000, 'requires_freight' => true,
            'short' => 'Sistem pompa surya 2HP untuk irigasi & perkebunan. Butuh survei kebutuhan debit.', 'stock' => 0,
        ]);
    }

    /* ------------------------------------------------------------------ */

    private function make(array $data): Product
    {
        $slug = Str::slug($data['name']);
        // firstOrCreate (not updateOrCreate): create the product only if its SKU is
        // missing. Re-running this seeder on a deploy must NEVER overwrite a product
        // that admin has since edited (price, description, flags, etc.).
        $product = Product::firstOrCreate(['sku' => $data['sku']], [
            'name' => $data['name'],
            'slug' => $slug,
            'category_id' => $this->categories[$data['category']] ?? null,
            'brand_id' => $this->brands[$data['brand']] ?? null,
            'product_type' => $data['product_type'] ?? 'simple',
            'condition' => $data['condition'] ?? 'new',
            'short_description' => $data['short'] ?? null,
            'description' => $data['description'] ?? '<p>'.($data['short'] ?? $data['name']).'</p>',
            'price' => $data['price'],
            'sale_price' => $data['sale_price'] ?? null,
            'cost_price' => round($data['price'] * 0.8),
            'price_status' => $data['price_status'] ?? 'fixed',
            'price_includes_tax' => false,
            'is_taxable' => true,
            'min_stock' => $data['min_stock'] ?? 5,
            'unit' => $data['unit'] ?? 'unit',
            'weight_grams' => $data['weight_grams'] ?? 1000,
            'length_cm' => $data['length_cm'] ?? 20,
            'width_cm' => $data['width_cm'] ?? 20,
            'height_cm' => $data['height_cm'] ?? 20,
            'requires_freight' => $data['requires_freight'] ?? false,
            'pickup_only' => $data['pickup_only'] ?? false,
            'warranty' => $data['warranty'] ?? null,
            'estimated_processing' => '1-3 hari kerja',
            'status' => 'published',
            'is_featured' => $data['is_featured'] ?? false,
            'is_new' => $data['is_new'] ?? false,
            'is_promo' => isset($data['sale_price']),
            'is_clearance' => $data['is_clearance'] ?? false,
            'is_purchasable' => $data['is_purchasable'] ?? true,
            'requires_quotation' => $data['requires_quotation'] ?? false,
            'min_purchase' => $data['min_purchase'] ?? 1,
            'meta_title' => $data['name'].' — Rekasurya Store',
            'meta_description' => Str::limit($data['short'] ?? $data['name'], 150),
            'published_at' => now()->subDays(rand(1, 40)),
        ]);

        // Initial stock through the ledger so warehouse_stocks + movements stay consistent.
        // Only for a NEW product — re-runs must not touch admin's adjusted stock.
        if ($product->wasRecentlyCreated) {
            $target = (int) ($data['stock'] ?? 0);
            if ($target > 0) {
                $this->stock->adjust($product, null, $target, StockMovementType::Purchase, note: 'Stok awal seeder');
            }
        }

        if (! empty($data['attributes'])) {
            $this->attachAttributes($product, $data['attributes']);
        }

        return $product;
    }

    private function attachAttributes(Product $product, array $groups): void
    {
        foreach ($groups as $groupName => $values) {
            foreach ($values as $attrName => $value) {
                $slug = Str::slug($groupName.'-'.$attrName);
                $attributeId = $this->attributes[$slug] ?? null;
                if (! $attributeId) {
                    continue;
                }
                $product->attributeValues()->updateOrCreate(
                    ['attribute_id' => $attributeId],
                    is_numeric($value)
                        ? ['value_number' => $value, 'value_text' => null]
                        : ['value_text' => $value, 'value_number' => null],
                );
            }
        }
    }

    private function addVariants(Product $product, string $optionName, array $variants): void
    {
        $product->update(['product_type' => 'variable']);
        foreach ($variants as $i => [$label, $price, $stock, $weight]) {
            $product->variants()->updateOrCreate(
                ['sku' => $product->sku.'-'.Str::slug($label)],
                [
                    'name' => $label,
                    'option_values' => [$optionName => $label],
                    'price' => $price,
                    'stock' => $stock,
                    'weight_grams' => $weight,
                    'is_active' => true,
                    'sort_order' => $i,
                ],
            );
        }
    }
}
