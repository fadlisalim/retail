@extends('layouts.storefront')

@section('title', 'Permintaan Penawaran (RFQ) — '.config('rekasurya.company.brand_name'))
@section('meta_description', 'Ajukan permintaan penawaran harga (RFQ) untuk kebutuhan proyek energi terbarukan Anda. Tim '.config('rekasurya.company.brand_name').' akan segera menindaklanjuti.')

@php
    $u = auth()->user();
    $seedItems = collect(old('items', $prefill
            ? [['name' => $prefill->name, 'quantity' => 1, 'product_id' => $prefill->id]]
            : [['name' => '', 'quantity' => 1]]))
        ->map(fn ($i) => [
            'name' => $i['name'] ?? '',
            'quantity' => (int) ($i['quantity'] ?? 1),
            'product_id' => $i['product_id'] ?? '',
            'note' => $i['note'] ?? '',
        ])->values()->all();
@endphp

@section('content')
    <x-breadcrumbs :items="[['label' => 'Permintaan Penawaran']]" />

    <div class="mx-auto max-w-3xl">
        <header class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">Permintaan Penawaran (RFQ)</h1>
            <p class="mt-1 text-sm text-gray-500">Lengkapi formulir di bawah ini. Anda dapat melampirkan BOQ atau daftar kebutuhan proyek.</p>
        </header>

        <form action="{{ route('quotations.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            {{-- Contact --}}
            <section class="card p-6">
                <h2 class="mb-4 text-base font-semibold text-gray-800">Informasi Kontak</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.input name="contact_name" label="Nama" :value="$u?->name" required />
                    <x-form.input name="contact_email" label="Email" type="email" :value="$u?->email" required />
                    <x-form.input name="contact_phone" label="Telepon / WhatsApp" type="tel" :value="$u?->whatsapp ?? $u?->phone" />
                    <x-form.input name="company_name" label="Nama Perusahaan" :value="$u?->profile?->company_name" />
                    <x-form.input name="npwp" label="NPWP" :value="$u?->profile?->npwp" />
                </div>
            </section>

            {{-- Project --}}
            <section class="card p-6">
                <h2 class="mb-4 text-base font-semibold text-gray-800">Detail Proyek</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.input name="project_name" label="Nama Proyek" />
                    <x-form.input name="project_location" label="Lokasi Proyek" />
                    <x-form.input name="procurement_target" label="Target Pengadaan" type="date" />
                    <div class="flex items-end pb-2">
                        <x-form.checkbox name="needs_installation" label="Membutuhkan jasa instalasi" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-form.textarea name="technical_notes" label="Catatan Teknis" rows="3" hint="Spesifikasi khusus, kapasitas, tegangan, dsb." />
                    </div>
                </div>
            </section>

            {{-- Items repeater --}}
            <section class="card p-6">
                <h2 class="mb-1 text-base font-semibold text-gray-800">Daftar Kebutuhan</h2>
                <p class="mb-4 text-sm text-gray-500">Tambahkan barang atau material yang Anda butuhkan.</p>

                <div class="space-y-3" x-data="{
                        items: @js($seedItems),
                        add() { this.items.push({ name: '', quantity: 1, product_id: '', note: '' }); },
                        remove(i) { if (this.items.length > 1) { this.items.splice(i, 1); } }
                     }">
                    <template x-for="(item, index) in items" :key="index">
                        <div class="rounded-lg border border-gray-200 p-4">
                            <div class="grid gap-3 sm:grid-cols-[1fr_130px]">
                                <div>
                                    <label class="input-label" :for="'item_name_' + index">Nama Barang <span class="text-red-500">*</span></label>
                                    <input type="text" class="form-input" :id="'item_name_' + index" :name="'items[' + index + '][name]'" x-model="item.name" required>
                                    <input type="hidden" :name="'items[' + index + '][product_id]'" :value="item.product_id">
                                </div>
                                <div>
                                    <label class="input-label" :for="'item_qty_' + index">Jumlah <span class="text-red-500">*</span></label>
                                    <input type="number" min="1" class="form-input" :id="'item_qty_' + index" :name="'items[' + index + '][quantity]'" x-model="item.quantity" required>
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="input-label" :for="'item_note_' + index">Catatan (opsional)</label>
                                <input type="text" class="form-input" :id="'item_note_' + index" :name="'items[' + index + '][note]'" x-model="item.note">
                            </div>
                            <div class="mt-2 text-right" x-show="items.length > 1">
                                <button type="button" @click="remove(index)" class="text-sm font-medium text-red-600 hover:underline">Hapus item</button>
                            </div>
                        </div>
                    </template>

                    <button type="button" @click="add()" class="btn-outline text-sm">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        Tambah Item
                    </button>
                </div>
            </section>

            {{-- Attachment --}}
            <section class="card p-6">
                <h2 class="mb-4 text-base font-semibold text-gray-800">Lampiran (opsional)</h2>
                <label for="attachment" class="input-label">Unggah BOQ / dokumen</label>
                <input type="file" name="attachment" id="attachment"
                       accept=".pdf,.xls,.xlsx,.doc,.docx,.jpg,.png"
                       class="block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100">
                <p class="mt-1 text-xs text-gray-400">Format: PDF, Excel, Word, JPG, PNG. Maksimal 5 MB.</p>
                @error('attachment')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </section>

            <div class="flex items-center gap-3">
                <button type="submit" class="btn-primary">Kirim Permintaan</button>
                <a href="{{ route('products.index') }}" class="btn-outline">Batal</a>
            </div>
        </form>
    </div>
@endsection
