@php
    $productTypes = ['simple' => 'Sederhana', 'variable' => 'Varian', 'bundle' => 'Paket (Bundle)', 'service' => 'Jasa/Layanan'];
    $conditions = ['new' => 'Baru', 'open_box' => 'Open Box', 'display_unit' => 'Bekas Display', 'used' => 'Bekas Pakai'];
    $statuses = ['draft' => 'Draft', 'published' => 'Terbit', 'archived' => 'Arsip'];

    // Friendly price model: "Harga Jual" (what the customer pays) + optional "Harga Coret".
    $sellPrice = $product->isOnSale() ? $product->sale_price : $product->price;
    $comparePrice = $product->isOnSale() ? $product->price : null;

    // Parse existing specifications HTML table back into editable rows.
    $specRows = [];
    if ($product->specifications) {
        preg_match_all('/<tr>\s*<th>(.*?)<\/th>\s*<td>(.*?)<\/td>\s*<\/tr>/s', $product->specifications, $m, PREG_SET_ORDER);
        foreach ($m as $row) {
            $specRows[] = ['key' => html_entity_decode(strip_tags($row[1])), 'value' => html_entity_decode(strip_tags($row[2]))];
        }
    }
    if (empty($specRows)) {
        $specRows = [['key' => '', 'value' => '']];
    }
@endphp

