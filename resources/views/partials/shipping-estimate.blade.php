{{-- "Berapa ongkir ke lokasi saya?" — estimasi ongkir semua ekspedisi untuk
     produk ini: dari GPS (reverse geocode → kelurahan) atau pilih wilayah.
     Lokasi diingat di browser supaya produk lain langsung menampilkan ongkir. --}}
<div x-data="ongkirEstimate({
        productId: {{ $product->id }},
        estimateUrl: @js(route('shipping.estimate')),
        regionsUrl: @js(route('shipping.regions')),
        indahUrl: @js(route('shipping.indah-cities')),
     })" class="mt-4 rounded-lg border border-brand-100 bg-brand-50/40 p-3 text-sm">

    <template x-if="!result">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <p class="font-semibold text-gray-800">Berapa ongkir ke lokasi saya?</p>
                <p class="text-xs text-gray-500">Lihat tarif JNE, J&amp;T, Indah Cargo, Buana Raya untuk produk ini.</p>
            </div>
            <button type="button" @click="openModal()" class="btn-primary text-sm">📍 Cek ongkir</button>
        </div>
    </template>
    <template x-if="result">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs text-gray-500">Ongkir ke <span class="font-medium text-gray-700" x-text="result.destination.label"></span></p>
                <p class="font-semibold text-gray-800">
                    <template x-if="cheapest"><span>mulai <span class="text-brand-700" x-text="cheapest.rupiah"></span> <span class="font-normal text-gray-500" x-text="'(' + cheapest.label + ')'"></span></span></template>
                    <template x-if="!cheapest"><span class="font-normal text-amber-700">Ongkir dikonfirmasi admin saat checkout</span></template>
                </p>
            </div>
            {{-- Tombol tetap hijau & sama di semua produk — pelanggan pindah-pindah produk, tetap satu tombol yang dikenali. --}}
            <button type="button" @click="openModal()" class="btn-primary text-sm">📍 Cek ongkir</button>
        </div>
    </template>

    {{-- Modal --}}
    <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center sm:p-4" @keydown.escape.window="open = false">
        <div class="flex max-h-[85vh] w-full max-w-lg flex-col overflow-hidden rounded-t-2xl bg-white shadow-xl sm:rounded-2xl" @click.outside="open = false">
            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                <div>
                    <h3 class="font-semibold text-gray-900">Estimasi Ongkir</h3>
                    <p class="text-xs text-gray-500">{{ Str::limit($product->name, 60) }}</p>
                </div>
                <button type="button" @click="open = false" aria-label="Tutup" class="rounded p-1 text-gray-400 hover:text-gray-700">✕</button>
            </div>

            <div class="space-y-4 overflow-y-auto p-4">
                {{-- Pilih lokasi --}}
                <div x-show="!result || changing">
                    <button type="button" @click="useGps()" :disabled="busy"
                            class="flex w-full items-center justify-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-60">
                        <span>📍</span> <span x-text="busy && busyWhat === 'gps' ? 'Mendeteksi lokasi…' : 'Gunakan lokasi saya (GPS)'"></span>
                    </button>
                    <p class="my-3 text-center text-xs text-gray-400">— atau pilih wilayah —</p>

                    <template x-if="mode === 'regions'">
                        <div class="grid gap-2 sm:grid-cols-2">
                            <select x-model="provinceId" @change="onProvince()" class="form-select text-sm">
                                <option value="">Provinsi</option>
                                <template x-for="p in provinces" :key="p.id"><option :value="p.id" x-text="p.name"></option></template>
                            </select>
                            <select x-model="cityId" @change="onCity()" :disabled="!provinceId" class="form-select text-sm">
                                <option value="" x-text="loading === 'city' ? 'Memuat…' : 'Kota / Kabupaten'"></option>
                                <template x-for="c in cities" :key="c.id"><option :value="c.id" x-text="c.name"></option></template>
                            </select>
                            <select x-model="districtId" @change="onDistrict()" :disabled="!cityId" class="form-select text-sm">
                                <option value="" x-text="loading === 'district' ? 'Memuat…' : 'Kecamatan'"></option>
                                <template x-for="d in districts" :key="d.id"><option :value="d.id" x-text="d.name"></option></template>
                            </select>
                            <select x-model="subdistrictId" @change="onSubdistrict()" :disabled="!districtId" class="form-select text-sm">
                                <option value="" x-text="loading === 'subdistrict' ? 'Memuat…' : 'Kelurahan / Desa'"></option>
                                <template x-for="s in subdistricts" :key="s.id"><option :value="s.id" x-text="s.name"></option></template>
                            </select>
                        </div>
                    </template>
                    <template x-if="mode === 'indah'">
                        <div class="grid gap-2 sm:grid-cols-2">
                            <select x-model="indahProvince" @change="indahCity = ''" class="form-select text-sm">
                                <option value="">Provinsi</option>
                                <template x-for="p in Object.keys(indah)" :key="p"><option :value="p" x-text="p"></option></template>
                            </select>
                            <select x-model="indahCity" @change="onIndahCity()" :disabled="!indahProvince" class="form-select text-sm">
                                <option value="">Kota / Kabupaten</option>
                                <template x-for="c in (indah[indahProvince] || [])" :key="c"><option :value="c" x-text="c"></option></template>
                            </select>
                        </div>
                    </template>
                    <p x-show="mode === ''" class="text-center text-xs text-gray-400">Memuat daftar wilayah…</p>
                </div>

                <p x-show="error" x-cloak class="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700" x-text="error"></p>
                <p x-show="busy && busyWhat === 'estimate'" x-cloak class="text-center text-sm text-gray-400">Menghitung ongkir…</p>

                {{-- Hasil --}}
                <template x-if="result && !changing">
                    <div>
                        <div class="mb-2 flex items-center justify-between gap-2 text-xs text-gray-500">
                            <span>Ke <strong class="text-gray-700" x-text="result.destination.label"></strong> · <span x-text="result.qty + ' pcs, ' + (result.weight_grams / 1000).toFixed(1).replace('.', ',') + ' kg'"></span></span>
                            <button type="button" @click="changing = true" class="font-medium text-brand-600 hover:underline">Ubah lokasi</button>
                        </div>
                        <ul class="divide-y divide-gray-100 rounded-lg border border-gray-200">
                            <template x-for="q in result.quotes" :key="q.provider_code + q.service_code">
                                <li class="flex items-start justify-between gap-3 px-3 py-2.5">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-800" x-text="q.label"></p>
                                        <p class="text-xs text-gray-500">
                                            <span x-show="q.estimated_days" x-text="'Estimasi ' + q.estimated_days"></span>
                                            <span x-show="q.confirmed && q.billable_kg" x-text="' • ' + q.billable_kg + ' kg'"></span>
                                            <span x-show="q.packing_fee > 0" x-text="' • termasuk packing kayu'"></span>
                                        </p>
                                        <p x-show="q.note" class="mt-0.5 text-[11px] text-gray-400" x-text="q.note"></p>
                                    </div>
                                    <p class="flex-none text-right text-sm font-semibold" :class="q.confirmed ? 'text-gray-900' : 'text-amber-700'" x-text="q.confirmed ? q.rupiah : 'Dikonfirmasi'"></p>
                                </li>
                            </template>
                        </ul>
                        <p class="mt-2 text-[11px] text-gray-400">Estimasi berdasarkan berat &amp; dimensi produk ini. Ongkir pasti dihitung ulang saat checkout sesuai alamat lengkap dan isi keranjang.</p>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function ongkirEstimate(cfg) {
    const KEY = 'energiclick.ongkir_dest';
    return {
        open: false, changing: false, busy: false, busyWhat: '', error: '', result: null,
        mode: '', loading: '',
        provinces: [], cities: [], districts: [], subdistricts: [],
        provinceId: '', cityId: '', districtId: '', subdistrictId: '',
        indah: {}, indahProvince: '', indahCity: '',
        get cheapest() {
            const c = (this.result?.quotes || []).filter((q) => q.confirmed && q.type !== 'pickup');
            return c.length ? c[0] : null;
        },
        init() {
            // Lokasi yang pernah diingat → langsung hitung untuk produk ini.
            try {
                const saved = JSON.parse(localStorage.getItem(KEY) || 'null');
                if (saved && saved.city) this.estimate(saved, true);
            } catch (e) {}
        },
        async openModal() {
            this.open = true; this.error = ''; this.changing = !this.result;
            if (this.result) this.estimate(this.result.destination, true); // qty/varian mungkin berubah
            if (!this.mode) await this.loadRegions();
        },
        async loadRegions() {
            try {
                const r = await fetch(this.regionsUrlOf(''), { headers: { 'Accept': 'application/json' } });
                const list = r.ok ? await r.json() : [];
                if (list.length) { this.mode = 'regions'; this.provinces = list; return; }
                const i = await fetch(cfg.indahUrl, { headers: { 'Accept': 'application/json' } });
                this.indah = i.ok ? await i.json() : {}; this.mode = 'indah';
            } catch (e) { this.mode = 'indah'; }
        },
        regionsUrlOf(parent) { return cfg.regionsUrl + (parent ? '?parent=' + encodeURIComponent(parent) : ''); },
        async children(parent, level) {
            this.loading = level;
            try { const r = await fetch(this.regionsUrlOf(parent), { headers: { 'Accept': 'application/json' } }); return r.ok ? await r.json() : []; }
            catch (e) { return []; } finally { this.loading = ''; }
        },
        async onProvince() { this.cityId = ''; this.districtId = ''; this.subdistrictId = ''; this.cities = []; this.districts = []; this.subdistricts = []; if (this.provinceId) this.cities = await this.children(this.provinceId, 'city'); },
        async onCity() { this.districtId = ''; this.subdistrictId = ''; this.districts = []; this.subdistricts = []; if (this.cityId) this.districts = await this.children(this.cityId, 'district'); },
        async onDistrict() { this.subdistrictId = ''; this.subdistricts = []; if (this.districtId) this.subdistricts = await this.children(this.districtId, 'subdistrict'); },
        onSubdistrict() { if (this.subdistrictId) this.estimate({ subdistrict_id: this.subdistrictId }); },
        onIndahCity() { if (this.indahCity) this.estimate({ province: this.indahProvince, city: this.indahCity }); },
        useGps() {
            if (!navigator.geolocation) { this.error = 'Browser tidak mendukung deteksi lokasi — pilih wilayah di bawah.'; return; }
            this.busy = true; this.busyWhat = 'gps'; this.error = '';
            navigator.geolocation.getCurrentPosition(
                (pos) => { this.busy = false; this.estimate({ lat: pos.coords.latitude, lng: pos.coords.longitude }); },
                () => { this.busy = false; this.error = 'Lokasi tidak bisa dideteksi (izin ditolak?). Silakan pilih wilayah di bawah.'; },
                { timeout: 10000, maximumAge: 300000 },
            );
        },
        productPayload() {
            const v = document.querySelector('input[name=variant_id]');
            const q = document.querySelector('input[name=quantity]');
            return { product_id: cfg.productId, variant_id: v && v.value ? v.value : null, qty: q && Number(q.value) > 0 ? Number(q.value) : 1 };
        },
        async estimate(dest, silent = false) {
            this.busy = true; this.busyWhat = 'estimate'; if (!silent) this.error = '';
            try {
                const res = await fetch(cfg.estimateUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ ...this.productPayload(), ...dest }),
                });
                const body = await res.json().catch(() => ({}));
                if (!res.ok) { if (!silent) this.error = body.message || 'Gagal menghitung ongkir.'; return; }
                this.result = body; this.changing = false;
                try { localStorage.setItem(KEY, JSON.stringify(body.destination)); } catch (e) {}
            } catch (e) {
                if (!silent) this.error = 'Gagal menghubungi server. Coba lagi.';
            } finally { this.busy = false; this.busyWhat = ''; }
        },
    };
}
</script>
@endpush
