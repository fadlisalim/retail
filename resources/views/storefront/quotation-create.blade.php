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

        {{-- projectType drives which technical block is shown/submitted. --}}
        <form action="{{ route('quotations.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6"
              x-data="{ projectType: @js(old('project_type', '')) }">
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
                    <x-form.select name="requester_role" label="Posisi Anda di proyek ini"
                                   :options="\App\Support\QuotationForm::REQUESTER_ROLES" placeholder="— Pilih —" />
                    <x-form.select name="decision_role" label="Peran dalam pengambilan keputusan"
                                   :options="\App\Support\QuotationForm::DECISION_ROLES" placeholder="— Pilih —" />
                </div>
            </section>

            {{-- Project context: stage/funding/budget decide how sales prioritises. --}}
            <section class="card p-6">
                <h2 class="mb-1 text-base font-semibold text-gray-800">Detail Proyek</h2>
                <p class="mb-4 text-sm text-gray-500">Makin lengkap datanya, makin cepat &amp; akurat penawaran yang kami kirim.</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.select name="project_type" label="Jenis Proyek" required
                                   :options="\App\Support\QuotationForm::PROJECT_TYPES" placeholder="— Pilih jenis proyek —"
                                   x-model="projectType" />
                    <x-form.select name="project_status" label="Status Proyek" required
                                   :options="\App\Support\QuotationForm::PROJECT_STATUSES" placeholder="— Pilih status —" />
                    <x-form.input name="project_name" label="Nama Proyek" />
                    <x-form.input name="project_location" label="Lokasi Proyek" required hint="Kabupaten/kota &amp; provinsi" />
                    <x-form.select name="funding_source" label="Sumber Dana"
                                   :options="\App\Support\QuotationForm::FUNDING_SOURCES" placeholder="— Pilih —" />
                    <x-form.select name="budget_range" label="Perkiraan Anggaran" required
                                   :options="\App\Support\QuotationForm::BUDGET_RANGES" placeholder="— Pilih rentang —" />
                    <x-form.input name="unit_scale" label="Jumlah Titik / Unit / Penerima" hint="Contoh: 20 titik PJU, 1 rumah, ±150 KK" />
                    <x-form.input name="procurement_target" label="Target Pengadaan" type="date" />
                </div>

                <div class="mt-4 space-y-2 border-t border-gray-100 pt-4">
                    <p class="text-sm font-medium text-gray-700">Kebutuhan tambahan</p>
                    <x-form.checkbox name="needs_installation" label="Membutuhkan jasa instalasi" />
                    <x-form.checkbox name="needs_survey" label="Membutuhkan survei lokasi" />
                    <x-form.checkbox name="needs_tender_docs" label="Membutuhkan dokumen pendukung tender (proposal teknis, RAB, spesifikasi, surat dukungan)" />
                </div>
            </section>

            {{-- Technical block: only the questions relevant to the chosen type. --}}
            <section class="card p-6" x-show="projectType" x-cloak>
                <h2 class="mb-1 text-base font-semibold text-gray-800">Data Teknis</h2>
                <p class="mb-4 text-sm text-gray-500">Isi yang Anda ketahui saja — sisanya bisa kami hitung/konfirmasi nanti.</p>

                @foreach ($technicalFields as $type => $fields)
                    <div x-show="projectType === '{{ $type }}'" x-cloak class="grid gap-4 sm:grid-cols-2">
                        @foreach ($fields as $field)
                            @php($inputName = 'requirements['.$field['key'].']')
                            @php($old = old('requirements.'.$field['key']))
                            <div @class(['sm:col-span-2' => ($field['type'] ?? 'text') === 'text' && str_contains($field['key'], 'summary')])>
                                <label for="req_{{ $type }}_{{ $field['key'] }}" class="input-label">{{ $field['label'] }}</label>
                                @if (($field['type'] ?? 'text') === 'select')
                                    <select name="{{ $inputName }}" id="req_{{ $type }}_{{ $field['key'] }}" class="form-select"
                                            :disabled="projectType !== '{{ $type }}'">
                                        <option value="">— Pilih —</option>
                                        @foreach ($field['options'] as $option)
                                            <option value="{{ $option }}" @selected($old === $option)>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="text" name="{{ $inputName }}" id="req_{{ $type }}_{{ $field['key'] }}"
                                           value="{{ $old }}" class="form-input" maxlength="500"
                                           :disabled="projectType !== '{{ $type }}'">
                                @endif
                                @if (! empty($field['hint']))
                                    <p class="mt-1 text-xs text-gray-400">{{ $field['hint'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endforeach

                <div class="mt-4 border-t border-gray-100 pt-4">
                    <x-form.textarea name="technical_notes" label="Catatan Teknis Lainnya" rows="3"
                                     hint="Spesifikasi khusus, merek yang diminta, kondisi lokasi, kendala akses, dsb." />
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
                <label for="attachment" class="input-label">Unggah BOQ / RAB / KAK-TOR / gambar layout / foto lokasi</label>
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
