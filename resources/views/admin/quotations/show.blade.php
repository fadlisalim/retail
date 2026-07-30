@extends('layouts.admin')

@section('title', 'Penawaran ' . $quotation->rfq_number)

@section('content')
    @php
        $badgeClasses = [
            'gray' => 'bg-gray-100 text-gray-700',
            'green' => 'bg-green-100 text-green-700',
            'amber' => 'bg-amber-100 text-amber-700',
            'red' => 'bg-red-100 text-red-700',
            'blue' => 'bg-blue-100 text-blue-700',
            'teal' => 'bg-teal-100 text-teal-700',
            'purple' => 'bg-purple-100 text-purple-700',
        ];
        $canConvert = in_array($quotation->status->value, ['approved', 'quote_sent'], true);
    @endphp

    <x-admin.page-header :title="'Penawaran ' . $quotation->rfq_number" :subtitle="$quotation->quotation_number ?? 'Belum ada nomor penawaran'">
        <x-slot:actions>
            <a href="{{ route('admin.quotations.index') }}" class="btn-outline">&larr; Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mb-4">
        <span class="badge {{ $badgeClasses[$quotation->status->color()] ?? $badgeClasses['gray'] }}">{{ $quotation->status->label() }}</span>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Main --}}
        <div class="space-y-6 lg:col-span-2">
            {{-- RFQ details --}}
            <div class="card p-5">
                @php
                    use App\Support\QuotationForm;
                    $priority = QuotationForm::priority($quotation);
                    $waPhone = preg_replace('/\D+/', '', (string) $quotation->contact_phone);
                    if (str_starts_with($waPhone, '0')) $waPhone = '62'.ltrim(substr($waPhone, 1), '0');
                @endphp

                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-semibold text-gray-900">Detail Permintaan</h2>
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($priority)
                            <span class="badge {{ $priority['class'] }}">{{ $priority['label'] }}</span>
                        @endif
                        @if ($waPhone)
                            <a href="https://wa.me/{{ $waPhone }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1.5 rounded-full bg-green-50 px-3 py-1 text-xs font-semibold text-green-700 transition hover:bg-green-100">
                                <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163a11.867 11.867 0 0 1-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 0 1 8.413 3.488 11.824 11.824 0 0 1 3.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 0 1-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 0 0 1.51 5.26l-.999 3.648 3.978-1.207z"/></svg>
                                WhatsApp
                            </a>
                        @endif
                        <a href="mailto:{{ $quotation->contact_email }}?subject=Penawaran%20{{ $quotation->rfq_number }}"
                           class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700 transition hover:bg-brand-100">✉️ Email</a>
                    </div>
                </div>

                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">Pemohon</p>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500">Kontak</dt><dd class="font-medium">{{ $quotation->contact_name }}</dd></div>
                    <div><dt class="text-gray-500">Email</dt><dd>{{ $quotation->contact_email }}</dd></div>
                    <div><dt class="text-gray-500">Telepon</dt><dd>{{ $quotation->contact_phone ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Perusahaan</dt><dd>{{ $quotation->company_name ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">NPWP</dt><dd>{{ $quotation->npwp ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Posisi di Proyek</dt><dd>{{ QuotationForm::label('requester_role', $quotation->requester_role) ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Peran Keputusan</dt><dd>{{ QuotationForm::label('decision_role', $quotation->decision_role) ?? '—' }}</dd></div>
                </dl>

                <p class="mb-1 mt-4 border-t border-gray-100 pt-3 text-xs font-semibold uppercase tracking-wide text-gray-400">Proyek</p>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500">Jenis Proyek</dt><dd class="font-medium">{{ QuotationForm::label('project_type', $quotation->project_type) ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Status Proyek</dt><dd class="font-medium">{{ QuotationForm::label('project_status', $quotation->project_status) ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Nama Proyek</dt><dd>{{ $quotation->project_name ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Lokasi</dt><dd>{{ $quotation->project_location ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Sumber Dana</dt><dd>{{ QuotationForm::label('funding_source', $quotation->funding_source) ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Perkiraan Anggaran</dt><dd class="font-medium">{{ QuotationForm::label('budget_range', $quotation->budget_range) ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Jumlah Titik / Unit</dt><dd>{{ $quotation->unit_scale ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Target Pengadaan</dt><dd>{{ $quotation->procurement_target?->format('d/m/Y') ?? '—' }}</dd></div>
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500">Kebutuhan Tambahan</dt>
                        <dd class="flex flex-wrap gap-1.5 pt-0.5">
                            @if ($quotation->needs_installation)<span class="badge bg-brand-50 text-brand-700">Instalasi</span>@endif
                            @if ($quotation->needs_survey)<span class="badge bg-brand-50 text-brand-700">Survei lokasi</span>@endif
                            @if ($quotation->needs_tender_docs)<span class="badge bg-amber-100 text-amber-700">Dokumen tender</span>@endif
                            @unless ($quotation->needs_installation || $quotation->needs_survey || $quotation->needs_tender_docs)
                                <span class="text-gray-400">—</span>
                            @endunless
                        </dd>
                    </div>
                </dl>

                @if (! empty($quotation->requirements))
                    <p class="mb-1 mt-4 border-t border-gray-100 pt-3 text-xs font-semibold uppercase tracking-wide text-gray-400">Data Teknis</p>
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                        @foreach ($quotation->requirements as $key => $value)
                            <div>
                                <dt class="text-gray-500">{{ QuotationForm::technicalLabel($quotation->project_type, $key) }}</dt>
                                <dd class="font-medium text-gray-800">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif

                @if ($quotation->technical_notes)
                    <div class="mt-3 rounded-lg bg-gray-50 p-3 text-sm text-gray-600">
                        <p class="mb-1 text-xs font-semibold uppercase text-gray-400">Catatan Teknis</p>
                        {{ $quotation->technical_notes }}
                    </div>
                @endif
            </div>

            {{-- Pricing form --}}
            <div class="card p-5">
                <h2 class="mb-4 font-semibold text-gray-900">Penetapan Harga</h2>
                <form method="POST" action="{{ route('admin.quotations.price', $quotation) }}" class="space-y-4">
                    @csrf
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b border-gray-100 text-left text-xs uppercase text-gray-500">
                                <tr>
                                    <th class="py-2 pr-2">Item</th>
                                    <th class="px-2 py-2 text-center">Qty</th>
                                    <th class="px-2 py-2">Harga Satuan</th>
                                    <th class="px-2 py-2">Diskon</th>
                                    <th class="px-2 py-2 text-center">Kena Pajak</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach ($quotation->items as $i => $item)
                                    <tr>
                                        <td class="py-2 pr-2">
                                            <input type="hidden" name="items[{{ $i }}][id]" value="{{ $item->id }}">
                                            <div class="font-medium text-gray-800">{{ $item->name }}</div>
                                            @if ($item->note)<div class="text-xs text-gray-400">{{ $item->note }}</div>@endif
                                        </td>
                                        <td class="px-2 py-2 text-center">{{ $item->quantity }}</td>
                                        <td class="px-2 py-2">
                                            <input type="number" step="1" min="0" name="items[{{ $i }}][unit_price]"
                                                   value="{{ old('items.'.$i.'.unit_price', (int) $item->unit_price) }}" class="form-input w-32">
                                        </td>
                                        <td class="px-2 py-2">
                                            <input type="number" step="1" min="0" name="items[{{ $i }}][discount]"
                                                   value="{{ old('items.'.$i.'.discount', (int) $item->discount) }}" class="form-input w-28">
                                        </td>
                                        <td class="px-2 py-2 text-center">
                                            <input type="checkbox" name="items[{{ $i }}][is_taxable]" value="1"
                                                   @checked(old('items.'.$i.'.is_taxable', $item->is_taxable ?? true))
                                                   class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="grid gap-4 border-t border-gray-100 pt-4 sm:grid-cols-2">
                        <div>
                            <label for="discount" class="input-label">Diskon Header (Rp)</label>
                            <input type="number" step="1" min="0" name="discount" id="discount" value="{{ old('discount', (int) $quotation->discount) }}" class="form-input">
                        </div>
                        <div>
                            <label for="shipping_cost" class="input-label">Ongkir (Rp)</label>
                            <input type="number" step="1" min="0" name="shipping_cost" id="shipping_cost" value="{{ old('shipping_cost', (int) $quotation->shipping_cost) }}" class="form-input">
                        </div>
                        <div>
                            <label for="payment_terms" class="input-label">Termin Pembayaran</label>
                            <input type="text" name="payment_terms" id="payment_terms" value="{{ old('payment_terms', $quotation->payment_terms) }}" placeholder="mis. 30% DP, 70% sebelum kirim" class="form-input">
                        </div>
                        <div>
                            <label for="valid_until" class="input-label">Berlaku Sampai</label>
                            <input type="date" name="valid_until" id="valid_until" value="{{ old('valid_until', $quotation->valid_until?->format('Y-m-d')) }}" class="form-input">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="admin_note" class="input-label">Catatan Penawaran</label>
                            <textarea name="admin_note" id="admin_note" rows="3" class="form-textarea">{{ old('admin_note', $quotation->admin_note) }}</textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary">Simpan &amp; Kirim Penawaran</button>
                </form>
            </div>

            {{-- Attachments --}}
            @if ($quotation->attachments->isNotEmpty())
                <div class="card p-5">
                    <h2 class="mb-3 font-semibold text-gray-900">Lampiran</h2>
                    <ul class="space-y-1 text-sm">
                        @foreach ($quotation->attachments as $att)
                            <li class="flex items-center justify-between gap-2">
                                <span>{{ $att->title ?? $att->path }}</span>
                                <span class="text-xs text-gray-400">{{ $att->type }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Totals --}}
            <div class="card p-5">
                <h2 class="mb-3 font-semibold text-gray-900">Ringkasan</h2>
                <dl class="space-y-1.5 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Subtotal</dt><dd>{{ rupiah($quotation->items_subtotal) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Diskon</dt><dd>- {{ rupiah($quotation->discount) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Ongkir</dt><dd>{{ rupiah($quotation->shipping_cost) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">PPN</dt><dd>{{ rupiah($quotation->tax_amount) }}</dd></div>
                    <div class="flex justify-between border-t border-gray-100 pt-2 text-base font-bold"><dt>Total</dt><dd class="text-brand-700">{{ rupiah($quotation->grand_total) }}</dd></div>
                </dl>
            </div>

            {{-- Status change --}}
            <div class="card p-5">
                <h2 class="mb-3 font-semibold text-gray-900">Ubah Status</h2>
                <form method="POST" action="{{ route('admin.quotations.status', $quotation) }}" class="space-y-3">
                    @csrf
                    <select name="status" class="form-select">
                        @foreach (\App\Enums\QuotationStatus::options() as $opt)
                            <option value="{{ $opt['value'] }}" @selected($quotation->status->value === $opt['value'])>{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn-outline w-full">Perbarui Status</button>
                </form>
            </div>

            {{-- Convert --}}
            @if ($quotation->converted_order_id && $quotation->convertedOrder)
                <div class="card p-5">
                    <p class="mb-2 text-sm text-gray-600">Penawaran ini telah menjadi pesanan.</p>
                    <a href="{{ route('admin.orders.show', $quotation->convertedOrder) }}" class="btn-primary w-full text-center">Lihat Pesanan</a>
                </div>
            @elseif ($canConvert)
                <div class="card p-5">
                    <h2 class="mb-2 font-semibold text-gray-900">Konversi</h2>
                    <p class="mb-3 text-xs text-gray-500">Buat pesanan dari penawaran yang telah disetujui.</p>
                    <form method="POST" action="{{ route('admin.quotations.convert', $quotation) }}" onsubmit="return confirm('Jadikan penawaran ini sebagai pesanan?')">
                        @csrf
                        <button type="submit" class="btn-accent w-full">Jadikan Pesanan</button>
                    </form>
                </div>
            @endif

            {{-- Revisions --}}
            <div class="card p-5">
                <h2 class="mb-3 font-semibold text-gray-900">Riwayat Revisi</h2>
                <ul class="space-y-2 text-sm">
                    @forelse ($quotation->revisions as $rev)
                        <li class="flex items-center justify-between gap-2">
                            <span class="font-medium">Versi {{ $rev->version }}</span>
                            <span class="text-xs text-gray-400">{{ $rev->created_at?->format('d/m/Y H:i') }}</span>
                        </li>
                    @empty
                        <li class="text-gray-400">Belum ada revisi.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection
