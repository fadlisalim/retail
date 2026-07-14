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
                    @guest
                        <p class="mt-2 text-xs text-gray-400">Sudah punya akun? <a href="{{ route('login') }}" class="text-brand-600 underline">Masuk</a> untuk checkout lebih cepat.</p>
                    @endguest
                </section>

                {{-- Address --}}
                <section class="card p-4">
                    <h2 class="mb-3 font-semibold text-gray-800">2. Alamat Pengiriman</h2>
                    @if ($addresses->isNotEmpty())
                        <div class="mb-3 space-y-2">
                            @foreach ($addresses as $addr)
                                <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-gray-200 p-2 text-sm hover:border-brand-400">
                                    <input type="radio" name="saved_address" class="mt-1"
                                           @change="fillAddress({{ Illuminate\Support\Js::from([
                                               'recipient_name' => $addr->recipient_name, 'recipient_phone' => $addr->phone,
                                               'company_name' => $addr->company_name, 'npwp' => $addr->npwp,
                                               'province' => $addr->province, 'city' => $addr->city, 'district' => $addr->district,
                                               'subdistrict' => $addr->subdistrict, 'postal_code' => $addr->postal_code,
                                               'address_line' => $addr->address_line, 'landmark' => $addr->landmark,
                                           ]) }})">
                                    <span><strong>{{ $addr->label }}</strong> — {{ $addr->recipient_name }}<br><span class="text-gray-500">{{ $addr->fullAddress() }}</span></span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-form.input name="recipient_name" label="Nama Penerima" required x-model="addr.recipient_name" />
                        <x-form.input name="recipient_phone" label="No. Telepon Penerima" x-model="addr.recipient_phone" />
                        <x-form.input name="company_name" label="Nama Perusahaan (opsional)" x-model="addr.company_name" />
                        <x-form.input name="npwp" label="NPWP (opsional)" x-model="addr.npwp" />
                        <div>
                            <label class="input-label" for="province">Provinsi <span class="text-red-500">*</span></label>
                            <select id="province" name="province" required x-model="addr.province" @change="onProvinceChange" class="form-select">
                                <option value="">— Pilih provinsi —</option>
                                <template x-for="prov in provinceList" :key="prov"><option :value="prov" x-text="prov"></option></template>
                            </select>
                        </div>
                        <div>
                            <label class="input-label" for="city">Kota/Kabupaten <span class="text-red-500">*</span></label>
                            <select id="city" name="city" required x-model="addr.city" @change="loadShipping" class="form-select" :disabled="!addr.province">
                                <option value="">— Pilih kota/kabupaten —</option>
                                <template x-for="c in availableCities" :key="c"><option :value="c" x-text="c"></option></template>
                            </select>
                            <p class="mt-1 text-xs text-gray-400" x-show="!addr.province">Pilih provinsi dulu untuk melihat daftar kota.</p>
                        </div>
                        <x-form.input name="district" label="Kecamatan" x-model="addr.district" />
                        <x-form.input name="subdistrict" label="Kelurahan" x-model="addr.subdistrict" />
                        <x-form.input name="postal_code" label="Kode Pos" x-model="addr.postal_code" />
                    </div>
                    <div class="mt-3">
                        <x-form.textarea name="address_line" label="Alamat Lengkap" required rows="2" x-model="addr.address_line" />
                    </div>
                    <div class="mt-3">
                        <x-form.input name="landmark" label="Patokan (opsional)" x-model="addr.landmark" />
                    </div>
                </section>

                {{-- Shipping --}}
                <section class="card p-4">
                    <h2 class="mb-3 font-semibold text-gray-800">3. Pengiriman</h2>
                    <p x-show="!shippingOptions.length" class="text-sm text-gray-400">Isi provinsi untuk melihat opsi pengiriman.</p>
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
        addr: { recipient_name: @js(auth()->user()?->name), recipient_phone: '', company_name: '', npwp: '', province: '', city: '', district: '', subdistrict: '', postal_code: '', address_line: '', landmark: '' },
        shippingOptions: [], loadingShipping: false,
        shippingProvider: '', shippingService: '', shippingCost: 0, shippingConfirmed: true, shippingWeight: 0,
        submitting: false,
        baseSubtotal, tax,
        citiesByProvince: @js($citiesByProvince),
        get provinceList() { return Object.keys(this.citiesByProvince) },
        get availableCities() { return this.citiesByProvince[this.addr.province] || [] },
        onProvinceChange() {
            // Drop a city that doesn't belong to the newly chosen province, then re-quote.
            if (! this.availableCities.includes(this.addr.city)) this.addr.city = '';
            this.loadShipping();
        },
        get grandTotal() { return this.baseSubtotal + this.tax + (this.shippingConfirmed ? this.shippingCost : 0) },
        rupiah(n) { return 'Rp ' + Math.round(n).toLocaleString('id-ID') },
        fillAddress(a) { this.addr = Object.assign(this.addr, a); this.loadShipping() },
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
            this.shippingCost = opt.cost + opt.packing_fee + opt.handling_fee + opt.insurance_fee;
            this.shippingConfirmed = opt.confirmed;
            this.shippingWeight = (opt.billable_weight_grams || 0) / 1000;
        },
    }
}
</script>
@endpush
