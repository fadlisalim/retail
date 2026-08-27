@extends('layouts.storefront')

@section('title', 'Invoice '.$invoice->invoice_number.' — '.config('rekasurya.company.brand_name'))
@section('noindex', 'noindex')

@php
    $company = $invoice->company_snapshot ?? [];
    $customer = $invoice->customer_snapshot ?? [];
    $order = $invoice->order;
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

@push('head')
    <style>
        @media print {
            body * { visibility: hidden; }
            #invoice, #invoice * { visibility: visible; }
            #invoice { position: absolute; inset: 0; width: 100%; box-shadow: none !important; border: 0 !important; }
        }
    </style>
@endpush

@section('content')
    <div class="mx-auto max-w-3xl">
        {{-- Actions --}}
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 print:hidden">
            <x-breadcrumbs :items="[['label' => 'Invoice'], ['label' => $invoice->invoice_number]]" />
            <div class="flex gap-2">
                <button type="button" onclick="window.print()" class="btn-outline text-sm">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0V4.125c0-.621-.504-1.125-1.125-1.125h-7.5c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Z" /></svg>
                    Cetak
                </button>
                <a href="{{ route('invoices.pdf', $invoice->public_token) }}" class="btn-primary text-sm">Download PDF</a>
            </div>
        </div>

        {{-- Invoice document --}}
        <div id="invoice" class="card p-6 sm:p-8">
            <div class="flex flex-col gap-4 border-b border-gray-200 pb-6 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-lg font-extrabold text-brand-700">{{ $company['brand_name'] ?? config('rekasurya.company.brand_name') }}</p>
                    @if (! empty($company['legal_name']))<p class="text-sm text-gray-600">{{ $company['legal_name'] }}</p>@endif
                    @if (! empty($company['address']))<p class="mt-1 text-sm text-gray-500">{{ $company['address'] }}</p>@endif
                    <p class="text-sm text-gray-500">
                        @if (! empty($company['phone'])){{ $company['phone'] }}@endif
                        @if (! empty($company['email'])) • {{ $company['email'] }}@endif
                    </p>
                    @if (! empty($company['npwp']))<p class="text-sm text-gray-500">NPWP: {{ $company['npwp'] }}</p>@endif
                </div>
                <div class="sm:text-right">
                    <h1 class="text-2xl font-bold text-gray-900">INVOICE</h1>
                    <p class="mt-1 text-sm font-medium text-gray-700">{{ $invoice->invoice_number }}</p>
                    <p class="text-sm text-gray-500">{{ optional($invoice->issued_at)->translatedFormat('d F Y') }}</p>
                    @if ($order)
                        <p class="mt-1 text-xs text-gray-400">Pesanan: {{ $order->order_number }}</p>
                        <span class="badge mt-2 {{ $badgeColors[$order->payment_status->color()] ?? $badgeColors['gray'] }}">{{ $order->payment_status->label() }}</span>
                    @endif
                </div>
            </div>

            {{-- Bill to --}}
            <div class="grid gap-6 py-6 sm:grid-cols-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Ditagihkan Kepada</p>
                    @if (! empty($customer['company']))
                        <p class="mt-1 font-medium text-gray-800">{{ $customer['company'] }}</p>
                        <p class="text-sm text-gray-600">u.p. {{ $customer['pic'] ?? ($customer['name'] ?? '—') }}</p>
                    @else
                        <p class="mt-1 font-medium text-gray-800">{{ $customer['name'] ?? '—' }}</p>
                        @if (! empty($customer['pic']))<p class="text-sm text-gray-600">u.p. {{ $customer['pic'] }}</p>@endif
                    @endif
                    @if (! empty($customer['address']))<p class="text-sm text-gray-500">{{ $customer['address'] }}</p>@endif
                    @if (! empty($customer['phone']))<p class="text-sm text-gray-500">{{ $customer['phone'] }}</p>@endif
                    @if (! empty($customer['email']))<p class="text-sm text-gray-500">{{ $customer['email'] }}</p>@endif
                    @if (! empty($customer['npwp']))<p class="text-sm text-gray-500">NPWP: {{ $customer['npwp'] }}</p>@endif
                </div>
            </div>

            {{-- Items --}}
            <div class="overflow-x-auto">
                <table class="w-full min-w-[480px] text-sm">
                    <thead class="border-y border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="py-2 pr-2 font-medium">Deskripsi</th>
                            <th class="px-2 py-2 text-center font-medium">Qty</th>
                            <th class="px-2 py-2 text-right font-medium">Harga</th>
                            <th class="py-2 pl-2 text-right font-medium">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($invoice->lineItems() as $item)
                            <tr>
                                <td class="py-3 pr-2">
                                    <p class="font-medium text-gray-800">{{ $item['name'] }}</p>
                                    @if (! empty($item['sku']))<p class="text-xs text-gray-400">{{ $item['sku'] }}</p>@endif
                                </td>
                                <td class="px-2 py-3 text-center text-gray-600">{{ $item['quantity'] }}</td>
                                <td class="px-2 py-3 text-right text-gray-700">{{ rupiah($item['unit_price']) }}</td>
                                <td class="py-3 pl-2 text-right font-medium text-gray-800">{{ rupiah($item['line_total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Totals --}}
            <div class="mt-6 flex justify-end">
                <dl class="w-full max-w-xs space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Subtotal</dt><dd class="text-gray-800">{{ rupiah($invoice->subtotal) }}</dd></div>
                    @if ((float) $invoice->discount > 0)
                        <div class="flex justify-between"><dt class="text-gray-500">Diskon</dt><dd class="text-green-600">-{{ rupiah($invoice->discount) }}</dd></div>
                    @endif
                    <div class="flex justify-between"><dt class="text-gray-500">Pengiriman</dt><dd class="text-gray-800">{{ rupiah($invoice->shipping) }}</dd></div>
                    @if ($invoice->tax > 0)<div class="flex justify-between"><dt class="text-gray-500">PPN</dt><dd class="text-gray-800">{{ rupiah($invoice->tax) }}</dd></div>@endif
                    <div class="flex justify-between border-t border-gray-200 pt-2 text-base font-bold">
                        <dt class="text-gray-900">Total</dt><dd class="text-brand-700">{{ rupiah($invoice->total) }}</dd>
                    </div>
                </dl>
            </div>

            <p class="mt-8 border-t border-gray-100 pt-4 text-center text-xs text-gray-400">Terima kasih atas kepercayaan Anda kepada {{ $company['brand_name'] ?? config('rekasurya.company.brand_name') }}.</p>
        </div>
    </div>
@endsection
