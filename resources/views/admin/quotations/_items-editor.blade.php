{{-- Editor baris penawaran (Alpine). Dipakai halaman buat & detail.
     Variabel: $products, $ppnPercent, $ppnEnabled, $quotation (opsional), $submitRoute --}}
@php
    $productOptions = $products->map(fn ($p) => [
        'id' => $p->id,
        'label' => $p->name.' — '.$p->sku,
        'name' => $p->name,
        'price' => (float) ($p->sale_price ?: $p->price),
        'variants' => $p->variants->map(fn ($v) => ['id' => $v->id, 'label' => $v->name.' ('.$v->sku.')', 'name' => $p->name.' – '.$v->name, 'price' => (float) ($v->sale_price ?: $v->price ?: ($p->sale_price ?: $p->price))])->values(),
    ])->values();
    $q = $quotation ?? null;
    $initialItems = old('items') ?: ($q && $q->items->isNotEmpty()
        ? $q->items->map(fn ($it) => ['id' => $it->id, 'product_id' => $it->product_id ?: '', 'product_variant_id' => $it->product_variant_id ?: '', 'name' => $it->name, 'note' => $it->note ?? '', 'quantity' => $it->quantity, 'unit_price' => (int) $it->unit_price, 'discount' => (int) $it->discount, 'is_taxable' => (bool) $it->is_taxable])->values()->all()
        : [['id' => '', 'product_id' => '', 'product_variant_id' => '', 'name' => '', 'note' => '', 'quantity' => 1, 'unit_price' => '', 'discount' => 0, 'is_taxable' => true]]);
@endphp
<div x-data="quotationEditor({{ \Illuminate\Support\Js::from([
        'products' => $productOptions,
        'items' => array_values($initialItems),
        'discount' => (float) old('discount', $q?->discount ?? 0),
        'shipping' => (float) old('shipping_cost', $q?->shipping_cost ?? 0),
        'applyTax' => (bool) old('apply_tax', $q ? ($q->tax_amount > 0 || $q->items->isEmpty()) && $ppnEnabled : $ppnEnabled),
        'ppn' => (float) $ppnPercent,
    ]) }})" class="space-y-4">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="border-b border-gray-100 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="py-2 pr-2" style="min-width:280px">Produk / jasa</th>
                    <th class="px-2 py-2 text-center">Qty</th>
                    <th class="px-2 py-2">Harga satuan</th>
                    <th class="px-2 py-2">Diskon</th>
                    <th class="px-2 py-2 text-center" title="Kena PPN">PPN</th>
                    <th class="px-2 py-2 text-right">Jumlah</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <template x-for="(item, i) in items" :key="i">
                    <tr class="align-top">
                        <td class="py-2 pr-2">
                            <input type="hidden" :name="'items[' + i + '][id]'" :value="item.id">
                            <select class="form-select text-xs" :name="'items[' + i + '][product_id]'" x-model="item.product_id" @change="pick(i)">
                                <option value="">— Produk / jasa manual —</option>
                                <template x-for="p in products" :key="p.id"><option :value="p.id" x-text="p.label"></option></template>
                            </select>
                            <template x-if="variantsOf(i).length">
                                <select class="form-select mt-1 text-xs" :name="'items[' + i + '][product_variant_id]'" x-model="item.product_variant_id" @change="pickVariant(i)">
                                    <option value="">— Pilih varian —</option>
                                    <template x-for="v in variantsOf(i)" :key="v.id"><option :value="v.id" x-text="v.label"></option></template>
                                </select>
                            </template>
                            <input type="text" class="form-input mt-1 text-sm" placeholder="Nama produk / jasa (tampil di PDF)" :name="'items[' + i + '][name]'" x-model="item.name" required>
                            <input type="text" class="form-input mt-1 text-xs" placeholder="Keterangan (opsional): spesifikasi, merek, lingkup jasa…" :name="'items[' + i + '][note]'" x-model="item.note">
                        </td>
                        <td class="px-2 py-2"><input type="number" min="1" class="form-input w-20 text-center" :name="'items[' + i + '][quantity]'" x-model.number="item.quantity"></td>
                        <td class="px-2 py-2"><input type="number" min="0" step="1" class="form-input w-32" :name="'items[' + i + '][unit_price]'" x-model="item.unit_price" required></td>
                        <td class="px-2 py-2"><input type="number" min="0" step="1" class="form-input w-28" :name="'items[' + i + '][discount]'" x-model="item.discount"></td>
                        <td class="px-2 py-2 text-center"><input type="hidden" :name="'items[' + i + '][is_taxable]'" :value="item.is_taxable ? 1 : 0"><input type="checkbox" x-model="item.is_taxable" class="rounded border-gray-300 text-brand-600"></td>
                        <td class="px-2 py-2 text-right font-medium text-gray-800" x-text="rupiah(lineTotal(i))"></td>
                        <td class="py-2 pl-2"><button type="button" @click="remove(i)" class="text-xs text-red-600 hover:underline">Hapus</button></td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
    <div class="flex flex-wrap gap-2">
        <button type="button" @click="add()" class="btn-outline text-sm">+ Tambah baris</button>
        <span class="self-center text-xs text-gray-400">Pilih produk dari katalog (harga ikut katalog, bisa diubah) atau biarkan "manual" untuk produk/jasa di luar website, mis. jasa instalasi, survei, tiang.</span>
    </div>

    <div class="grid gap-4 border-t border-gray-100 pt-4 sm:grid-cols-2">
        <div><label class="input-label">Diskon tambahan (Rp)</label><input type="number" step="1" min="0" name="discount" x-model="discount" class="form-input"></div>
        <div><label class="input-label">Ongkos kirim / instalasi (Rp)</label><input type="number" step="1" min="0" name="shipping_cost" x-model="shipping" class="form-input"></div>
        <div><label class="input-label">Termin pembayaran</label><input type="text" name="payment_terms" value="{{ old('payment_terms', $q?->payment_terms) }}" placeholder="mis. DP 50%, pelunasan sebelum kirim" class="form-input"></div>
        <div><label class="input-label">Berlaku sampai</label><input type="date" name="valid_until" value="{{ old('valid_until', $q?->valid_until?->format('Y-m-d') ?? now()->addDays(14)->format('Y-m-d')) }}" class="form-input"></div>
        <div class="sm:col-span-2">
            <label class="flex items-center gap-2 text-sm text-gray-700"><input type="hidden" name="apply_tax" :value="applyTax ? 1 : 0"><input type="checkbox" x-model="applyTax" class="rounded border-gray-300 text-brand-600"> Tambahkan PPN {{ rtrim(rtrim(number_format($ppnPercent, 2, ',', '.'), '0'), ',') }}% pada item yang kena pajak</label>
        </div>
        <div class="sm:col-span-2"><label class="input-label">Catatan / ketentuan (tampil di PDF)</label><textarea name="admin_note" rows="3" class="form-textarea" placeholder="Garansi, lingkup pekerjaan, pengecualian, waktu pengerjaan…">{{ old('admin_note', $q?->admin_note) }}</textarea></div>
    </div>

    <div class="rounded-lg bg-gray-50 p-4 text-sm">
        <div class="flex justify-between"><span class="text-gray-500">Subtotal</span><span x-text="rupiah(subtotal)"></span></div>
        <div class="flex justify-between" x-show="Number(discount) > 0"><span class="text-gray-500">Diskon</span><span x-text="'− ' + rupiah(discount)"></span></div>
        <div class="flex justify-between" x-show="Number(shipping) > 0"><span class="text-gray-500">Ongkir / instalasi</span><span x-text="rupiah(shipping)"></span></div>
        <div class="flex justify-between" x-show="applyTax"><span class="text-gray-500">PPN</span><span x-text="rupiah(tax)"></span></div>
        <div class="mt-1 flex justify-between border-t border-gray-200 pt-2 text-base font-bold"><span>Total</span><span class="text-brand-700" x-text="rupiah(grand)"></span></div>
    </div>
