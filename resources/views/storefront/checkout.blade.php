@extends('layouts.storefront')
@section('title', 'Checkout')
@section('noindex', 'noindex')

@section('content')
    <h1 class="mb-4 text-xl font-bold text-gray-900 sm:text-2xl">Checkout</h1>

    @php
        // Only *added* PPN is new money at checkout; embedded PPN is already inside
        // the subtotal (grandTotal only adds taxAdded). Passing taxAmount here would
        // double-count embedded PPN for price_includes_tax products.
        $taxAdded = round($totals->grandTotal - ($totals->itemsSubtotal - $totals->couponDiscount), 2);
        $taxEmbedded = round(max(0, $totals->taxAmount - $taxAdded), 2);
        // Keep the chosen address selected after a server validation error.
        $selectedAddr = $addresses->firstWhere('id', (int) old('address_choice')) ?? $defaultAddress;
    @endphp
    <form action="{{ route('checkout.store') }}" method="POST" x-on:submit="submitting = true"
          x-data="checkout({{ $totals->itemsSubtotal - $totals->couponDiscount }}, {{ $taxAdded }})">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
        <input type="hidden" name="shipping_provider" x-model="shippingProvider">
        <input type="hidden" name="shipping_service" x-model="shippingService">

        <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
            <div class="space-y-5">
                {{-- Customer --}}
                <section class="card p-4">
                    <h2 class="mb-3 font-semibold text-gray-800">1. Data Pembeli</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-form.input name="customer_name" label="Nama" required :value="auth()->user()?->name" />
                        <x-form.input name="customer_email" label="Email" type="email" required :value="auth()->user()?->email" />
                        <x-form.input name="customer_phone" label="No. HP / WhatsApp" required :value="auth()->user()?->whatsapp" />
                    </div>
                </section>

                {{-- Address: only a saved shipping address may be used (Indah-valid). --}}
                <section class="card p-4">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="font-semibold text-gray-800">2. Alamat Pengiriman</h2>
                        <a href="{{ route('account.addresses.create') }}" class="text-sm font-medium text-brand-600 hover:underline">+ Tambah Alamat</a>
                    </div>
                    <input type="hidden" name="address_id" x-model="addressId">
                    @if ($addresses->isNotEmpty())
                        <div class="space-y-2">
                            @foreach ($addresses as $addr)
                                <label class="flex cursor-pointer items-start gap-2 rounded-lg border p-3 text-sm"
                                       :class="addressId == {{ $addr->id }} ? 'border-brand-500 bg-brand-50' : 'border-gray-200 hover:border-brand-400'">
                                    <input type="radio" name="address_choice" class="mt-1" value="{{ $addr->id }}" @checked($selectedAddr?->id === $addr->id)
                                           @change="selectAddress({{ $addr->id }}, {{ Illuminate\Support\Js::from($addr->province) }}, {{ Illuminate\Support\Js::from($addr->city) }})">
                                    <span>
                                        <strong>{{ $addr->label }}</strong> — {{ $addr->recipient_name }}
                                        @if ($addr->phone)<span class="text-gray-400">• {{ $addr->phone }}</span>@endif
                                        <br><span class="text-gray-500">{{ $addr->fullAddress() }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-2 text-xs text-gray-400">Alamat diambil dari akun Anda. <a href="{{ route('account.addresses.index') }}" class="text-brand-600 hover:underline">Kelola alamat</a>.</p>
                    @else
                        <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center">
                            <p class="text-sm text-gray-500">Anda belum punya alamat pengiriman tersimpan.</p>
                            <p class="mt-1 text-xs text-gray-400">Tambah alamat dulu (provinsi &amp; kota mengikuti jangkauan Indah Cargo) agar ongkir bisa dihitung.</p>
                            <a href="{{ route('account.addresses.create') }}" class="btn-primary mt-3">Tambah Alamat</a>
                        </div>
                    @endif
                </section>

                {{-- Shipping --}}
                <section class="card p-4">
                    <h2 class="mb-3 font-semibold text-gray-800">3. Pengiriman</h2>
                    <p x-show="!addr.province" class="text-sm text-gray-400">Pilih alamat pengiriman untuk melihat opsi &amp; ongkir.</p>
                    <div x-show="shippingError" x-cloak role="alert" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                        Gagal memuat ongkir. <button type="button" @click="loadShipping()" class="font-semibold underline">Coba lagi</button>
                    </div>
                    <div class="space-y-2" x-show="shippingOptions.length">
                        <template x-for="opt in shippingOptions" :key="opt.provider_code + opt.service_code">
                            <label class="flex cursor-pointer items-center justify-between rounded-lg border border-gray-200 p-3 text-sm hover:border-brand-400">
                                <span class="flex items-center gap-2">
                                    <input type="radio" name="shipping_choice" :value="opt.provider_code + '|' + opt.service_code"
                                           @change="selectShipping(opt)">
                                    <span>
                                        <span class="font-medium text-gray-800" x-text="opt.label"></span>
                                        <span class="block text-xs text-gray-400">
                                            <span x-text="opt.estimated_days ? ('Estimasi ' + opt.estimated_days) : (opt.note || '')"></span>
                                            <span x-show="opt.confirmed && opt.billable_weight_grams > 0" x-text="' • Berat ' + (opt.billable_weight_grams / 1000) + ' kg'"></span>
                                            <span x-show="opt.confirmed && opt.packing_fee > 0" x-text="' • Packing ' + rupiah(opt.packing_fee)"></span>
                                        </span>
                                        @if ($siteSettings->get('pickup.address'))
                                            {{-- Warehouse address + map for the pickup option --}}
                                            <span x-show="opt.type === 'pickup'" class="mt-1.5 block text-xs leading-snug text-gray-600">
                                                📍 {{ $siteSettings->get('pickup.address') }}
                                                @if ($siteSettings->get('pickup.maps_url'))
                                                    <a href="{{ $siteSettings->get('pickup.maps_url') }}" target="_blank" rel="noopener" @click.stop
                                                       class="mt-1 inline-flex w-fit items-center gap-1 rounded-full bg-brand-50 px-2.5 py-1 font-semibold text-brand-700 hover:bg-brand-100">
                                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
                                                        Lihat di Google Maps
                                                    </a>
                                                @endif
                                            </span>
                                        @endif
                                    </span>
                                </span>
                                <span class="font-semibold" x-text="opt.confirmed ? opt.rupiah : 'Dikonfirmasi'"></span>
                            </label>
                        </template>
                    </div>
                    <p x-show="loadingShipping" class="text-sm text-gray-400">Menghitung ongkir…</p>
                </section>

                {{-- Notes --}}
                <section class="card p-4">
                    <h2 class="mb-3 font-semibold text-gray-800">4. Catatan (opsional)</h2>
                    <textarea name="customer_note" rows="2" maxlength="1000" placeholder="Catatan untuk penjual — mis. patokan alamat, jadwal pengiriman."
                              class="form-input">{{ old('customer_note') }}</textarea>
                </section>

                {{-- Payment --}}
                <section class="card p-4">
                    <h2 class="mb-3 font-semibold text-gray-800">5. Metode Pembayaran</h2>
                    <div class="space-y-2">
                        @foreach ($paymentMethods as $i => $method)
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 p-3 text-sm hover:border-brand-400">
                                <input type="radio" name="payment_method" value="{{ $method->code() }}" @checked($i === 0) required>
                                <span class="font-medium text-gray-800">{{ $method->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                </section>
            </div>

            {{-- Summary --}}
            <div class="lg:sticky lg:top-24 lg:self-start">
                <div class="card space-y-3 p-4">
                    <h2 class="font-semibold text-gray-800">Ringkasan Pesanan</h2>
                    <div class="max-h-52 space-y-2 overflow-y-auto">
                        @foreach ($totals->lines as $line)
                            <div class="flex gap-2 text-sm">
                                <img src="{{ $line->item->product->primaryImageUrl() }}" alt="" class="h-10 w-10 rounded object-cover">
                                <div class="flex-1"><p class="line-clamp-1 text-gray-700">{{ $line->item->product->name }}</p><p class="text-xs text-gray-400">{{ $line->quantity }} × {{ rupiah($line->unitPrice) }}</p></div>
                                <span class="font-medium">{{ rupiah($line->lineTotal) }}</span>
                            </div>
                        @endforeach
                    </div>
                    <dl class="space-y-1 border-t border-gray-100 pt-3 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-500">Subtotal</dt><dd>{{ rupiah($totals->itemsSubtotal) }}</dd></div>
                        @if ($totals->couponDiscount > 0)<div class="flex justify-between text-green-600"><dt>Voucher</dt><dd>−{{ rupiah($totals->couponDiscount) }}</dd></div>@endif
                        <div class="flex justify-between"><dt class="text-gray-500">Ongkir <span class="text-gray-400" x-show="shippingWeight > 0" x-text="'(' + shippingWeight + ' kg)'"></span></dt><dd x-text="shippingConfirmed ? rupiah(shippingCost) : 'Dikonfirmasi'"></dd></div>
                        <div class="flex justify-between" x-show="shippingConfirmed && shippingPacking > 0"><dt class="text-gray-500">Packing kayu</dt><dd x-text="rupiah(shippingPacking)"></dd></div>
                        <div class="flex justify-between" x-show="shippingConfirmed && shippingExtra > 0"><dt class="text-gray-500">Biaya lain</dt><dd x-text="rupiah(shippingExtra)"></dd></div>
                        @if ($taxAdded > 0)<div class="flex justify-between"><dt class="text-gray-500">PPN</dt><dd>{{ rupiah($taxAdded) }}</dd></div>@endif
                    </dl>
                    @if ($taxEmbedded > 0)<p class="text-xs text-gray-400">Harga sudah termasuk PPN {{ rupiah($taxEmbedded) }}.</p>@endif
                    <div class="flex justify-between border-t border-gray-100 pt-3 text-base font-bold">
                        <span>Total</span><span class="text-brand-700" x-text="rupiah(grandTotal)"></span>
                    </div>

                    <label class="flex items-start gap-2 text-xs text-gray-600">
                        <input type="checkbox" name="agree_terms" value="1" required class="mt-0.5 rounded text-brand-600">
                        Saya menyetujui <a href="{{ route('pages.show', 'syarat-ketentuan') }}" class="text-brand-600 underline">syarat &amp; ketentuan</a> dan konfirmasi kondisi produk.
                    </label>

                    {{-- Cargo/freight: ongkir dikonfirmasi admin sebelum bayar. --}}
                    <div x-show="!shippingConfirmed" x-cloak role="alert" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                        Ongkir kargo akan dikonfirmasi admin sebelum pembayaran — total akhir dapat berubah.
                    </div>

                    <button type="submit" class="btn-primary w-full" x-bind:disabled="!shippingService || submitting">
                        <span x-show="!submitting">Buat Pesanan &amp; Bayar</span>
                        <span x-show="submitting">Memproses…</span>
                    </button>
                    <p class="text-center text-xs text-gray-400">Total dihitung ulang di server untuk keamanan.</p>
                    @if ($whatsappEnabled)
                        <a href="{{ whatsapp_link('Halo Rekasurya, saya butuh bantuan untuk menyelesaikan checkout.') }}" target="_blank" rel="noopener" class="block text-center text-xs font-medium text-green-700 hover:underline">Butuh bantuan? Chat WhatsApp</a>
                    @endif
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
function checkout(baseSubtotal, tax) {
    return {
        addressId: @js($selectedAddr?->id ?? ''),
        addr: { province: @js($selectedAddr?->province ?? ''), city: @js($selectedAddr?->city ?? '') },
        shippingOptions: [], loadingShipping: false, shippingError: false,
        shippingProvider: '', shippingService: '', shippingCost: 0, shippingPacking: 0, shippingExtra: 0, shippingConfirmed: true, shippingWeight: 0,
        submitting: false,
        baseSubtotal, tax,
        init() { if (this.addr.province) this.loadShipping(); },
        get shippingTotal() { return this.shippingCost + this.shippingPacking + this.shippingExtra },
        get grandTotal() { return this.baseSubtotal + this.tax + (this.shippingConfirmed ? this.shippingTotal : 0) },
        rupiah(n) { return 'Rp ' + Math.round(n).toLocaleString('id-ID') },
        selectAddress(id, province, city) {
            this.addressId = id;
            this.addr.province = province;
            this.addr.city = city;
            this.shippingProvider = ''; this.shippingService = ''; this.shippingCost = 0; this.shippingPacking = 0; this.shippingExtra = 0;
            this.loadShipping();
        },
        async loadShipping() {
            if (!this.addr.province) return;
            this.loadingShipping = true;
            this.shippingError = false;
            try {
                const res = await fetch('{{ route('checkout.shipping') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                    body: JSON.stringify({ province: this.addr.province, city: this.addr.city }),
                });
                if (!res.ok) throw new Error('shipping');
                this.shippingOptions = await res.json();
            } catch (e) {
                this.shippingOptions = [];
                this.shippingError = true;
            } finally { this.loadingShipping = false; }
        },
        selectShipping(opt) {
            this.shippingProvider = opt.provider_code;
            this.shippingService = opt.service_code;
            this.shippingCost = opt.cost;                                  // ongkir kurir saja
            this.shippingPacking = opt.packing_fee;                        // packing kayu (dipisah)
            this.shippingExtra = opt.handling_fee + opt.insurance_fee;     // biaya lain (biasanya 0)
            this.shippingConfirmed = opt.confirmed;
            this.shippingWeight = (opt.billable_weight_grams || 0) / 1000;
        },
    }
}
</script>
@endpush