<div class="space-y-6">
    {{-- ===== Essentials ===== --}}
    <div class="card space-y-4 p-5">
        <h2 class="font-semibold text-gray-900">Informasi Produk</h2>
        <x-form.input name="name" label="Nama Produk" :value="$product->name" required />

        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.select name="category_id" label="Kategori" :options="$categories" :selected="$product->category_id" placeholder="— Pilih kategori —" />
            <div>
                <x-form.select name="brand_id" label="Brand" :options="$brands" :selected="$product->brand_id" placeholder="— Pilih brand —" />
                <x-form.input name="new_brand" label="atau Brand Baru" :value="old('new_brand')" placeholder="Ketik nama brand baru" class="mt-2" hint="Kalau brand belum ada, ketik di sini — otomatis dibuat." />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.select name="product_type" label="Tipe Produk" :options="$productTypes" :selected="$product->product_type ?? 'simple'" required />
            <x-form.select name="status" label="Status" :options="$statuses" :selected="$product->status ?? 'draft'" required />
        </div>

        <x-form.textarea name="short_description" label="Deskripsi Singkat" :value="$product->short_description" rows="2" hint="Ringkasan 1-2 kalimat. Maksimal 500 karakter." />
        <x-form.richtext name="description" label="Deskripsi Lengkap" :value="$product->description" hint="Gunakan tombol format (tebal, daftar, dll.) — tidak perlu menulis kode HTML." />
    </div>

    {{-- Harga & stok --}}
    <div class="card space-y-4 p-5">
        <h2 class="font-semibold text-gray-900">Harga &amp; Stok</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.input type="number" step="0.01" min="0" name="price" label="Harga Jual (Rp)" :value="old('price', $sellPrice)" required hint="Harga yang dibayar pembeli." />
            <x-form.input type="number" step="0.01" min="0" name="compare_price" label="Harga Coret (opsional)" :value="old('compare_price', $comparePrice)" hint="Harga sebelum diskon (dicoret). Kosongkan bila tidak ada diskon." />
        </div>
        @unless ($product->exists)
            <x-form.input type="number" min="0" name="initial_stock" label="Stok Awal" value="0" hint="Jumlah stok saat ini. Bisa diubah lewat menu Stok." />
        @endunless
    </div>

    {{-- Spesifikasi teknis (baris atribut/nilai → tabel) --}}
    <div class="card space-y-3 p-5" x-data="{ rows: {{ Illuminate\Support\Js::from($specRows) }} }">
        <div>
            <h2 class="font-semibold text-gray-900">Spesifikasi Teknis</h2>
            <p class="text-xs text-gray-400">Isi atribut &amp; nilai. Tampil sebagai tabel di tab "Spesifikasi" halaman produk.</p>
        </div>
        <div class="space-y-2">
            <template x-for="(row, i) in rows" :key="i">
                <div class="flex items-center gap-2">
                    <input type="text" :name="`spec_key[${i}]`" x-model="row.key" placeholder="Atribut (cth: Daya Output)" class="form-input flex-1">
                    <input type="text" :name="`spec_value[${i}]`" x-model="row.value" placeholder="Nilai (cth: 6000 W)" class="form-input flex-1">
                    <button type="button" @click="rows.splice(i, 1)" class="shrink-0 rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600" aria-label="Hapus baris">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                    </button>
                </div>
            </template>
        </div>
        <button type="button" @click="rows.push({ key: '', value: '' })" class="btn-outline text-sm">+ Tambah baris</button>
    </div>

    {{-- ===== Advanced (collapsed) ===== --}}
    <div class="card p-5" x-data="{ open: false }">
        <button type="button" @click="open = !open" class="flex w-full items-center justify-between text-left">
            <div>
                <h2 class="font-semibold text-gray-900">Pengaturan Lanjutan</h2>
                <p class="text-xs text-gray-400">SKU, kondisi, dimensi & berat, pajak, badge, SEO — opsional.</p>
            </div>
            <svg class="h-5 w-5 shrink-0 text-gray-400 transition" :class="open && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
        </button>

        <div x-show="open" x-cloak class="mt-5 space-y-6 border-t border-gray-100 pt-5">
            {{-- Identitas --}}
            <div class="space-y-4">
                <h3 class="text-sm font-semibold text-gray-700">Identitas</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.input name="sku" label="SKU" :value="$product->sku" hint="Kosongkan untuk dibuat otomatis." />
                    <x-form.input name="model" label="Model" :value="$product->model" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.input name="slug" label="Slug (URL)" :value="$product->slug" hint="Kosongkan untuk dibuat otomatis dari nama." />
                    <x-form.select name="condition" label="Kondisi" :options="$conditions" :selected="$product->condition ?? 'new'" />
                </div>
            </div>

            {{-- Harga lanjutan --}}
            <div class="space-y-4">
                <h3 class="text-sm font-semibold text-gray-700">Harga Lanjutan</h3>
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-form.input type="number" step="0.01" min="0" name="cost_price" label="Harga Modal (Rp)" :value="$product->cost_price" hint="Hanya untuk internal." />
                    <x-form.input type="number" step="0.01" min="0" max="100" name="affiliate_rate" label="Komisi Afiliasi (%)" :value="$product->affiliate_rate" hint="Kosongkan untuk pakai default." />
                    <x-form.input name="unit" label="Satuan" :value="$product->unit" placeholder="pcs" />
                </div>
            </div>

            {{-- Stok, berat & pengiriman --}}
            <div class="space-y-4">
                <h3 class="text-sm font-semibold text-gray-700">Berat &amp; Pengiriman</h3>
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-form.input type="number" min="0" name="min_stock" label="Stok Minimum" :value="$product->min_stock" hint="Batas peringatan stok menipis." />
                    <x-form.input type="number" min="0" name="weight_grams" label="Berat (gram)" :value="$product->weight_grams" />
                    <x-form.input type="number" min="1" name="package_count" label="Jumlah Koli" :value="$product->package_count" />
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-form.input type="number" step="0.01" min="0" name="length_cm" label="Panjang (cm)" :value="$product->length_cm" />
                    <x-form.input type="number" step="0.01" min="0" name="width_cm" label="Lebar (cm)" :value="$product->width_cm" />
                    <x-form.input type="number" step="0.01" min="0" name="height_cm" label="Tinggi (cm)" :value="$product->height_cm" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.input name="warranty" label="Garansi" :value="$product->warranty" placeholder="mis. 12 bulan" />
                    <x-form.input name="estimated_processing" label="Estimasi Proses" :value="$product->estimated_processing" placeholder="mis. 1-3 hari kerja" />
                </div>
            </div>

            {{-- Badge / Label --}}
            <div class="space-y-4">
                <h3 class="text-sm font-semibold text-gray-700">Badge / Label</h3>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <x-form.checkbox name="is_featured" label="Produk unggulan" :checked="(bool) $product->is_featured" />
                    <x-form.checkbox name="is_new" label="Produk baru" :checked="(bool) $product->is_new" />
                    <x-form.checkbox name="is_promo" label="Promo" :checked="(bool) $product->is_promo" />
                    <x-form.checkbox name="is_clearance" label="Clearance" :checked="(bool) $product->is_clearance" />
                </div>
            </div>

            {{-- SEO --}}
            <div class="space-y-4">
                <h3 class="text-sm font-semibold text-gray-700">Publikasi &amp; SEO</h3>
                <x-form.input type="datetime-local" name="published_at" label="Tanggal Publikasi"
                              :value="$product->published_at?->format('Y-m-d\TH:i')"
                              hint="Otomatis terisi saat status Terbit bila dikosongkan." />
                <x-form.input name="meta_title" label="Meta Title" :value="$product->meta_title" />
                <x-form.textarea name="meta_description" label="Meta Description" :value="$product->meta_description" rows="3" />
                <x-form.input name="keywords" label="Keywords" :value="$product->keywords" hint="Pisahkan dengan koma." />
            </div>
        </div>
    </div>

    {{-- Sticky action bar — always reachable while scrolling the long form. --}}
    <div class="sticky bottom-0 z-20 -mx-4 flex items-center gap-2 border-t border-gray-200 bg-white/95 px-4 py-3 shadow-[0_-4px_12px_rgba(0,0,0,0.04)] sm:-mx-6 sm:px-6">
        <button type="submit" class="btn-primary">Simpan Produk</button>
        <a href="{{ route('admin.products.index') }}" class="btn-outline">Kembali ke Daftar</a>
    </div>
</div>
