@extends('layouts.storefront')
@section('title', 'Checkout')
@section('noindex', 'noindex')

@section('content')
    <h1 class="mb-4 text-xl font-bold text-gray-900 sm:text-2xl">Checkout</h1>

    <form action="{{ route('checkout.store') }}" method="POST"
          x-data="checkout({{ $totals->itemsSubtotal - $totals->couponDiscount }}, {{ $totals->taxAmount }})">
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
                                    <input type="radio" name="address_choice" class="mt-1" value="{{ $addr->id }}" @checked($addr->is_default)
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
                    <p x-show="!shippingOptions.length" class="text-sm text-gray-400">Pilih alamat pengiriman untuk melihat opsi &amp; ongkir.</p>
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
                                    </span>
                                </span>
                                <span class="font-semibold" x-text="opt.confirmed ? opt.rupiah : 'Dikonfirmasi'"></span>
                            </label>
                        </template>
                    </div>
                    <p x-show="loadingShipping" class="text-sm text-gray-400">Menghitung ongkir…</p>
                </section>

                {{-- Payment --}}
                <section class="card p-4">
                    <h2 class="mb-3 font-semibold text-gray-800">4. Metode Pembayaran</h2>
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
                        @if ($totals->taxAmount > 0)<div class="flex justify-between"><dt class="text-gray-500">PPN</dt><dd>{{ rupiah($totals->taxAmount) }}</dd></div>@endif
                    </dl>
                    <div class="flex justify-between border-t border-gray-100 pt-3 text-base font-bold">
                        <span>Total</span><span class="text-brand-700" x-text="rupiah(grandTotal)"></span>
                    </div>

                    <label class="flex items-start gap-2 text-xs text-gray-600">
                        <input type="checkbox" name="agree_terms" value="1" required class="mt-0.5 rounded text-brand-600">
                        Saya menyetujui <a href="{{ route('pages.show', 'syarat-ketentuan') }}" class="text-brand-600 underline">syarat &amp; ketentuan</a> dan konfirmasi kondisi produk.
                    </label>

                    <button type="submit" class="btn-primary w-full" x-bind:disabled="!shippingService" @click="submitting = true">
                        <span x-show="!submitting">Buat Pesanan &amp; Bayar</span>
                        <span x-show="submitting">Memproses…</span>
                    </button>
                    <p class="text-center text-xs text-gray-400">Total dihitung ulang di server untuk keamanan.</p>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
function checkout(baseSubtotal, tax) {
    return {
        addressId: @js($defaultAddress?->id ?? ''),
        addr: { province: @js($defaultAddress?->province ?? ''), city: @js($defaultAddress?->city ?? '') },
        shippingOptions: [], loadingShipping: false,
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
            try {
                const res = await fetch('{{ route('checkout.shipping') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                    body: JSON.stringify({ province: this.addr.province, city: this.addr.city }),
                });
                this.shippingOptions = res.ok ? await res.json() : [];
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
