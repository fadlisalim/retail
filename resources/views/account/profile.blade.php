@extends('layouts.storefront')

@section('title', 'Profil Saya — '.config('rekasurya.company.brand_name'))
@section('noindex', 'noindex')

@section('content')
    <x-breadcrumbs :items="[['label' => 'Akun', 'url' => route('account.dashboard')], ['label' => 'Profil']]" />

    <div class="grid gap-6 lg:grid-cols-[240px_1fr]">
        @include('partials.account-nav')

        <div class="min-w-0 space-y-6">
            <h1 class="text-xl font-bold text-gray-800">Profil Saya</h1>

            {{-- Profile details --}}
            <section class="card p-6">
                <h2 class="mb-4 text-base font-semibold text-gray-800">Data Diri</h2>
                <form action="{{ route('account.profile.update') }}" method="POST" class="grid gap-4 sm:grid-cols-2">
                    @csrf
                    @method('PUT')

                    <x-form.input name="name" label="Nama Lengkap" :value="$user->name" required />
                    <x-form.input name="whatsapp" label="Nomor WhatsApp" type="tel" :value="$user->whatsapp" required hint="Contoh: 08123456789" />
                    <x-form.input name="phone" label="Telepon" type="tel" :value="$user->phone" />
                    <div>
                        <label class="input-label" for="email_display">Email</label>
                        <input id="email_display" type="email" value="{{ $user->email }}" class="form-input bg-gray-50" disabled>
                        <p class="mt-1 text-xs text-gray-400">Email tidak dapat diubah.</p>
                    </div>
                    <x-form.input name="company_name" label="Nama Perusahaan" :value="$user->profile?->company_name" />
                    <x-form.input name="npwp" label="NPWP" :value="$user->profile?->npwp" />

                    <div class="sm:col-span-2">
                        <button type="submit" class="btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </section>

            {{-- Password --}}
            <section class="card p-6">
                <h2 class="mb-4 text-base font-semibold text-gray-800">Ubah Kata Sandi</h2>
                <form action="{{ route('account.password.update') }}" method="POST" class="grid gap-4 sm:grid-cols-2">
                    @csrf
                    @method('PUT')

                    <div class="sm:col-span-2">
                        <x-form.input name="current_password" label="Kata Sandi Saat Ini" type="password" required autocomplete="current-password" />
                    </div>
                    <x-form.input name="password" label="Kata Sandi Baru" type="password" required autocomplete="new-password" hint="Minimal 8 karakter, mengandung huruf dan angka." />
                    <x-form.input name="password_confirmation" label="Konfirmasi Kata Sandi Baru" type="password" required autocomplete="new-password" />

                    <div class="sm:col-span-2">
                        <button type="submit" class="btn-primary">Perbarui Kata Sandi</button>
                    </div>
                </form>
            </section>
        </div>
    </div>
@endsection
