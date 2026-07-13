@extends('layouts.admin')

@section('title', 'Quotation / RFQ')

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
    @endphp

    <x-admin.page-header title="Quotation / RFQ" subtitle="Permintaan penawaran dari pelanggan" />

    <form method="GET" action="{{ route('admin.quotations.index') }}" class="card mb-4 flex flex-wrap items-end gap-3 p-4">
        <div class="min-w-52 flex-1">
            <label for="q" class="input-label">Cari</label>
            <input type="text" name="q" id="q" value="{{ request('q') }}" placeholder="No. RFQ / penawaran / perusahaan" class="form-input">
        </div>
        <div class="w-full sm:w-56">
            <label for="status" class="input-label">Status</label>
            <select name="status" id="status" class="form-select">
                <option value="">Semua Status</option>
                @foreach (\App\Enums\QuotationStatus::options() as $opt)
                    <option value="{{ $opt['value'] }}" @selected(request('status') === $opt['value'])>
                        {{ $opt['label'] }} ({{ (int) ($statusCounts[$opt['value']] ?? 0) }})
                    </option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn-primary">Filter</button>
            <a href="{{ route('admin.quotations.index') }}" class="btn-outline">Reset</a>
        </div>
    </form>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">No. RFQ / Penawaran</th>
                    <th class="px-4 py-3">Kontak / Perusahaan</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-right">Berlaku s/d</th>
                    <th class="px-4 py-3 text-right">Tanggal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($quotations as $quotation)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.quotations.show', $quotation) }}" class="font-medium text-brand-700 hover:underline">{{ $quotation->rfq_number }}</a>
                            @if ($quotation->quotation_number)<div class="text-xs text-gray-400">{{ $quotation->quotation_number }}</div>@endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-gray-800">{{ $quotation->contact_name }}</div>
                            <div class="text-xs text-gray-400">{{ $quotation->company_name ?? $quotation->contact_email }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="badge {{ $badgeClasses[$quotation->status->color()] ?? $badgeClasses['gray'] }}">{{ $quotation->status->label() }}</span>
                        </td>
                        <td class="px-4 py-3 text-right font-semibold">{{ (float) $quotation->grand_total > 0 ? rupiah($quotation->grand_total) : '—' }}</td>
                        <td class="px-4 py-3 text-right text-xs text-gray-400">{{ $quotation->valid_until?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right text-xs text-gray-400">{{ $quotation->created_at?->format('d/m/Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">Tidak ada permintaan penawaran.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $quotations->links() }}</div>
@endsection
