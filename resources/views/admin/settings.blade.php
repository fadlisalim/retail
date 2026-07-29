@extends('layouts.admin')

@section('title', 'Pengaturan')

@section('content')
    <x-admin.page-header title="Pengaturan" subtitle="Konfigurasi toko yang dapat diubah kapan saja" />

    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Company --}}
        <div class="card p-5">
            <h2 class="mb-4 font-semibold text-gray-900">Informasi Perusahaan</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="company_legal_name" class="input-label">Nama Legal</label>
                    <input type="text" name="company_legal_name" id="company_legal_name" value="{{ old('company_legal_name', $settings['company.legal_name']) }}" class="form-input">
                    @error('company_legal_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="company_npwp" class="input-label">NPWP</label>
                    <input type="text" name="company_npwp" id="company_npwp" value="{{ old('company_npwp', $settings['company.npwp']) }}" class="form-input">
                    @error('company_npwp')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="company_phone" class="input-label">Telepon</label>
                    <input type="text" name="company_phone" id="company_phone" value="{{ old('company_phone', $settings['company.phone']) }}" class="form-input">
                    @error('company_phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="company_email" class="input-label">Email</label>
                    <input type="email" name="company_email" id="company_email" value="{{ old('company_email', $settings['company.email']) }}" class="form-input">
                    @error('company_email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="company_address" class="input-label">Alamat</label>
                    <textarea name="company_address" id="company_address" rows="2" class="form-textarea">{{ old('company_address', $settings['company.address']) }}</textarea>
                    @error('company_address')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        {{-- Tax --}}
        <div class="card p-5">
            <h2 class="mb-4 font-semibold text-gray-900">Pajak (PPN)</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="tax_ppn_percent" class="input-label">Persentase PPN (%) <span class="text-red-500">*</span></label>
                    <input type="number" step="1" min="0" max="100" name="tax_ppn_percent" id="tax_ppn_percent" value="{{ old('tax_ppn_percent', $settings['tax.ppn_percent']) }}" required class="form-input">
                    @error('tax_ppn_percent')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="hidden" name="tax_enabled" value="0">
                        <input type="checkbox" name="tax_enabled" value="1" @checked(old('tax_enabled', $settings['tax.enabled'])) class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                        <span>Aktifkan PPN</span>
                    </label>
                </div>
            </div>
        </div>

        {{-- WhatsApp --}}
        <div class="card p-5">
            <h2 class="mb-4 font-semibold text-gray-900">WhatsApp</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="whatsapp_number" class="input-label">Nomor (format internasional)</label>
                    <input type="text" name="whatsapp_number" id="whatsapp_number" value="{{ old('whatsapp_number', $settings['whatsapp.number']) }}" placeholder="628123456789" class="form-input">
                    @error('whatsapp_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="hidden" name="whatsapp_enabled" value="0">
                        <input type="checkbox" name="whatsapp_enabled" value="1" @checked(old('whatsapp_enabled', $settings['whatsapp.enabled'])) class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                        <span>Tampilkan tombol WhatsApp</span>
                    </label>
                </div>
                <div class="sm:col-span-2">
                    <label for="whatsapp_greeting" class="input-label">Pesan Sapaan</label>
                    <textarea name="whatsapp_greeting" id="whatsapp_greeting" rows="2" class="form-textarea">{{ old('whatsapp_greeting', $settings['whatsapp.greeting']) }}</textarea>
                    @error('whatsapp_greeting')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="whatsapp_admin_notify" class="input-label">Nomor WA Notifikasi Admin (Chat Toko)</label>
                    <input type="text" name="whatsapp_admin_notify" id="whatsapp_admin_notify" value="{{ old('whatsapp_admin_notify', $settings['whatsapp.admin_notify']) }}" placeholder="6281xxxxxxxx" class="form-input">
                    <p class="mt-1 text-xs text-gray-500">Chat Toko baru dari website dikirim sebagai notifikasi WA ke nomor ini (via Wablas). Kosongkan untuk mematikan. Jangan isi dengan nomor device Wablas itu sendiri — pesan ke nomor sendiri tidak terkirim.</p>
                    @error('whatsapp_admin_notify')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        {{-- Payment --}}
        <div class="card p-5">
            <h2 class="mb-4 font-semibold text-gray-900">Pembayaran</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="payment_bank_account" class="input-label">Rekening Bank</label>
                    <textarea name="payment_bank_account" id="payment_bank_account" rows="3" placeholder="BNI 3355113351 a.n. REKASURYA CIPTA DAYA CV&#10;BRI 040701000954302 a.n. REKASURYA CIPTA DAYA CV" class="form-textarea">{{ old('payment_bank_account', $settings['payment.bank_account']) }}</textarea>
                    <p class="mt-1 text-xs text-gray-400">Boleh lebih dari satu rekening — tulis satu rekening per baris.</p>
                    @error('payment_bank_account')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="payment_qris_image" class="input-label">Gambar QRIS (opsional)</label>
                    <input type="file" name="payment_qris_image" id="payment_qris_image" accept="image/png,image/jpeg" class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-brand-700">
                    <p class="mt-1 text-xs text-gray-400">Unggah gambar QRIS statis toko (JPG/PNG). Akan ditampilkan di halaman pembayaran.</p>
                    @error('payment_qris_image')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                @if (!empty($settings['payment.qris_image']))
                    <div>
                        <span class="input-label">QRIS saat ini</span>
                        <img src="{{ asset('storage/'.$settings['payment.qris_image']) }}" alt="QRIS toko" class="h-32 w-32 rounded-lg border border-gray-200 object-contain">
                    </div>
                @endif
            </div>
        </div>

        {{-- Ambil di Gudang (pickup) --}}
        <div class="card p-5">
            <h2 class="mb-4 font-semibold text-gray-900">Ambil di Gudang</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="pickup_address" class="input-label">Alamat Gudang</label>
                    <textarea name="pickup_address" id="pickup_address" rows="2" class="form-textarea">{{ old('pickup_address', $settings['pickup.address']) }}</textarea>
                    <p class="mt-1 text-xs text-gray-400">Ditampilkan di checkout pada opsi "Ambil di Gudang Rekasurya".</p>
                    @error('pickup_address')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="pickup_maps_url" class="input-label">Link Google Maps</label>
                    <input type="url" name="pickup_maps_url" id="pickup_maps_url" value="{{ old('pickup_maps_url', $settings['pickup.maps_url']) }}" placeholder="https://www.google.com/maps/..." class="form-input">
                    @error('pickup_maps_url')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn-primary">Simpan Pengaturan</button>
        </div>
    </form>
@endsection