</div>

@once
@push('scripts')
<script>
    function quotationEditor(cfg) {
        return {
            products: cfg.products, items: cfg.items, discount: cfg.discount, shipping: cfg.shipping, applyTax: cfg.applyTax, ppn: cfg.ppn,
            add() { this.items.push({ id: '', product_id: '', product_variant_id: '', name: '', note: '', quantity: 1, unit_price: '', discount: 0, is_taxable: true }); },
            remove(i) { this.items.splice(i, 1); if (!this.items.length) this.add(); },
            product(i) { return this.products.find(p => String(p.id) === String(this.items[i].product_id)) || null; },
            variantsOf(i) { const p = this.product(i); return p ? p.variants : []; },
            pick(i) {
                const p = this.product(i); this.items[i].product_variant_id = '';
                if (p) { this.items[i].name = p.name; if (!p.variants.length) this.items[i].unit_price = Math.round(p.price); }
            },
            pickVariant(i) {
                const p = this.product(i); const v = p ? p.variants.find(x => String(x.id) === String(this.items[i].product_variant_id)) : null;
                if (v) { this.items[i].name = v.name; this.items[i].unit_price = Math.round(v.price); }
            },
            lineTotal(i) { const it = this.items[i]; return Math.max(0, (Number(it.unit_price) || 0) * (Number(it.quantity) || 0) - (Number(it.discount) || 0)); },
            get subtotal() { return this.items.reduce((s, _, i) => s + this.lineTotal(i), 0); },
            get taxable() { return this.items.reduce((s, it, i) => s + (it.is_taxable ? this.lineTotal(i) : 0), 0); },
            get tax() { return this.applyTax ? Math.round(Math.max(0, this.taxable - (Number(this.discount) || 0)) * this.ppn) / 100 : 0; },
            get grand() { return Math.max(0, this.subtotal - (Number(this.discount) || 0) + (Number(this.shipping) || 0) + this.tax); },
            rupiah(n) { return 'Rp ' + Math.round(n || 0).toLocaleString('id-ID'); },
        };
    }
</script>
@endpush
@endonce
