@extends('layouts.storefront')

@section('title', ($address->exists ? 'Ubah Alamat' : 'Tambah Alamat').' — '.config('rekasurya.company.brand_name'))
@section('noindex', 'noindex')

@section('content')
    <x-breadcrumbs :items="[
        ['label' => 'Akun', 'url' => route('account.dashboard')],
        ['label' => 'Alamat', 'url' => route('account.addresses.index')],
        ['label' => $address->exists ? 'Ubah' : 'Tambah'],
    ]" />

    <div class="grid gap-6 lg:grid-cols-[240px_1fr]">
        @include('partials.account-nav')

        <div class="min-w-0 space-y-6">
            <h1 class="text-xl font-bold text-gray-800">{{ $address->exists ? 'Ubah Alamat' : 'Tambah Alamat' }}</h1>

            <section class="card p-6">
                <form action="{{ $address->exists ? route('account.addresses.update', $address) : route('account.addresses.store') }}" method="POST" class="grid gap-4 sm:grid-cols-2"
                      x-data="addressForm({
                          enabled: @js($courierSearchEnabled),
                          citiesByProvince: {{ Illuminate\Support\Js::from($citiesByProvince) }},
                          province: @js(old('province', $address->province)),
                          city: @js(old('city', $address->city)),
                          regionsUrl: @js(route('shipping.regions')),
                          searchUrl: @js(route('shipping.destinations')),
                          provinces: {{ Illuminate\Support\Js::from($provinces) }},
                          selected: {
                              province: @js(old('province_id', $selectedRegions['province'])),
                              city: @js(old('city_id', $selectedRegions['city'])),
                              district: @js(old('district_id', $selectedRegions['district'])),
                              subdistrict: @js(old('subdistrict_id', $selectedRegions['subdistrict'])),
                          },
                          picked: @js($address->courier_destination_label),
                      })">
                    @csrf
                    @if ($address->exists)
                        @method('PUT')
                    @endif

                    <x-form.select name="label" label="Jenis Alamat" required
                        :options="['Rumah' => 'Rumah', 'Kantor' => 'Kantor', 'Gudang' => 'Gudang', 'Proyek' => 'Proyek']"
                        :selected="$address->label" placeholder="Pilih jenis" />
                    <x-form.input name="recipient_name" label="Nama Penerima" :value="$address->recipient_name" required />

                    <x-form.input name="phone" label="Nomor Telepon" type="tel" :value="$address->phone" required />
                    <x-form.input name="company_name" label="Nama Perusahaan" :value="$address->company_name" />

                    <x-form.input name="npwp" label="NPWP" :value="$address->npwp" />
                    <div class="hidden sm:block"></div>

                    @if ($courierSearchEnabled)
                        {{-- Wilayah bertingkat dari tabel regions (sinkron RajaOngkir): dasar ongkir kurir. --}}
                        <div class="relative sm:col-span-2 rounded-lg border border-brand-100 bg-brand-50/40 p-3">
                            <label class="input-label" for="destination_search">Cari cepat kelurahan / kecamatan <span class="font-normal text-gray-400">(opsional — atau pilih bertingkat di bawah)</span></label>
                            <input type="search" id="destination_search" x-model="q" @input.debounce.400ms="search()" @keydown.enter.prevent="search()"
                                   placeholder="Ketik nama kelurahan atau kecamatan, mis. Coblong" class="form-input" autocomplete="off">
                            <p class="mt-1 text-xs text-gray-400" x-show="searching" x-cloak>Mencari…</p>
                            <ul x-show="results.length" x-cloak @click.outside="results = []"
                                class="absolute left-3 right-3 z-20 mt-1 max-h-64 overflow-auto rounded-lg border border-gray-200 bg-white text-sm shadow-lg">
                                <template x-for="r in results" :key="r.id">
                                    <li><button type="button" @click="pick(r)" class="block w-full px-3 py-2 text-left hover:bg-brand-50" x-text="r.label"></button></li>
                                </template>
                            </ul>
                            <p x-show="pickError" x-cloak class="mt-1 text-xs text-amber-700" x-text="pickError"></p>
                            <p x-show="picked && !pickError" x-cloak class="mt-2 text-xs text-green-700">✓ Tujuan kurir: <strong x-text="picked"></strong></p>
                        </div>

                        <div>
                            <label class="input-label" for="province_id">Provinsi <span class="text-red-500">*</span></label>
                            <select id="province_id" name="province_id" x-model="provinceId" @change="onProvince()" required class="form-select">
                                <option value="">— Pilih provinsi —</option>
                                <template x-for="p in provinces" :key="p.id"><option :value="p.id" x-text="p.name" :selected="p.id == provinceId"></option></template>
                            </select>
                            @error('province_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="input-label" for="city_id">Kota / Kabupaten <span class="text-red-500">*</span></label>
                            <select id="city_id" name="city_id" x-model="cityId" @change="onCity()" required class="form-select" :disabled="!provinceId || loading === provinceId">
                                <option value="" x-text="loading === provinceId ? 'Memuat…' : '— Pilih kota/kabupaten —'"></option>
                                <template x-for="c in cities" :key="c.id"><option :value="c.id" x-text="c.name" :selected="c.id == cityId"></option></template>
                            </select>
                            @error('city_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="input-label" for="district_id">Kecamatan <span class="text-red-500">*</span></label>
                            <select id="district_id" name="district_id" x-model="districtId" @change="onDistrict()" required class="form-select" :disabled="!cityId || loading === cityId">
                                <option value="" x-text="loading === cityId ? 'Memuat…' : '— Pilih kecamatan —'"></option>
                                <template x-for="d in districts" :key="d.id"><option :value="d.id" x-text="d.name" :selected="d.id == districtId"></option></template>
                            </select>
                            <p class="mt-1 text-xs text-red-600" x-show="cityId && loading !== cityId && districts.length === 0" x-cloak>Daftar kecamatan belum bisa dimuat — <button type="button" class="underline" @click="onCity()">coba lagi</button>.</p>
                            @error('district_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="input-label" for="subdistrict_id">Kelurahan / Desa <span class="text-red-500">*</span></label>
                            <select id="subdistrict_id" name="subdistrict_id" x-model="subdistrictId" @change="onSubdistrict()" required class="form-select" :disabled="!districtId || loading === districtId">
                                <option value="" x-text="loading === districtId ? 'Memuat…' : '— Pilih kelurahan/desa —'"></option>
                                <template x-for="s in subdistricts" :key="s.id"><option :value="s.id" x-text="s.name + (s.postal_code ? ' (' + s.postal_code + ')' : '')" :selected="s.id == subdistrictId"></option></template>
                            </select>
                            @error('subdistrict_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                    @else
                        {{-- Provinsi & Kota mengacu data Indah Cargo agar ongkir bisa dihitung. --}}
                        <div>
                            <label class="input-label" for="province">Provinsi <span class="text-red-500">*</span></label>
                            <select id="province" name="province" required x-model="province" @change="onProvinceChange" class="form-select">
                                <option value="">— Pilih provinsi —</option>
                                <template x-for="prov in provinceList" :key="prov"><option :value="prov" x-text="prov"></option></template>
                            </select>
                            @error('province')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="input-label" for="city">Kota / Kabupaten <span class="text-red-500">*</span></label>
                            <select id="city" name="city" required x-model="city" class="form-select" :disabled="!province">
                                <option value="">— Pilih kota/kabupaten —</option>
                                <template x-for="c in availableCities" :key="c"><option :value="c" x-text="c"></option></template>
                            </select>
                            <p class="mt-1 text-xs text-gray-400" x-show="!province">Pilih provinsi dulu.</p>
                            @error('city')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <x-form.input name="district" label="Kecamatan" :value="$address->district" />
                        <x-form.input name="subdistrict" label="Kelurahan / Desa" :value="$address->subdistrict" />
                    @endif

                    <x-form.input name="postal_code" label="Kode Pos" :value="$address->postal_code" :hint="$courierSearchEnabled ? 'Terisi otomatis dari kelurahan yang dipilih.' : null" />

                    <div class="sm:col-span-2">
                        <x-form.textarea name="address_line" label="Alamat Lengkap" :value="$address->address_line" required rows="3" hint="Nama jalan, nomor rumah/gedung, RT/RW." />
                    </div>

                    <div class="sm:col-span-2">
                        <x-form.input name="landmark" label="Patokan (opsional)" :value="$address->landmark" hint="Contoh: seberang SPBU, dekat pasar." />
                    </div>

                    <div class="sm:col-span-2">
                        <x-form.checkbox name="is_default" label="Jadikan alamat utama" :checked="$address->is_default" />
                    </div>

                    <div class="flex items-center gap-3 sm:col-span-2">
                        <button type="submit" class="btn-primary">{{ $address->exists ? 'Simpan Perubahan' : 'Simpan Alamat' }}</button>
                        <a href="{{ route('account.addresses.index') }}" class="btn-outline">Batal</a>
                    </div>
                </form>
            </section>
        </div>
    </div>
@endsection

@push('scripts')
<script>
function addressForm(cfg) {
    const norm = (s) => String(s || '').toUpperCase().replace(/^(KOTA|KAB\.?|KABUPATEN)\s+/, '').trim();
    const byName = (list, name) => list.find((x) => norm(x.name) === norm(name))
        || list.find((x) => norm(name).includes(norm(x.name)) || norm(x.name).includes(norm(name)));

    return {
        enabled: cfg.enabled,

        // Mode Indah (integrasi kurir nonaktif): provinsi/kota dari tabel tarif.
        citiesByProvince: cfg.citiesByProvince, province: cfg.province || '', city: cfg.city || '',
        get provinceList() { return Object.keys(this.citiesByProvince) },
        get availableCities() { return this.citiesByProvince[this.province] || [] },
        onProvinceChange() { if (!this.availableCities.includes(this.city)) this.city = '' },

        // Mode wilayah RajaOngkir: dropdown bertingkat, tiap tingkat dimuat dari server
        // (tabel regions; diambil dari API sekali per induk lalu permanen).
        regionsUrl: cfg.regionsUrl, searchUrl: cfg.searchUrl,
        provinces: cfg.provinces || [], cities: [], districts: [], subdistricts: [],
        provinceId: cfg.selected.province || '', cityId: cfg.selected.city || '',
        districtId: cfg.selected.district || '', subdistrictId: cfg.selected.subdistrict || '',
        loading: '',
        q: '', results: [], searching: false, picked: cfg.picked || '', pickError: '',

        async init() {
            if (!this.enabled) return;
            // Form ubah / validasi gagal: muat rantai yang sudah terpilih.
            if (this.provinceId) this.cities = await this.fetchRegions(this.provinceId);
            if (this.cityId) this.districts = await this.fetchRegions(this.cityId);
            if (this.districtId) this.subdistricts = await this.fetchRegions(this.districtId);
        },
        async fetchRegions(parentId) {
            this.loading = parentId;
            try {
                const res = await fetch(this.regionsUrl + '?parent=' + encodeURIComponent(parentId), { headers: { 'Accept': 'application/json' } });
                return res.ok ? await res.json() : [];
            } catch (e) { return []; } finally { this.loading = ''; }
        },
        async onProvince() {
            this.cityId = ''; this.districtId = ''; this.subdistrictId = ''; this.picked = '';
            this.cities = []; this.districts = []; this.subdistricts = [];
            if (this.provinceId) this.cities = await this.fetchRegions(this.provinceId);
        },
        async onCity() {
            this.districtId = ''; this.subdistrictId = ''; this.picked = '';
            this.districts = []; this.subdistricts = [];
            if (this.cityId) this.districts = await this.fetchRegions(this.cityId);
        },
        async onDistrict() {
            this.subdistrictId = ''; this.picked = ''; this.subdistricts = [];
            if (this.districtId) this.subdistricts = await this.fetchRegions(this.districtId);
        },
        onSubdistrict() {
            const s = this.subdistricts.find((x) => String(x.id) === String(this.subdistrictId));
            if (!s) { this.picked = ''; return; }
            const postal = document.querySelector('form [name=postal_code]');
            if (postal && s.postal_code) postal.value = s.postal_code;
            const nameOf = (list, id) => (list.find((x) => String(x.id) === String(id)) || {}).name || '';
            this.picked = [s.name, nameOf(this.districts, this.districtId), nameOf(this.cities, this.cityId), nameOf(this.provinces, this.provinceId), s.postal_code].filter(Boolean).join(', ');
        },
        async search() {
            if (this.q.trim().length < 3) { this.results = []; return; }
            this.searching = true;
            try {
                const res = await fetch(this.searchUrl + '?q=' + encodeURIComponent(this.q.trim()), { headers: { 'Accept': 'application/json' } });
                this.results = res.ok ? await res.json() : [];
            } catch (e) { this.results = []; } finally { this.searching = false; }
        },
        // Hasil pencarian → set dropdown bertingkat satu per satu (nama dicocokkan, kelurahan lewat ID RajaOngkir).
        async pick(r) {
            this.results = []; this.q = ''; this.pickError = '';
            const prov = byName(this.provinces, r.province);
            if (!prov) { this.pickError = 'Provinsi "' + r.province + '" tidak ada di daftar — pilih manual di bawah.'; return; }
            this.provinceId = prov.id; await this.onProvince();
            const city = byName(this.cities, r.city);
            if (!city) { this.pickError = 'Kota "' + r.city + '" tidak ditemukan — pilih manual di bawah.'; return; }
            this.cityId = city.id; await this.onCity();
            const d = byName(this.districts, r.district);
            if (!d) { this.pickError = 'Kecamatan "' + r.district + '" tidak ditemukan — pilih manual di bawah.'; return; }
            this.districtId = d.id; await this.onDistrict();
            const s = this.subdistricts.find((x) => String(x.code) === String(r.id)) || byName(this.subdistricts, r.subdistrict);
            if (!s) { this.pickError = 'Kelurahan "' + r.subdistrict + '" tidak ditemukan — pilih manual di bawah.'; return; }
            this.subdistrictId = s.id; this.onSubdistrict();
        },
    }
}
</script>
@endpush
