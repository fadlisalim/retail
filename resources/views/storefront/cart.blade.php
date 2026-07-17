@extends('layouts.storefront')
@section('title', 'Keranjang Belanja')
@section('noindex', 'noindex')

@section('content')
    <h1 class="mb-4 text-xl font-bold text-gray-900 sm:text-2xl">Keranjang Belanja</h1>

    @if ($cart->items->isEmpty() && $cart->savedItems->isEmpty())
        <div class="card grid place-items-center gap-3 p-12 text-center">
            <svg class="h-16 w-16 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/></svg>
            <p class="text-lg font-semibold text-gray-700">Keranjang Anda kosong</p>
            <p class="text-sm text-gray-500">Belum ada produk. Yuk mulai jelajahi katalog energi surya kami.</p>
            <a href="{{ route('products.index') }}" class="btn-primary mt-1">Mulai Belanja</a>
        </div>
    @else
        @php
            $summaryData = [
                'item_count' => $totals->itemCount(),
                'subtotal' => rupiah($totals->itemsSubtotal),
                'product_discount' => $totals->productDiscount > 0 ? rupiah($totals->productDiscount) : null,
                'coupon_discount' => $totals->couponDiscount > 0 ? rupiah($totals->couponDiscount) : null,
                'tax' => $totals->taxAmount > 0 ? rupiah($totals->taxAmount) : null,
                'grand_total' => rupiah($totals->grandTotal),
            ];
        @endphp
        <div class="grid gap-6 lg:grid-cols-[1fr_340px]" x-data="cartPage(@js($summaryData))">
            {{-- Toast (AJAX feedback) --}}
            <div x-show="toast" x-cloak x-transition role="status" aria-live="polite"
                 class="fixed inset-x-0 bottom-20 z-50 mx-auto w-max max-w-[90%] rounded-lg px-4 py-2 text-sm text-white shadow-lg lg:bottom-6"
                 :class="toastErr ? 'bg-red-600' : 'bg-gray-900'" x-text="toast"></div>
            <div class="space-y-4">
                {{-- Buyable items --}}
                @php($buyable = $cart->items->filter(fn($i) => $i->product && !$i->product->requires_quotation))
                @if ($buyable->isNotEmpty())
                    <div class="card divide-y divide-gray-100">
                        <div class="px-4 py-3 text-sm font-semibold text-gray-700">Produk untuk Checkout</div>
                        @foreach ($buyable as $item)
                            <div class="flex gap-3 p-4" data-cartrow="{{ $item->id }}">
                                <a href="{{ route('products.show', $item->product->slug) }}" class="shrink-0">
                                    <img src="{{ $item->product->primaryImageUrl() }}" alt="{{ $item->product->name }}" class="h-20 w-20 rounded-lg object-cover">
                                </a>
                                <div class="flex flex-1 flex-col">
                                    <a href="{{ route('products.show', $item->product->slug) }}" class="text-sm font-medium text-gray-800 hover:text-brand-700">{{ $item->product->name }}</a>
                                    @if ($item->variant)<p class="text-xs text-gray-500">{{ $item->variant->name }}</p>@endif
                                    <p class="mt-1 text-sm font-bold text-gray-900">{{ rupiah($item->currentUnitPrice()) }}</p>

                                    @if ($item->product->requiresConditionAck() && ! $item->condition_acknowledged)
                                        <form action="{{ route('cart.acknowledge', $item) }}" method="POST" class="mt-1">
                                            @csrf
                                            <label class="flex items-center gap-2 text-xs text-amber-700">
                                                <input type="checkbox" onchange="this.form.submit()" class="rounded border-amber-300 text-amber-600">
                                                Saya menyetujui kondisi produk ({{ $item->product->conditionEnum()->label() }})
                                            </label>
                                        </form>
                                    @elseif ($item->product->requiresConditionAck())
                                        <span class="mt-1 inline-flex items-center gap-1 text-xs text-green-600"><svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>Kondisi disetujui</span>
                                    @endif

                                    <div class="mt-2 flex items-center gap-3">
                                        <form action="{{ route('cart.update', $item) }}" method="POST" class="inline-flex items-center rounded-lg border border-gray-300" @submit.prevent="mutate($event)">
                                            @csrf @method('PATCH')
                                            <button type="submit" name="quantity" value="{{ max(1, $item->quantity - 1) }}" data-dec="{{ $item->id }}" class="px-2 py-1 text-gray-500 disabled:opacity-40" aria-label="Kurangi jumlah" @disabled($item->quantity <= 1)>−</button>
                                            <span class="w-10 text-center text-sm" data-qty="{{ $item->id }}" aria-live="polite">{{ $item->quantity }}</span>
                                            <button type="submit" name="quantity" value="{{ $item->quantity + 1 }}" data-inc="{{ $item->id }}" class="px-2 py-1 text-gray-500" aria-label="Tambah jumlah">+</button>
                                        </form>
                                        <form action="{{ route('cart.save', $item) }}" method="POST" @submit.prevent="mutate($event)">@csrf<button class="text-xs text-gray-400 hover:text-brand-600">Simpan untuk nanti</button></form>
                                        <form action="{{ route('cart.destroy', $item) }}" method="POST" @submit.prevent="mutate($event)">@csrf @method('DELETE')<button class="text-xs text-red-400 hover:text-red-600">Hapus</button></form>
                                    </div>
                                </div>
                                <div class="text-right text-sm font-bold text-gray-900" data-line="{{ $item->id }}">{{ rupiah($item->currentUnitPrice() * $item->quantity) }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Quotation items (separated) --}}
                @if ($quotationItems->isNotEmpty())
                    <div class="card divide-y divide-gray-100 border-teal-200">
                        <div class="bg-teal-50 px-4 py-3 text-sm font-semibold text-teal-800">Produk via Permintaan Penawaran</div>
                        @foreach ($quotationItems as $item)
                            @continue(! $item->product)
                            <div class="flex items-center gap-3 p-4">
                                <img src="{{ $item->product->primaryImageUrl() }}" alt="{{ $item->product->name }}" class="h-16 w-16 rounded-lg object-cover">
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-800">{{ $item->product->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $item->quantity }} unit — perlu penawaran</p>
                                </div>
                                <a href="{{ route('quotations.create', ['produk' => $item->product->slug]) }}" class="btn-outline text-xs">Minta Penawaran</a>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Saved for later --}}
                @if ($cart->savedItems->isNotEmpty())
                    <div class="card divide-y divide-gray-100">
                        <div class="px-4 py-3 text-sm font-semibold text-gray-700">Disimpan untuk Nanti</div>
                        @foreach ($cart->savedItems as $item)
                            @continue(! $item->product)
                            <div class="flex items-center gap-3 p-4">
                                <img src="{{ $item->product->primaryImageUrl() }}" alt="{{ $item->product->name }}" class="h-16 w-16 rounded-lg object-cover">
                                <div class="flex-1"><p class="text-sm font-medium text-gray-800">{{ $item->product->name }}</p><p class="text-sm text-gray-500">{{ rupiah($item->currentUnitPrice()) }}</p></div>
                                <form action="{{ route('cart.move', $item) }}" method="POST">@csrf<button class="btn-outline text-xs">Pindah ke Keranjang</button></form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Summary --}}
            <div class="lg:sticky lg:top-24 lg:self-start">
                <div class="card space-y-3 p-4">
                    <h2 class="font-semibold text-gray-800">Ringkasan Belanja</h2>

                    <form action="{{ route('cart.coupon') }}" method="POST" class="flex gap-2">
                        @csrf
                        <input name="coupon_code" value="{{ $cart->coupon_code }}" placeholder="Kode voucher" class="form-input text-sm">
                        <button class="btn-outline text-sm">Pakai</button>
                    </form>
                    @if ($cart->coupon_code)
                        <form action="{{ route('cart.coupon.remove') }}" method="POST">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-500 hover:underline">Lepas voucher "{{ $cart->coupon_code }}"</button>
                        </form>
                        @if ($totals->couponDiscount <= 0 && ! $totals->couponFreeShipping)
                            <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700" role="alert">Voucher "{{ $cart->coupon_code }}" belum berlaku untuk belanja saat ini.</p>
                        @endif
                    @endif

                    <dl class="space-y-1 border-t border-gray-100 pt-3 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-500">Subtotal (<span x-text="s.item_count"></span> item)</dt><dd x-text="s.subtotal"></dd></div>
                        <div class="flex justify-between text-green-600" x-show="s.product_discount"><dt>Hemat promo</dt><dd>−<span x-text="s.product_discount"></span></dd></div>
                        <div class="flex justify-between text-green-600" x-show="s.coupon_discount"><dt>Voucher</dt><dd>−<span x-text="s.coupon_discount"></span></dd></div>
                        <div class="flex justify-between text-gray-400"><dt>Ongkir</dt><dd>Dihitung saat checkout</dd></div>
                        <div class="flex justify-between" x-show="s.tax"><dt class="text-gray-500">Estimasi PPN</dt><dd x-text="s.tax"></dd></div>
                    </dl>
                    <div class="flex justify-between border-t border-gray-100 pt-3 text-base font-bold">
                        <span>Estimasi Total</span><span class="text-brand-700" x-text="s.grand_total"></span>
                    </div>

                    @if ($buyable->isNotEmpty())
                        <a href="{{ route('checkout.index') }}" class="btn-primary w-full">Lanjut ke Checkout</a>
                    @endif
                    <a href="{{ route('products.index') }}" class="block text-center text-sm text-gray-400 hover:text-brand-600">Lanjut belanja</a>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
