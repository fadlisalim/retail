@extends('layouts.storefront')

@section('title', 'Penawaran '.$quotation->rfq_number.' — '.config('rekasurya.company.brand_name'))
@section('noindex', 'noindex')

@php
    $badgeColors = [
        'gray' => 'bg-gray-100 text-gray-700',
        'amber' => 'bg-amber-100 text-amber-700',
        'blue' => 'bg-blue-100 text-blue-700',
        'green' => 'bg-green-100 text-green-700',
        'red' => 'bg-red-100 text-red-700',
        'teal' => 'bg-teal-100 text-teal-700',
        'purple' => 'bg-purple-100 text-purple-700',
    ];
    $canDecide = in_array($quotation->status, [\App\Enums\QuotationStatus::QuoteSent, \App\Enums\QuotationStatus::Revised], true);
    $adminAttachments = $quotation->attachments->where('type', 'admin');
    $customerAttachments = $quotation->attachments->where('type', '!==', 'admin');
@endphp

@section('content')
    <x-breadcrumbs :items="[['label' => 'Penawaran'], ['label' => $quotation->rfq_number]]" />

    <div class="mx-auto max-w-4xl space-y-6">
        {{-- Header --}}
        <section class="card p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-xl font-bold text-gray-800">{{ $quotation->rfq_number }}</h1>
                    @if ($quotation->quotation_number)
                        <p class="mt-1 text-sm text-gray-500">No. Penawaran: {{ $quotation->quotation_number }}</p>
                    @endif
                    <div class="mt-3">
                        <span class="badge {{ $badgeColors[$quotation->status->color()] ?? $badgeColors['gray'] }}">{{ $quotation->status->label() }}</span>
                    </div>
                </div>
                <a href="{{ whatsapp_link('Halo Rekasurya, terkait penawaran '.$quotation->rfq_number) }}" target="_blank" rel="noopener" class="btn-outline text-sm">
                    <svg class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M.057 24l1.687-6.163a11.867 11.867 0 0 1-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 0 1 8.413 3.488 11.824 11.824 0 0 1 3.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 0 1-5.688-1.448L.057 24z"/></svg>
                    Tanya via WhatsApp
                </a>
            </div>

            @if ($canDecide)
                <div class="mt-4 flex flex-wrap gap-3 border-t border-gray-100 pt-4">
                    <form action="{{ route('quotations.approve', $quotation->public_token) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn-primary">Setujui Penawaran</button>
                    </form>
                    <form action="{{ route('quotations.reject', $quotation->public_token) }}" method="POST" onsubmit="return confirm('Tolak penawaran ini?');">
                        @csrf
                        <button type="submit" class="btn-outline text-red-600 hover:border-red-400 hover:text-red-700">Tolak</button>
                    </form>
                </div>
            @endif
        </section>

        {{-- Project details --}}
        <section class="card p-6">
            <h2 class="mb-4 font-semibold text-gray-800">Detail Proyek</h2>
            <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                <div><dt class="text-gray-500">Nama Kontak</dt><dd class="font-medium text-gray-800">{{ $quotation->contact_name }}</dd></div>
                <div><dt class="text-gray-500">Email</dt><dd class="font-medium text-gray-800">{{ $quotation->contact_email }}</dd></div>
                @if ($quotation->contact_phone)<div><dt class="text-gray-500">Telepon</dt><dd class="font-medium text-gray-800">{{ $quotation->contact_phone }}</dd></div>@endif
                @if ($quotation->company_name)<div><dt class="text-gray-500">Perusahaan</dt><dd class="font-medium text-gray-800">{{ $quotation->company_name }}</dd></div>@endif
                @if ($quotation->project_name)<div><dt class="text-gray-500">Nama Proyek</dt><dd class="font-medium text-gray-800">{{ $quotation->project_name }}</dd></div>@endif
                @if ($quotation->project_location)<div><dt class="text-gray-500">Lokasi</dt><dd class="font-medium text-gray-800">{{ $quotation->project_location }}</dd></div>@endif
                @if ($quotation->procurement_target)<div><dt class="text-gray-500">Target Pengadaan</dt><dd class="font-medium text-gray-800">{{ $quotation->procurement_target->translatedFormat('d F Y') }}</dd></div>@endif
                <div><dt class="text-gray-500">Instalasi</dt><dd class="font-medium text-gray-800">{{ $quotation->needs_installation ? 'Ya' : 'Tidak' }}</dd></div>
            </dl>
            @if ($quotation->technical_notes)
                <div class="mt-4 border-t border-gray-100 pt-4">
                    <dt class="text-sm text-gray-500">Catatan Teknis</dt>
                    <dd class="mt-1 text-sm text-gray-700">{{ $quotation->technical_notes }}</dd>
                </div>
            @endif
        </section>

        {{-- Items --}}
        <section class="card overflow-hidden">
            <h2 class="border-b border-gray-100 px-4 py-3 font-semibold text-gray-800">Daftar Barang</h2>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[560px] text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">Barang</th>
                            <th class="px-4 py-3 text-center font-medium">Qty</th>
                            <th class="px-4 py-3 text-right font-medium">Harga Satuan</th>
                            <th class="px-4 py-3 text-right font-medium">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($quotation->items as $item)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-800">{{ $item->name }}</p>
                                    @if ($item->note)<p class="text-xs text-gray-400">{{ $item->note }}</p>@endif
                                </td>
                                <td class="px-4 py-3 text-center text-gray-600">{{ $item->quantity }}</td>
                                <td class="px-4 py-3 text-right text-gray-700">
                                    @if ((float) $item->unit_price > 0)
                                        {{ rupiah($item->unit_price) }}
                                    @else
                                        <span class="text-amber-600">Menunggu penawaran</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-gray-800">{{ (float) $item->line_total > 0 ? rupiah($item->line_total) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ((float) $quotation->grand_total > 0)
                <div class="border-t border-gray-100 p-4">
                    <dl class="ml-auto max-w-xs space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-500">Subtotal</dt><dd class="text-gray-800">{{ rupiah($quotation->items_subtotal) }}</dd></div>
                        @if ((float) $quotation->discount > 0)
                            <div class="flex justify-between"><dt class="text-gray-500">Diskon</dt><dd class="text-green-600">-{{ rupiah($quotation->discount) }}</dd></div>
                        @endif
                        @if ((float) $quotation->shipping_cost > 0)
                            <div class="flex justify-between"><dt class="text-gray-500">Ongkos Kirim</dt><dd class="text-gray-800">{{ rupiah($quotation->shipping_cost) }}</dd></div>
                        @endif
                        @if ((float) $quotation->tax_amount > 0)
                            <div class="flex justify-between"><dt class="text-gray-500">PPN</dt><dd class="text-gray-800">{{ rupiah($quotation->tax_amount) }}</dd></div>
                        @endif
                        <div class="flex justify-between border-t border-gray-100 pt-2 text-base font-bold">
                            <dt class="text-gray-800">Total</dt><dd class="text-brand-700">{{ rupiah($quotation->grand_total) }}</dd>
                        </div>
                    </dl>
                </div>
            @endif
        </section>

        {{-- Terms --}}
        @if ($quotation->payment_terms || $quotation->valid_until)
            <section class="card p-6">
                <h2 class="mb-3 font-semibold text-gray-800">Syarat Penawaran</h2>
                <dl class="space-y-2 text-sm">
                    @if ($quotation->payment_terms)
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">Termin Pembayaran</dt><dd class="text-right text-gray-800">{{ $quotation->payment_terms }}</dd></div>
                    @endif
                    @if ($quotation->valid_until)
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">Berlaku Hingga</dt><dd class="text-right text-gray-800">{{ $quotation->valid_until->translatedFormat('d F Y') }}</dd></div>
                    @endif
                </dl>
            </section>
        @endif

        {{-- Attachments --}}
        @if ($adminAttachments->isNotEmpty())
            <section class="card p-6">
                <h2 class="mb-3 font-semibold text-gray-800">Dokumen Penawaran</h2>
                <ul class="space-y-2">
                    @foreach ($adminAttachments as $att)
                        <li>
                            <a href="{{ asset('storage/'.$att->path) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 text-sm font-medium text-brand-600 hover:underline">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                                {{ $att->title ?: 'Dokumen' }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($customerAttachments->isNotEmpty())
            <section class="card p-6">
                <h2 class="mb-3 font-semibold text-gray-800">Lampiran Anda</h2>
                <ul class="space-y-2">
                    @foreach ($customerAttachments as $att)
                        <li>
                            <a href="{{ asset('storage/'.$att->path) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-brand-700 hover:underline">
                                {{ $att->title ?: 'Lampiran' }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
@endsection
