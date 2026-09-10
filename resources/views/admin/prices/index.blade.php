@extends('layouts.admin')

@section('title', 'Edit Cepat Produk')

@section('content')
<div x-data="quickEditPage()" x-init="init()">
    <x-admin.page-header title="Edit Cepat Produk"
        subtitle="Harga · margin · stok · berat & dimensi — sunting langsung di sel, tersimpan otomatis saat pindah sel atau tekan Enter">
        <x-slot:actions>
            {{-- Pilih kelompok kolom supaya tabel tidak terlalu lebar; pilihan diingat di browser. --}}
            <div class="inline-flex rounded-lg border border-gray-200 bg-white p-0.5 text-xs" role="group" aria-label="Kolom yang ditampilkan">
                <template x-for="opt in colOptions" :key="opt.key">
                    <button type="button" @click="setCols(opt.key)"
                            :class="cols === opt.key ? 'bg-brand-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'"
                            class="rounded-md px-3 py-1.5 font-medium transition" x-text="opt.label"></button>
                </template>
            </div>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="GET" class="mb-3 flex flex-wrap items-end gap-3">
        <div class="min-w-[220px] flex-1">
            <label class="input-label" for="q">Cari produk</label>
            <input type="search" name="q" id="q" value="{{ $q }}" placeholder="Ketik nama atau SKU, lalu Enter" class="form-input" autofocus>
        </div>
        <div>
            <label class="input-label" for="brand">Brand</label>
            <select name="brand" id="brand" class="form-select" onchange="this.form.submit()">
                <option value="">Semua brand</option>
                @foreach ($brandOptions as $bid => $blabel)
                    <option value="{{ $bid }}" @selected((string) $brandId === (string) $bid)>{{ $blabel }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="input-label" for="tampil">Tampilkan</label>
            <select name="tampil" id="tampil" class="form-select" onchange="this.form.submit()">
                <option value="">Semua produk</option>
                <optgroup label="Harga">
                    <option value="margin-tipis" @selected($only === 'margin-tipis')>Margin di bawah 20%</option>
                    <option value="tanpa-modal" @selected($only === 'tanpa-modal')>Belum ada harga modal</option>
                </optgroup>
                <optgroup label="Stok">
                    <option value="stok-habis" @selected($only === 'stok-habis')>Stok habis</option>
                    <option value="stok-menipis" @selected($only === 'stok-menipis')>Stok menipis</option>
                </optgroup>
                <optgroup label="Pengiriman">
                    <option value="tanpa-berat" @selected($only === 'tanpa-berat')>Berat / dimensi belum lengkap</option>
                </optgroup>
            </select>
        </div>
        <button type="submit" class="btn-primary">Cari</button>
        @if ($q !== '' || $brandId || $only)
            <a href="{{ route('admin.prices.index') }}" class="btn-outline">Reset</a>
        @endif
    </form>

    <details class="mb-3 rounded-lg border border-gray-200 bg-white px-4 py-2 text-xs text-gray-600">
        <summary class="cursor-pointer select-none font-medium text-gray-700">Cara pakai &amp; keterangan warna</summary>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            <li>Klik sel, ketik angka, lalu <kbd class="rounded border border-gray-300 bg-gray-50 px-1">Enter</kbd> — tersimpan dan kursor lompat ke baris berikutnya di kolom yang sama. <kbd class="rounded border border-gray-300 bg-gray-50 px-1">Tab</kbd> pindah ke sel sebelahnya.</li>
            <li>Margin = (harga jual − modal) ÷ harga jual, dari harga jual efektif — <strong>belum</strong> memperhitungkan PPN &amp; ongkir. Baris <span class="rounded bg-red-50 px-1 text-red-600">merah</span> rugi, <span class="rounded bg-amber-50 px-1 text-amber-600">kuning</span> di bawah 20%. Harga modal &amp; fee tidak pernah tampil di toko.</li>
            <li>Produk bervarian: harga jual, modal, stok, berat &amp; dimensi disunting pada <span class="text-indigo-600">baris variannya</span> (↳). Sel varian yang kosong memakai nilai produk induk (ditampilkan redup). Fee afiliasi tetap di baris produk.</li>
            <li>Stok = jumlah tersedia di gudang default; perubahan tercatat sebagai penyesuaian di riwayat stok.</li>
            <li>Berat dalam <strong>gram</strong>, dimensi <strong>cm</strong> (kemasan siap kirim). Kolom <em>Volumetrik</em> = P×L×T ÷ {{ number_format($divisor, 0, ',', '.') }} — kurir menagih yang lebih besar antara berat asli dan volumetrik; angka <span class="text-amber-600">kuning</span> berarti dimensi terlalu besar dibanding beratnya (cek ulang).</li>
            @unless ($canPrice)
                <li class="text-amber-700">Akun Anda hanya memiliki izin stok &amp; gudang — kolom harga dinonaktifkan.</li>
            @endunless
        </ul>
    </details>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="sticky top-0 z-10">
                <tr class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <th class="px-4 py-3">Produk</th>
                    <th x-show="show('harga')" class="px-3 py-3 text-right">Modal</th>
                    <th x-show="show('harga')" class="px-3 py-3 text-right">Harga Jual</th>
                    <th x-show="show('harga')" class="px-3 py-3 text-right">Harga Coret</th>
                    <th x-show="show('harga')" class="px-3 py-3 text-center">Diskon</th>
                    <th x-show="show('harga')" class="px-3 py-3 text-center" title="Fee afiliator (% dari harga jual); kosong = default toko">Fee %</th>
                    <th x-show="show('harga')" class="px-3 py-3 text-center">Margin</th>
                    <th x-show="show('stok')" class="px-3 py-3 text-center">Stok</th>
                    <th x-show="show('kirim')" class="px-3 py-3 text-right">Berat (g)</th>
                    <th x-show="show('kirim')" class="px-3 py-3 text-center">P × L × T (cm)</th>
                    <th x-show="show('kirim')" class="px-3 py-3 text-right" title="Berat volumetrik = P×L×T ÷ {{ $divisor }}">Volumetrik</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($products as $product)
                    @php
                        $isVariable = $product->product_type === 'variable' || $product->variants->isNotEmpty();
                        $jual = $product->effectivePrice();
                        $coret = $product->isOnSale() ? (float) $product->price : null;
                    @endphp
                    <tr x-data="priceRow({
                            url: '{{ route('admin.prices.update', $product) }}',
                            variable: @js($isVariable),
                            canPrice: @js($canPrice),
                            modal: @js($product->cost_price !== null ? (float) $product->cost_price : null),
                            jual: @js($jual),
                            coret: @js($coret),
                            fee: @js($product->affiliate_rate !== null ? (float) $product->affiliate_rate : null),
                            stok: @js((int) $product->stock),
                            minStok: @js((int) $product->min_stock),
                            berat: @js((int) $product->weight_grams),
                            p: @js((float) $product->length_cm), l: @js((float) $product->width_cm), t: @js((float) $product->height_cm),
                        })"
                        :class="{'bg-red-50/60': marginPct !== null && marginPct < 0, 'bg-amber-50/60': marginPct !== null && marginPct >= 0 && marginPct < 20}">
                        <td class="max-w-[300px] px-4 py-2">
                            <div class="flex items-center gap-3">
                                <img src="{{ $product->primaryImageUrl() }}" alt="" class="h-9 w-9 flex-none rounded object-cover" loading="lazy">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-gray-800" title="{{ $product->name }}">
                                        <a href="{{ route('admin.products.edit', $product) }}" class="hover:text-brand-700 hover:underline" title="Buka form lengkap produk">{{ $product->name }}</a>
                                    </p>
                                    <p class="flex flex-wrap items-center gap-x-1.5 text-xs text-gray-400">
                                        <span>{{ $product->sku }}</span>
                                        <span>· {{ $product->brand?->name ?? '—' }}</span>
                                        @if ($isVariable)<span class="text-indigo-500">· {{ $product->variants->count() }} varian ↓</span>@endif
                                        <span x-show="stok <= 0" x-cloak class="rounded bg-red-100 px-1 py-px text-[10px] font-semibold uppercase text-red-600">Habis</span>
                                        <span x-show="stok > 0 && stok <= Math.max(1, minStok)" x-cloak class="rounded bg-amber-100 px-1 py-px text-[10px] font-semibold uppercase text-amber-700">Menipis</span>
                                        {{-- Status simpan menempel di sel produk supaya selalu terlihat walau tabel digulir. --}}
                                        <span x-show="state === 'saving'" x-cloak class="text-gray-400">· menyimpan…</span>
                                        <span x-show="state === 'saved'" x-cloak class="font-medium text-green-600">· ✓ tersimpan</span>
                                        <span x-show="state === 'error'" x-cloak class="font-medium text-red-600" :title="error" x-text="'· ✗ ' + error"></span>
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td x-show="show('harga')" class="px-3 py-2"><input type="number" min="0" step="1000" data-col="modal" x-model.number="modal" @change="save()" @keydown.enter.prevent="next($event)" :disabled="! canPrice" class="form-input w-28 text-right disabled:bg-gray-100" placeholder="—"></td>
                        <td x-show="show('harga')" class="px-3 py-2"><input type="number" min="0" step="1000" data-col="jual" x-model.number="jual" @change="save()" @keydown.enter.prevent="next($event)" :disabled="variable || ! canPrice" class="form-input w-28 text-right disabled:bg-gray-100" @if ($isVariable) title="Harga produk bervarian mengikuti varian termurah (mulai dari)" @endif></td>
                        <td x-show="show('harga')" class="px-3 py-2"><input type="number" min="0" step="1000" data-col="coret" x-model.number="coret" @change="save()" @keydown.enter.prevent="next($event)" :disabled="variable || ! canPrice" class="form-input w-28 text-right disabled:bg-gray-100" placeholder="—"></td>
                        <td x-show="show('harga')" class="px-3 py-2 text-center text-xs" x-text="diskonLabel()"></td>
                        <td x-show="show('harga')" class="px-3 py-2"><input type="number" min="0" max="100" step="0.5" data-col="fee" x-model.number="fee" @change="save()" @keydown.enter.prevent="next($event)" :disabled="! canPrice" class="form-input mx-auto w-20 text-center disabled:bg-gray-100" placeholder="dflt"></td>
                        <td x-show="show('harga')" class="px-3 py-2 text-center">
                            <span class="font-semibold"
                                  :class="marginPct === null ? 'text-gray-300' : (marginPct < 0 ? 'text-red-600' : (marginPct < 20 ? 'text-amber-600' : 'text-green-600'))"
                                  x-text="marginLabel()"></span>
                        </td>
                        <td x-show="show('stok')" class="px-3 py-2 text-center">
                            @if ($isVariable)
                                <span class="text-xs text-gray-400" title="Total semua varian — sunting di baris varian" x-text="stok"></span>
                            @else
                                <input type="number" min="0" step="1" data-col="stok" x-model.number="stok" @change="save()" @keydown.enter.prevent="next($event)" class="form-input mx-auto w-16 text-center">
                            @endif
                        </td>
                        <td x-show="show('kirim')" class="px-3 py-2"><input type="number" min="0" step="10" data-col="berat" x-model.number="berat" @change="save()" @keydown.enter.prevent="next($event)" class="form-input w-20 text-right" :title="beratLabel()"></td>
                        <td x-show="show('kirim')" class="px-3 py-2">
                            <div class="flex items-center justify-center gap-1">
                                <input type="number" min="0" step="0.5" data-col="p" x-model.number="p" @change="save()" @keydown.enter.prevent="next($event)" class="form-input w-14 px-1 text-center" placeholder="P" aria-label="Panjang (cm)">
                                <span class="text-gray-300">×</span>
                                <input type="number" min="0" step="0.5" data-col="l" x-model.number="l" @change="save()" @keydown.enter.prevent="next($event)" class="form-input w-14 px-1 text-center" placeholder="L" aria-label="Lebar (cm)">
                                <span class="text-gray-300">×</span>
                                <input type="number" min="0" step="0.5" data-col="t" x-model.number="t" @change="save()" @keydown.enter.prevent="next($event)" class="form-input w-14 px-1 text-center" placeholder="T" aria-label="Tinggi (cm)">
                            </div>
                        </td>
                        <td x-show="show('kirim')" class="px-3 py-2 text-right text-xs" :class="volumetricHeavy() ? 'font-semibold text-amber-600' : 'text-gray-400'" :title="volumetricHeavy() ? 'Volumetrik jauh di atas berat asli — cek dimensi' : ''" x-text="volLabel()"></td>
                    </tr>

                    {{-- Baris varian: harga jual/coret, modal, stok, berat & dimensi hidup di sini. --}}
                    @foreach ($product->variants as $variant)
                        @php
                            $vJual = $variant->effectivePrice();
                            $vCoret = $variant->sale_price !== null && (float) $variant->price > $vJual ? (float) $variant->price : null;
                        @endphp
                        <tr class="bg-indigo-50/30"
                            x-data="priceRow({
                                url: '{{ route('admin.prices.update', $product) }}',
                                variantId: @js($variant->id),
                                canPrice: @js($canPrice),
                                modal: @js($variant->cost_price !== null ? (float) $variant->cost_price : null),
                                modalInduk: @js($product->cost_price !== null ? (float) $product->cost_price : null),
                                jual: @js($vJual),
                                coret: @js($vCoret),
                                stok: @js((int) $variant->stock),
                                berat: @js($variant->weight_grams !== null ? (int) $variant->weight_grams : null),
                                p: @js($variant->length_cm !== null ? (float) $variant->length_cm : null),
                                l: @js($variant->width_cm !== null ? (float) $variant->width_cm : null),
                                t: @js($variant->height_cm !== null ? (float) $variant->height_cm : null),
                                beratInduk: @js((int) $product->weight_grams),
                                pInduk: @js((float) $product->length_cm), lInduk: @js((float) $product->width_cm), tInduk: @js((float) $product->height_cm),
                            })"
                            :class="{'bg-red-50/60': marginPct !== null && marginPct < 0, 'bg-amber-50/60': marginPct !== null && marginPct >= 0 && marginPct < 20}">
                            <td class="max-w-[300px] px-4 py-1.5 pl-8">
                                <p class="truncate text-sm text-gray-700" title="{{ $variant->name }}">↳ {{ $variant->name }}</p>
                                <p class="flex items-center gap-1.5 text-[11px] text-gray-400">
                                    <span>{{ $variant->sku }}</span>
                                    <span x-show="stok <= 0" x-cloak class="rounded bg-red-100 px-1 py-px text-[10px] font-semibold uppercase text-red-600">Habis</span>
                                    <span x-show="state === 'saving'" x-cloak class="text-gray-400">· menyimpan…</span>
                                    <span x-show="state === 'saved'" x-cloak class="font-medium text-green-600">· ✓ tersimpan</span>
                                    <span x-show="state === 'error'" x-cloak class="font-medium text-red-600" :title="error" x-text="'· ✗ ' + error"></span>
                                </p>
                            </td>
                            <td x-show="show('harga')" class="px-3 py-1.5"><input type="number" min="0" step="1000" data-col="modal" x-model.number="modal" @change="save()" @keydown.enter.prevent="next($event)" :disabled="! canPrice" class="form-input w-28 text-right disabled:bg-gray-100" :placeholder="modalInduk ? '↑ ' + modalInduk : '—'" title="Modal varian ini — kosongkan untuk memakai modal produk induk"></td>
                            <td x-show="show('harga')" class="px-3 py-1.5"><input type="number" min="0" step="1000" data-col="jual" x-model.number="jual" @change="save()" @keydown.enter.prevent="next($event)" :disabled="! canPrice" class="form-input w-28 text-right disabled:bg-gray-100"></td>
                            <td x-show="show('harga')" class="px-3 py-1.5"><input type="number" min="0" step="1000" data-col="coret" x-model.number="coret" @change="save()" @keydown.enter.prevent="next($event)" :disabled="! canPrice" class="form-input w-28 text-right disabled:bg-gray-100" placeholder="—"></td>
                            <td x-show="show('harga')" class="px-3 py-1.5 text-center text-xs" x-text="diskonLabel()"></td>
                            <td x-show="show('harga')" class="px-3 py-1.5 text-center text-xs text-gray-300" title="Fee dicatat di baris produk">—</td>
                            <td x-show="show('harga')" class="px-3 py-1.5 text-center">
                                <span class="font-semibold"
                                      :class="marginPct === null ? 'text-gray-300' : (marginPct < 0 ? 'text-red-600' : (marginPct < 20 ? 'text-amber-600' : 'text-green-600'))"
                                      :title="modal ? '' : 'Dihitung dari modal produk induk'"
                                      x-text="marginLabel()"></span>
                            </td>
                            <td x-show="show('stok')" class="px-3 py-1.5"><input type="number" min="0" step="1" data-col="stok" x-model.number="stok" @change="save()" @keydown.enter.prevent="next($event)" class="form-input mx-auto w-16 text-center"></td>
                            <td x-show="show('kirim')" class="px-3 py-1.5"><input type="number" min="0" step="10" data-col="berat" x-model.number="berat" @change="save()" @keydown.enter.prevent="next($event)" class="form-input w-20 text-right" :placeholder="'↑ ' + beratInduk" title="Berat varian ini — kosongkan untuk memakai berat produk induk"></td>
                            <td x-show="show('kirim')" class="px-3 py-1.5">
                                <div class="flex items-center justify-center gap-1">
                                    <input type="number" min="0" step="0.5" data-col="p" x-model.number="p" @change="save()" @keydown.enter.prevent="next($event)" class="form-input w-14 px-1 text-center" :placeholder="pInduk || 'P'" aria-label="Panjang (cm)" title="Kosong = mengikuti produk induk">
                                    <span class="text-gray-300">×</span>
                                    <input type="number" min="0" step="0.5" data-col="l" x-model.number="l" @change="save()" @keydown.enter.prevent="next($event)" class="form-input w-14 px-1 text-center" :placeholder="lInduk || 'L'" aria-label="Lebar (cm)" title="Kosong = mengikuti produk induk">
                                    <span class="text-gray-300">×</span>
                                    <input type="number" min="0" step="0.5" data-col="t" x-model.number="t" @change="save()" @keydown.enter.prevent="next($event)" class="form-input w-14 px-1 text-center" :placeholder="tInduk || 'T'" aria-label="Tinggi (cm)" title="Kosong = mengikuti produk induk">
                                </div>
                            </td>
                            <td x-show="show('kirim')" class="px-3 py-1.5 text-right text-xs" :class="volumetricHeavy() ? 'font-semibold text-amber-600' : 'text-gray-400'" :title="volumetricHeavy() ? 'Volumetrik jauh di atas berat asli — cek dimensi' : ''" x-text="volLabel()"></td>
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="11" class="px-4 py-8 text-center text-gray-400">Tidak ada produk pada filter ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $products->links() }}</div>
</div>

    <script>
        const VOLUMETRIC_DIVISOR = @js($divisor);

        function quickEditPage() {
            return {
                // Default kelompok Harga supaya tabel muat di layar; "Semua kolom" tersedia (gulir mendatar).
                cols: 'harga',
                colOptions: [
                    { key: 'semua', label: 'Semua kolom' },
                    { key: 'harga', label: 'Harga & Margin' },
                    { key: 'stok', label: 'Stok' },
                    { key: 'kirim', label: 'Berat & Dimensi' },
                ],
                init() {
                    try { this.cols = localStorage.getItem('quickedit.cols') || 'harga'; } catch (e) {}
                },
                setCols(key) {
                    this.cols = key;
                    try { localStorage.setItem('quickedit.cols', key); } catch (e) {}
                },
                show(group) { return this.cols === 'semua' || this.cols === group; },
            };
        }

        function priceRow(init) {
            const blank = (v) => v === '' || v === null || v === undefined;
            return {
                url: init.url,
                variable: init.variable ?? false,
                variantId: init.variantId ?? null,
                canPrice: init.canPrice ?? true,
                modal: init.modal ?? null,
                modalInduk: init.modalInduk ?? null,
                jual: init.jual,
                coret: init.coret,
                fee: init.fee ?? null,
                stok: init.stok ?? null,
                minStok: init.minStok ?? 0,
                berat: init.berat ?? null,
                p: init.p ?? null, l: init.l ?? null, t: init.t ?? null,
                beratInduk: init.beratInduk ?? null,
                pInduk: init.pInduk ?? null, lInduk: init.lInduk ?? null, tInduk: init.tInduk ?? null,
                state: 'idle',
                error: null,
                get marginPct() {
                    // Varian tanpa modal sendiri memakai modal produk induk.
                    const modalRaw = blank(this.modal) && this.variantId ? this.modalInduk : this.modal;
                    const modal = Number(modalRaw), jual = Number(this.jual);
                    if (! modal || ! jual || modal <= 0 || jual <= 0) return null;
                    return (jual - modal) / jual * 100;
                },
                marginLabel() {
                    return this.marginPct === null ? '—' : this.marginPct.toFixed(1).replace('.', ',') + '%';
                },
                diskonLabel() {
                    const jual = Number(this.jual), coret = Number(this.coret);
                    if (! coret || coret <= jual) return '—';
                    return '-' + ((1 - jual / coret) * 100).toFixed(0) + '%';
                },
                // Nilai efektif untuk pengiriman: varian kosong → induk.
                eff(field) {
                    const own = this[field];
                    if (! blank(own)) return Number(own);
                    return this.variantId ? Number(this[field + 'Induk'] ?? 0) : 0;
                },
                effBerat() {
                    return blank(this.berat) && this.variantId ? Number(this.beratInduk ?? 0) : Number(this.berat ?? 0);
                },
                volGrams() {
                    const p = this.eff('p'), l = this.eff('l'), t = this.eff('t');
                    if (p <= 0 || l <= 0 || t <= 0) return 0;
                    return Math.round(p * l * t / VOLUMETRIC_DIVISOR * 1000);
                },
                volLabel() {
                    const g = this.volGrams();
                    return g > 0 ? (g / 1000).toFixed(2).replace('.', ',') + ' kg' : '—';
                },
                volumetricHeavy() {
                    const g = this.volGrams(), b = this.effBerat();
                    return g > 0 && b > 0 && g > Math.max(b * 2, b + 1000);
                },
                beratLabel() {
                    const b = this.effBerat();
                    return b > 0 ? '= ' + (b / 1000).toFixed(2).replace('.', ',') + ' kg' : '';
                },
                payload() {
                    const kirim = {
                        weight_grams: blank(this.berat) ? null : this.berat,
                        length_cm: blank(this.p) ? null : this.p,
                        width_cm: blank(this.l) ? null : this.l,
                        height_cm: blank(this.t) ? null : this.t,
                    };
                    const stock = { stock: blank(this.stok) ? null : this.stok };
                    if (this.variantId) {
                        return {
                            variant_id: this.variantId,
                            ...(this.canPrice ? {
                                cost_price: blank(this.modal) ? null : this.modal,
                                price: this.jual,
                                compare_price: blank(this.coret) ? null : this.coret,
                            } : {}),
                            ...stock,
                            ...kirim,
                        };
                    }
                    return {
                        ...(this.canPrice ? {
                            cost_price: blank(this.modal) ? null : this.modal,
                            affiliate_rate: blank(this.fee) ? null : this.fee,
                        } : {}),
                        ...(this.variable || ! this.canPrice ? {} : {
                            price: this.jual,
                            compare_price: blank(this.coret) ? null : this.coret,
                        }),
                        ...(this.variable ? {} : stock),
                        ...kirim,
                    };
                },
                // Enter: simpan (lewat blur → change) lalu lompat ke sel yang sama di baris berikutnya.
                next(event) {
                    const el = event.target;
                    el.blur();
                    const col = el.dataset.col;
                    const cells = Array.from(document.querySelectorAll('input[data-col="' + col + '"]')).filter((i) => ! i.disabled && i.offsetParent !== null);
                    const target = cells[cells.indexOf(el) + 1];
                    if (target) { target.focus(); target.select(); }
                },
                async save() {
                    this.state = 'saving';
                    this.error = null;
                    try {
                        const response = await fetch(this.url, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            },
                            body: JSON.stringify(this.payload()),
                        });
                        if (! response.ok) {
                            const body = await response.json().catch(() => ({}));
                            throw new Error(body.message || 'HTTP ' + response.status);
                        }
                        const row = await response.json();
                        // Server yang menentukan pemetaan jual/coret & nilai tersimpan — segarkan dari sana.
                        this.jual = row.jual; this.coret = row.coret; this.stok = row.stok; this.modal = row.modal;
                        this.berat = row.berat; this.p = row.p; this.l = row.l; this.t = row.t;
                        if (this.variantId) {
                            this.modalInduk = row.modal_induk;
                            this.beratInduk = row.berat_induk; this.pInduk = row.p_induk; this.lInduk = row.l_induk; this.tInduk = row.t_induk;
                        } else {
                            this.fee = row.fee; this.minStok = row.min_stok;
                        }
                        this.state = 'saved';
                        setTimeout(() => { if (this.state === 'saved') this.state = 'idle'; }, 2500);
                    } catch (e) {
                        this.state = 'error';
                        this.error = e.message;
                    }
                },
            };
        }
    </script>
@endsection
