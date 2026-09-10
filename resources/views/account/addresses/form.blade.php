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
                      x-data="{
                          citiesByProvince: {{ Illuminate\Support\Js::from($citiesByProvince) }},
                          province: @js(old('province', $address->province)),
                          city: @js(old('city', $address->city)),
                          get provinceList() { return Object.keys(this.citiesByProvince) },
                          get availableCities() { return this.citiesByProvince[this.province] || [] },
                          onProvinceChange() { if (!this.availableCities.includes(this.city)) this.city = '' },

                          // Pencarian kelurahan (RajaOngkir) — mengisi kecamatan/kelurahan/kode pos
                          // + ID tujuan untuk ongkir kurir reguler; provinsi & kota ikut dicocokkan.
                          searchUrl: @js(route('shipping.destinations')),
                          q: '', results: [], searching: false,
                          destinationId: @js(old('courier_destination_id', $address->courier_destination_id)),
                          picked: @js(old('courier_destination_label', $address->courier_destination_label)),
                          normalize(s) { return String(s || '').toUpperCase().replace(/^(KOTA|KAB\.?|KABUPATEN)\s+/, '').trim() },
                          async search() {
                              if (this.q.trim().length < 3) { this.results = []; return; }
                              this.searching = true;
                              try {
                                  const res = await fetch(this.searchUrl + '?q=' + encodeURIComponent(this.q.trim()), { headers: { 'Accept': 'application/json' } });
                                  this.results = res.ok ? await res.json() : [];
                              } catch (e) { this.results = []; } finally { this.searching = false; }
                          },
                          pick(r) {
                              this.destinationId = r.id; this.picked = r.label; this.results = []; this.q = '';
                              const prov = this.provinceList.find(p => p.toUpperCase() === this.normalize(r.province));
                              if (prov) {
                                  this.province = prov;
                                  const want = this.normalize(r.city);
                                  const found = this.availableCities.find(c => this.normalize(c) === want)
                                      || this.availableCities.find(c => want.includes(this.normalize(c)) || this.normalize(c).includes(want));
                                  if (found) this.city = found;
                              }
                              for (const [name, val] of [['district', r.district], ['subdistrict', r.subdistrict], ['postal_code', r.postal_code]]) {
                                  const el = this.$root.querySelector('[name=' + name + ']');
                                  if (el && val) el.value = val;
                              }
                          },
                          clearPick() { this.destinationId = ''; this.picked = ''; },
                      }">
                    @csrf
                    @if ($address->exists)
                        @method('PUT')
                    @endif

                    @if ($courierSearchEnabled)
                        <div class="relative sm:col-span-2 rounded-lg border border-brand-100 bg-brand-50/40 p-3">
                            <label class="input-label" for="destination_search">Cari kecamatan / kelurahan <span class="font-normal text-gray-400">(untuk ongkir JNE, J&amp;T, SiCepat, dll.)</span></label>
                            <input type="search" id="destination_search" x-model="q" @input.debounce.400ms="search()" @keydown.enter.prevent="search()"
                                   placeholder="Ketik nama kecamatan atau kelurahan, mis. Coblong" class="form-input" autocomplete="off">
                            <p class="mt-1 text-xs text-gray-500" x-show="!picked">Pilih dari hasil pencarian — kecamatan, kelurahan, kode pos, provinsi &amp; kota terisi otomatis.</p>
                            <p class="mt-1 text-xs text-gray-400" x-show="searching" x-cloak>Mencari…</p>
                            <ul x-show="results.length" x-cloak @click.outside="results = []"
                                class="absolute left-3 right-3 z-20 mt-1 max-h-64 overflow-auto rounded-lg border border-gray-200 bg-white text-sm shadow-lg">
                                <template x-for="r in results" :key="r.id">
                                    <li><button type="button" @click="pick(r)" class="block w-full px-3 py-2 text-left hover:bg-brand-50" x-text="r.label"></button></li>
                                </template>
                            </ul>
                            <p x-show="picked" x-cloak class="mt-2 flex flex-wrap items-center gap-2 text-xs text-green-700">
                                <span>✓ Tujuan kurir: <strong x-text="picked"></strong></span>
                                <button type="button" @click="clearPick()" class="text-gray-400 underline hover:text-red-600">hapus</button>
                            </p>
                            <input type="hidden" name="courier_destination_id" :value="destinationId || ''">
                            <input type="hidden" name="courier_destination_label" :value="picked || ''">
                        </div>
                    @endif

                    <x-form.select name="label" label="Jenis Alamat" required
                        :options="['Rumah' => 'Rumah', 'Kantor' => 'Kantor', 'Gudang' => 'Gudang', 'Proyek' => 'Proyek']"
                        :selected="$address->label" placeholder="Pilih jenis" />
                    <x-form.input name="recipient_name" label="Nama Penerima" :value="$address->recipient_name" required />

                    <x-form.input name="phone" label="Nomor Telepon" type="tel" :value="$address->phone" required />
                    <x-form.input name="company_name" label="Nama Perusahaan" :value="$address->company_name" />

                    <x-form.input name="npwp" label="NPWP" :value="$address->npwp" />

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
                    <x-form.input name="postal_code" label="Kode Pos" :value="$address->postal_code" />

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