function cartPage(summary) {
    return {
        s: summary,
        toast: '', toastErr: false, _t: null,
        showToast(msg, isErr = false) {
            this.toast = msg; this.toastErr = isErr;
            clearTimeout(this._t);
            this._t = setTimeout(() => { this.toast = ''; }, 3000);
        },
        /* Intercept qty/remove/save forms → server-authoritative AJAX update, no reload. */
        async mutate(e) {
            const form = e.target;
            const submitter = e.submitter;
            const row = form.closest('[data-cartrow]');
            const body = new FormData(form);
            if (submitter && submitter.name) body.append(submitter.name, submitter.value); // qty from the clicked button
            if (submitter) submitter.disabled = true;
            if (row) row.classList.add('opacity-60', 'pointer-events-none');
            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                    body,
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) { this.showToast(data.message || 'Gagal memperbarui keranjang.', true); return; }
                this.apply(data);
                if (data.notice) this.showToast(data.notice);
            } catch (err) {
                this.showToast('Terjadi kesalahan jaringan. Coba lagi.', true);
            } finally {
                if (submitter) submitter.disabled = false;
                if (row) row.classList.remove('opacity-60', 'pointer-events-none');
            }
        },
        apply(data) {
            if (data.empty) { window.location.reload(); return; } // fall back to server-rendered empty/saved state
            const lines = data.lines || {};
            for (const [id, line] of Object.entries(lines)) {
                const q = document.querySelector('[data-qty="' + id + '"]');
                const lt = document.querySelector('[data-line="' + id + '"]');
                const dec = document.querySelector('[data-dec="' + id + '"]');
                const inc = document.querySelector('[data-inc="' + id + '"]');
                if (q) q.textContent = line.quantity;
                if (lt) lt.textContent = line.line_formatted;
                if (dec) { dec.value = Math.max(1, line.quantity - 1); dec.disabled = line.quantity <= 1; }
                if (inc) inc.value = line.quantity + 1;
            }
            document.querySelectorAll('[data-cartrow]').forEach(el => {
                if (!lines[el.getAttribute('data-cartrow')]) el.remove(); // removed / saved-for-later
            });
            this.s = data.summary;
            if (window.Alpine && window.Alpine.store('cart')) window.Alpine.store('cart').count = data.count;
        },
    };
}
</script>
@endpush
