@php
    $productTypes = ['simple' => 'Sederhana', 'variable' => 'Varian', 'bundle' => 'Paket (Bundle)', 'service' => 'Jasa/Layanan'];
    $conditions = ['new' => 'Baru', 'open_box' => 'Open Box', 'display_unit' => 'Bekas Display', 'used' => 'Bekas Pakai'];
    $statuses = ['draft' => 'Draft', 'published' => 'Terbit', 'archived' => 'Arsip'];
@endphp

<div class="space-y-6">
    {{-- Informasi dasar --}}
    <div class="card space-y-4 p-5">
        <h2 class="font-semibold text-gray-900">Informasi Dasar</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.input name="sku" label="SKU" :value="$product->sku" required />
            <x-form.input name="name" label="Nama Produk" :value="$product->name" required />
        </div>
        <x-form.input name="slug" label="Slug" :value="$product->slug" hint="Kosongkan untuk membuat otomatis dari nama." />
        <div class="grid gap-4 sm:grid-cols-3">
            <x-form.select name="category_id" label="Kategori" :options="$categories" :selected="$product->category_id" placeholder="— Pilih kategori —" />
            <x-form.select name="brand_id" label="Brand" :options="$brands" :selected="$product->brand_id" placeholder="— Pilih brand —" />
            <x-form.input name="model" label="Model" :value="$product->model" />
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <x-form.select name="product_type" label="Tipe Produk" :options="$productTypes" :selected="$product->product_type" required />
            <x-form.select name="condition" label="Kondisi" :options="$conditions" :selected="$product->condition" required />
            <x-form.select name="status" label="Status" :options="$statuses" :selected="$product->status" required />
        </div>
        <x-form.textarea name="short_description" label="Deskripsi Singkat" :value="$product->short_description" rows="2" hint="Maksimal 500 karakter." />
        <x-form.textarea name="description" label="Deskripsi Lengkap" :value="$product->description" rows="6" />
    </div>

    {{-- Harga --}}
    <div class="card space-y-4 p-5">
        <h2 class="font-semibold text-gray-900">Harga</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <x-form.input type="number" step="0.01" min="0" name="price" label="Harga Normal (Rp)" :value="$product->price" required />
            <x-form.input type="number" step="0.01" min="0" name="sale_price" label="Harga Promo (Rp)" :value="$product->sale_price" hint="Opsional." />
            <x-form.input type="number" step="0.01" min="0" name="cost_price" label="Harga Modal (Rp)" :value="$product->cost_price" hint="Hanya untuk internal." />
            <x-form.input type="number" step="0.01" min="0" max="100" name="affiliate_rate" label="Komisi Afiliasi (%)" :value="$product->affiliate_rate" hint="Kosongkan untuk pakai default global." />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.input name="unit" label="Satuan" :value="$product->unit" placeholder="pcs" />
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <x-form.checkbox name="is_taxable" label="Kena pajak (PPN)" :checked="(bool) $product->is_taxable" />
            <x-form.checkbox name="price_includes_tax" label="Harga sudah termasuk pajak" :checked="(bool) $product->price_includes_tax" />
        </div>
    </div>

    {{-- Stok & pengiriman --}}
    <div class="card space-y-4 p-5">
        <h2 class="font-semibold text-gray-900">Stok &amp; Pengiriman</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            @unless ($product->exists)
                <x-form.input type="number" min="0" name="initial_stock" label="Stok Awal" value="0" hint="Dicatat sebagai pembelian." />
            @endunless
            <x-form.input type="number" min="0" name="min_stock" label="Stok Minimum" :value="$product->min_stock" hint="Batas peringatan stok menipis." />
            <x-form.input type="number" min="0" name="weight_grams" label="Berat (gram)" :value="$product->weight_grams" />
        </div>
        <div class="grid gap-4 sm:grid-cols-4">
            <x-form.input type="number" step="0.01" min="0" name="length_cm" label="Panjang (cm)" :value="$product->length_cm" />
            <x-form.input type="number" step="0.01" min="0" name="width_cm" label="Lebar (cm)" :value="$product->width_cm" />
            <x-form.input type="number" step="0.01" min="0" name="height_cm" label="Tinggi (cm)" :value="$product->height_cm" />
            <x-form.input type="number" min="1" name="package_count" label="Jumlah Koli" :value="$product->package_count" />
        </div>
        <div class="grid gap-3 sm:grid-cols-3">
            <x-form.checkbox name="can_combine_package" label="Boleh digabung 1 paket" :checked="(bool) $product->can_combine_package" />
            <x-form.checkbox name="requires_freight" label="Wajib kargo" :checked="(bool) $product->requires_freight" />
            <x-form.checkbox name="pickup_only" label="Ambil di lokasi saja" :checked="(bool) $product->pickup_only" />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.input name="warranty" label="Garansi" :value="$product->warranty" placeholder="mis. 12 bulan" />
            <x-form.input name="estimated_processing" label="Estimasi Proses" :value="$product->estimated_processing" placeholder="mis. 1-3 hari kerja" />
        </div>
    </div>

    {{-- Pembelian & merchandising --}}
    <div class="card space-y-4 p-5">
        <h2 class="font-semibold text-gray-900">Pembelian &amp; Merchandising</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.input type="number" min="1" name="min_purchase" label="Minimal Pembelian" :value="$product->min_purchase" />
            <x-form.input type="number" min="1" name="max_purchase" label="Maksimal Pembelian" :value="$product->max_purchase" hint="Kosongkan untuk tanpa batas." />
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <x-form.checkbox name="is_purchasable" label="Dapat dibeli langsung" :checked="(bool) $product->is_purchasable" />
            <x-form.checkbox name="requires_quotation" label="Perlu penawaran (RFQ)" :checked="(bool) $product->requires_quotation" />
            <x-form.checkbox name="is_featured" label="Produk unggulan" :checked="(bool) $product->is_featured" />
            <x-form.checkbox name="is_new" label="Produk baru" :checked="(bool) $product->is_new" />
            <x-form.checkbox name="is_promo" label="Promo" :checked="(bool) $product->is_promo" />
            <x-form.checkbox name="is_clearance" label="Clearance" :checked="(bool) $product->is_clearance" />
        </div>
    </div>

    {{-- Publikasi & SEO --}}
    <div class="card space-y-4 p-5">
        <h2 class="font-semibold text-gray-900">Publikasi &amp; SEO</h2>
        <x-form.input type="datetime-local" name="published_at" label="Tanggal Publikasi"
                      :value="$product->published_at?->format('Y-m-d\TH:i')"
                      hint="Otomatis terisi saat status Terbit bila dikosongkan." />
        <x-form.input name="meta_title" label="Meta Title" :value="$product->meta_title" />
        <x-form.textarea name="meta_description" label="Meta Description" :value="$product->meta_description" rows="3" />
        <x-form.input name="keywords" label="Keywords" :value="$product->keywords" hint="Pisahkan dengan koma." />
    </div>

    <div class="flex items-center gap-2">
        <button type="submit" class="btn-primary">Simpan Produk</button>
        <a href="{{ route('admin.products.index') }}" class="btn-outline">Batal</a>
    </div>
</div>
