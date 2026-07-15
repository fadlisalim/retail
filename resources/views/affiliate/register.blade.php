@extends('layouts.storefront')

@section('title', 'Daftar Afiliasi — '.config('rekasurya.company.brand_name'))
@section('noindex', 'noindex')

@section('content')
    <x-breadcrumbs :items="[['label' => 'Akun', 'url' => route('account.dashboard')], ['label' => 'Daftar Afiliasi']]" />

    <div class="grid gap-6 lg:grid-cols-[240px_1fr]">
        @include('partials.account-nav')

        <div class="min-w-0 space-y-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900 sm:text-2xl">Daftar Program Afiliasi</h1>
                <p class="mt-1 text-sm text-gray-500">Lengkapi data berikut. Akun afiliasi aktif setelah diverifikasi admin.</p>
            </div>

            <form action="{{ route('account.affiliate.store') }}" method="POST" class="space-y-5">
                @csrf
                <div class="card space-y-4 p-5">
                    <h2 class="font-semibold text-gray-900">Data Diri</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-form.input name="full_name" label="Nama Lengkap" :value="old('full_name', $user->name)" required />
                        <x-form.input name="id_number" label="NIK (KTP)" :value="old('id_number')" required />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-form.input name="phone" label="No. HP / WhatsApp" :value="old('phone', $user->phone ?? '')" required />
                        <x-form.input name="npwp" label="NPWP (opsional)" :value="old('npwp')" />
                    </div>
                    <x-form.textarea name="address" label="Alamat" :value="old('address')" rows="2" required />
                    <x-form.input name="channel" label="Channel Promosi (opsional)" :value="old('channel')" placeholder="mis. Instagram @akun, komunitas, website" />
                </div>

                <div class="card space-y-4 p-5">
                    <h2 class="font-semibold text-gray-900">Rekening Pencairan</h2>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-form.input name="bank_name" label="Nama Bank" :value="old('bank_name')" required />
                        <x-form.input name="bank_account_number" label="No. Rekening" :value="old('bank_account_number')" required />
                        <x-form.input name="bank_account_holder" label="Atas Nama" :value="old('bank_account_holder', $user->name)" required />
                    </div>
                </div>

                <label class="flex items-start gap-2 text-sm text-gray-600">
                    <input type="checkbox" name="agree" value="1" class="mt-0.5 rounded border-gray-300" required>
                    <span>Saya menyetujui <a href="{{ route('affiliate.landing') }}" class="text-brand-600 hover:underline" target="_blank">ketentuan program afiliasi</a> dan menyatakan data di atas benar.</span>
                </label>
                @error('agree')<p class="text-xs text-red-600">{{ $message }}</p>@enderror

                <div class="flex gap-2">
                    <button type="submit" class="btn-primary">Kirim Pendaftaran</button>
                    <a href="{{ route('account.dashboard') }}" class="btn-outline">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
