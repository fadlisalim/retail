@extends('layouts.admin')

@section('title', 'Input Pesanan Manual')

@section('content')
    <x-admin.page-header title="Input Pesanan Manual"
                         subtitle="Catat penjualan dari Tokopedia, Shopee, WhatsApp, atau showroom agar keuangan & stok tercatat di sini" />

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
            <ul class="list-disc pl-4">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @php
        $productOptions = $products->map(fn ($p) => [
            'id' => $p->id,
            'label' => $p->name.' — '.$p->sku,
            'price' => (float) ($p->sale_price ?: $p->price),
            'variants' => $p->variants->map(fn ($v) => [
                'id' => $v->id,
                'label' => $v->name.' ('.$v->sku.') · stok '.(int) $v->stock,
                'price' => (float) ($v->sale_price ?: $v->price ?: ($p->sale_price ?: $p->price)),
            ])->values(),
        ])->values();
        $oldItems = old('items', [['product_id' => '', 'variant_id' => '', 'name' => '', 'quantity' => 1, 'unit_price' => '']]);
    @endphp

    <form method="POST" action="{{ route('admin.orders.store') }}" class="space-y-5"
          x-data="{
              products: @js($productOptions),
              items: @js(array_values($oldItems)),
              discount: {{ (float) old('discount', 0) }},
              shipping: {{ (float) old('shipping_cost', 0) }},
              tax: {{ (float) old('tax_amount', 0) }},
              markPaid: {{ old('mark_paid') ? 'true' : 'false' }},
              add() { this.items.push({ product_id: '', variant_id: '', name: '', quantity: 1, unit_price: '' }); },
              remove(i) { if (this.items.length > 1) this.items.splice(i, 1); },
              pick(i) { if (this.items[i].product_id) { this.items[i].name = ''; } this.items[i].variant_id = ''; },
              /** Active variants of the picked product (empty for simple products). */
              variantsOf(i) {
                  const p = this.products.find((x) => String(x.id) === String(this.items[i].product_id));
                  return p ? p.variants : [];
              },
              /** Catalogue price: variant price when a variant is chosen. */
              catalogPrice(i) {
                  const p = this.products.find((x) => String(x.id) === String(this.items[i].product_id));
                  if (!p) return 0;
                  const v = p.variants.find((x) => String(x.id) === String(this.items[i].variant_id));
                  return v ? v.price : p.price;
              },
              /** Harga kosong = ikut harga katalog (server memakai aturan yang sama). */
              effectivePrice(i) {
                  const typed = this.items[i].unit_price;
                  return (typed === '' || typed === null) ? this.catalogPrice(i) : (Number(typed) || 0);
              },
              lineTotal(i) { return this.effectivePrice(i) * (Number(this.items[i].quantity) || 0); },
              get subtotal() { return this.items.reduce((s, _, i) => s + this.lineTotal(i), 0); },
              get grand() { return Math.max(0, this.subtotal - (Number(this.discount) || 0) + (Number(this.shipping) || 0) + (Number(this.tax) || 0)); },
              rupiah(n) { return 'Rp ' + Math.round(n || 0).toLocaleString('id-ID'); },
          }">
        @csrf

        {{-- Channel --}}
        <div class="card p-5">
            <h2 class="mb-4 font-semibold text-gray-900">Sumber Penjualan</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.select name="channel" label="Kanal" required :options="$channels" :selected="old('channel', 'tokopedia')" />
                <x-form.input name="external_reference" label="No. Pesanan di Marketplace" :value="old('external_reference')"
                              hint="Opsional — contoh nomor invoice Tokopedia, untuk rekonsiliasi" />
            </div>
        </div>

        {{-- Customer --}}
        <div class="card p-5">
            <h2 class="mb-1 font-semibold text-gray-900">Data Pelanggan</h2>
            <p class="mb-4 text-sm text-gray-500">Marketplace biasanya hanya memberi nama &amp; nomor HP — itu sudah cukup.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input name="customer_name" label="Nama Pelanggan" required :value="old('customer_name')" />
                <x-form.input name="customer_phone" label="No. WhatsApp / HP" required :value="old('customer_phone')" hint="Contoh: 08123456789" />
                <x-form.input name="customer_email" label="Email (opsional)" type="email" :value="old('customer_email')"
                              hint="Kosongkan bila tidak ada — sistem membuat email internal otomatis" />
                <div class="flex items-end pb-2">
                    <x-form.checkbox name="create_customer" label="Simpan sebagai akun pelanggan" :checked="old('create_customer', true)" />
                </div>
            </div>
        </div>

        {{-- Items --}}
        <div class="card p-5">
            <h2 class="mb-4 font-semibold text-gray-900">Item Pesanan</h2>
            <div class="space-y-3">
                <template x-for="(item, index) in items" :key="index">
                    <div class="rounded-lg border border-gray-200 p-3">
                        <div class="grid gap-3 sm:grid-cols-[2fr_90px_150px_120px]">
                            <div>
                                <label class="input-label">Produk katalog</label>
                                <select class="form-select" :name="'items[' + index + '][product_id]'" x-model="item.product_id" @change="pick(index)">
                                    <option value="">— Item manual (di luar katalog) —</option>
                                    <template x-for="p in products" :key="p.id">
                                        <option :value="p.id" x-text="p.label"></option>
                                    </template>
                                </select>
                                <input type="text" class="form-input mt-2" placeholder="Nama item manual"
                                       :name="'items[' + index + '][name]'" x-model="item.name" x-show="!item.product_id" x-cloak>

                                {{-- Variable products keep stock & price per variant. --}}
                                <template x-if="variantsOf(index).length">
                                    <div class="mt-2">
                                        <select class="form-select" :name="'items[' + index + '][variant_id]'" x-model="item.variant_id" required>
                                            <option value="">— Pilih varian —</option>
                                            <template x-for="v in variantsOf(index)" :key="v.id">
                                                <option :value="v.id" x-text="v.label"></option>
                                            </template>
                                        </select>
                                    </div>
                                </template>
                            </div>
                            <div>
                                <label class="input-label">Qty</label>
                                <input type="number" min="1" class="form-input" :name="'items[' + index + '][quantity]'" x-model.number="item.quantity">
                            </div>
                            <div>
                                <label class="input-label">Harga Satuan</label>
                                <input type="number" min="0" step="1" class="form-input"
                                       :name="'items[' + index + '][unit_price]'" x-model="item.unit_price"
                                       :placeholder="item.product_id ? rupiah(catalogPrice(index)) : 'Wajib diisi'">
                                <p class="mt-1 text-[11px] text-gray-400" x-show="item.product_id" x-cloak>
                                    Kosongkan = pakai harga katalog
                                </p>
                            </div>
                            <div>
                                <label class="input-label">Subtotal</label>
                                <p class="pt-2 text-sm font-semibold text-gray-800" x-text="rupiah(lineTotal(index))"></p>
                                <button type="button" @click="remove(index)" x-show="items.length > 1" class="mt-1 text-xs font-medium text-red-600 hover:underline">Hapus</button>
                            </div>
                        </div>
                    </div>
                </template>
                <button type="button" @click="add()" class="btn-outline text-sm">+ Tambah Item</button>
            </div>

            <div class="mt-4 border-t border-gray-100 pt-3">
                <x-form.checkbox name="skip_stock" label="Jangan potong stok (barang sudah keluar / stok dikelola terpisah)" />
                <p class="mt-1 pl-6 text-xs text-gray-400">Centang bila stok di sistem ini tidak mencukupi atau memang tidak dipakai untuk kanal tersebut.</p>
            </div>
        </div>

        {{-- Totals --}}
        <div class="card p-5">
            <h2 class="mb-4 font-semibold text-gray-900">Biaya &amp; Total</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="discount" class="input-label">Diskon</label>
                    <input id="discount" type="number" min="0" step="1" name="discount" class="form-input" x-model.number="discount">
                </div>
                <div>
                    <label for="shipping_cost" class="input-label">Ongkos Kirim</label>
                    <input id="shipping_cost" type="number" min="0" step="1" name="shipping_cost" class="form-input" x-model.number="shipping">
                </div>
                <div>
                    <label for="tax_amount" class="input-label">Pajak (PPN)</label>
                    <input id="tax_amount" type="number" min="0" step="1" name="tax_amount" class="form-input" x-model.number="tax">
                </div>
            </div>

            <dl class="mt-4 space-y-1 border-t border-gray-100 pt-4 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Subtotal</dt><dd class="font-medium" x-text="rupiah(subtotal)"></dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Diskon</dt><dd class="text-red-600" x-text="'- ' + rupiah(discount)"></dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Ongkir + Pajak</dt><dd x-text="rupiah((Number(shipping)||0) + (Number(tax)||0))"></dd></div>
                <div class="flex justify-between border-t border-gray-100 pt-2 text-base"><dt class="font-semibold text-gray-700">Total</dt><dd class="font-bold text-brand-700" x-text="rupiah(grand)"></dd></div>
            </dl>
        </div>

        {{-- Payment --}}
        <div class="card p-5">
            <h2 class="mb-4 font-semibold text-gray-900">Pembayaran &amp; Catatan</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input name="payment_method" label="Metode Pembayaran" :value="old('payment_method')" hint="Contoh: Tokopedia (BCA VA), Transfer BNI, Tunai" />
                <x-form.input name="shipping_method" label="Pengiriman" :value="old('shipping_method')" hint="Contoh: JNE REG, Ambil di gudang" />
                <div class="sm:col-span-2">
                    <label class="input-label" for="affiliate_id">Afiliator <span class="text-gray-400">(opsional)</span></label>
                    <select name="affiliate_id" id="affiliate_id" class="form-select">
                        <option value="">— Tanpa afiliator —</option>
                        @foreach ($affiliates as $aff)
                            <option value="{{ $aff->id }}" @selected((string) old('affiliate_id') === (string) $aff->id)>{{ $aff->full_name }} ({{ $aff->code }})</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-400">Pesanan WA/offline tidak membawa cookie referral — pilih afiliatornya di sini agar komisinya tetap tercatat (masuk saat dibayar, cair saat pesanan Selesai).</p>
                </div>
                <div class="sm:col-span-2 space-y-2 border-t border-gray-100 pt-3">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="hidden" name="mark_paid" value="0">
                        <input type="checkbox" name="mark_paid" value="1" x-model="markPaid" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                        <span>Sudah dibayar (langsung terbit kuitansi &amp; stok terpotong)</span>
                    </label>
                    <div x-show="markPaid" x-cloak class="grid gap-4 pl-6 sm:grid-cols-2">
                        <x-form.input name="paid_at" label="Tanggal Bayar" type="date" :value="old('paid_at', now()->toDateString())" />
                        <div class="flex items-end pb-2">
                            <x-form.checkbox name="send_thanks" label="Kirim ucapan terima kasih via WhatsApp" :checked="old('send_thanks', true)" />
                        </div>
                    </div>
                </div>
                <div class="sm:col-span-2">
                    <x-form.textarea name="customer_note" label="Catatan Pelanggan" rows="2" />
                </div>
                <div class="sm:col-span-2">
                    <x-form.textarea name="internal_note" label="Catatan Internal" rows="2" hint="Hanya terlihat admin" />
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn-primary">Simpan Pesanan</button>
            <a href="{{ route('admin.orders.index') }}" class="btn-outline">Batal</a>
        </div>
    </form>
@endsection
