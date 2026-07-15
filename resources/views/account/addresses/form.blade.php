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
                      }">
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
