@extends('layouts.storefront')

@section('title', 'Quotation Saya — '.config('rekasurya.company.brand_name'))
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
@endphp

@section('content')
    <x-breadcrumbs :items="[['label' => 'Akun', 'url' => route('account.dashboard')], ['label' => 'Quotation']]" />

    <div class="grid gap-6 lg:grid-cols-[240px_1fr]">
        @include('partials.account-nav')

        <div class="min-w-0 space-y-6">
            <div class="flex items-center justify-between gap-3">
                <h1 class="text-xl font-bold text-gray-800">Quotation Saya</h1>
                <a href="{{ route('quotations.create') }}" class="btn-primary text-sm">Ajukan Penawaran</a>
            </div>

            @if ($quotations->isEmpty())
                <div class="card px-4 py-12 text-center">
                    <p class="text-sm text-gray-500">Anda belum mengajukan permintaan penawaran.</p>
                    <a href="{{ route('quotations.create') }}" class="btn-primary mt-4">Minta Penawaran</a>
                </div>
            @else
                <div class="card hidden overflow-hidden md:block">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[720px] text-sm">
                            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3 font-medium">No. RFQ</th>
                                    <th class="px-4 py-3 font-medium">No. Penawaran</th>
                                    <th class="px-4 py-3 font-medium">Proyek</th>
                                    <th class="px-4 py-3 font-medium">Status</th>
                                    <th class="px-4 py-3 text-right font-medium">Total</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($quotations as $q)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 font-medium text-gray-800">{{ $q->rfq_number }}</td>
                                        <td class="px-4 py-3 text-gray-500">{{ $q->quotation_number ?? '—' }}</td>
                                        <td class="px-4 py-3 text-gray-500">{{ $q->project_name ?? '—' }}</td>
                                        <td class="px-4 py-3">
                                            <span class="badge {{ $badgeColors[$q->status->color()] ?? $badgeColors['gray'] }}">{{ $q->status->label() }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold text-gray-800">{{ (float) $q->grand_total > 0 ? rupiah($q->grand_total) : '—' }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <a href="{{ route('quotations.show', $q->public_token) }}" class="font-medium text-brand-600 hover:underline">Detail</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="space-y-3 md:hidden">
                    @foreach ($quotations as $q)
                        <a href="{{ route('quotations.show', $q->public_token) }}" class="card block p-4">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-gray-800">{{ $q->rfq_number }}</span>
                                <span class="badge {{ $badgeColors[$q->status->color()] ?? $badgeColors['gray'] }}">{{ $q->status->label() }}</span>
                            </div>
                            @if ($q->project_name)<p class="mt-1 text-sm text-gray-600">{{ $q->project_name }}</p>@endif
                            <p class="mt-1 text-xs text-gray-400">
                                {{ $q->quotation_number ? 'Penawaran: '.$q->quotation_number : 'Menunggu penawaran' }}
                                @if ((float) $q->grand_total > 0) • {{ rupiah($q->grand_total) }} @endif
                            </p>
                        </a>
                    @endforeach
                </div>

                <div>{{ $quotations->links() }}</div>
            @endif
        </div>
    </div>
@endsection
