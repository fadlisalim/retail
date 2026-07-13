@extends('layouts.storefront')

@section('title', 'Pesanan Saya — '.config('rekasurya.company.brand_name'))
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
    <x-breadcrumbs :items="[['label' => 'Akun', 'url' => route('account.dashboard')], ['label' => 'Pesanan']]" />

    <div class="grid gap-6 lg:grid-cols-[240px_1fr]">
        @include('partials.account-nav')

        <div class="min-w-0 space-y-6">
            <h1 class="text-xl font-bold text-gray-800">Pesanan Saya</h1>

            @if ($orders->isEmpty())
                <div class="card px-4 py-12 text-center">
                    <p class="text-sm text-gray-500">Anda belum memiliki pesanan.</p>
                    <a href="{{ route('products.index') }}" class="btn-primary mt-4">Mulai Belanja</a>
                </div>
            @else
                {{-- Desktop table --}}
                <div class="card hidden overflow-hidden md:block">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[720px] text-sm">
                            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3 font-medium">No. Pesanan</th>
                                    <th class="px-4 py-3 font-medium">Tanggal</th>
                                    <th class="px-4 py-3 font-medium">Item</th>
                                    <th class="px-4 py-3 font-medium">Status</th>
                                    <th class="px-4 py-3 font-medium">Pembayaran</th>
                                    <th class="px-4 py-3 text-right font-medium">Total</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($orders as $order)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 font-medium text-gray-800">{{ $order->order_number }}</td>
                                        <td class="px-4 py-3 text-gray-500">{{ $order->created_at->translatedFormat('d M Y') }}</td>
                                        <td class="px-4 py-3 text-gray-500">{{ $order->items->count() }} item</td>
                                        <td class="px-4 py-3">
                                            <span class="badge {{ $badgeColors[$order->status->color()] ?? $badgeColors['gray'] }}">{{ $order->status->label() }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="badge {{ $badgeColors[$order->payment_status->color()] ?? $badgeColors['gray'] }}">{{ $order->payment_status->label() }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold text-gray-800">{{ rupiah($order->grand_total) }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <a href="{{ route('account.orders.show', $order->public_token) }}" class="font-medium text-brand-600 hover:underline">Detail</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Mobile cards --}}
                <div class="space-y-3 md:hidden">
                    @foreach ($orders as $order)
                        <a href="{{ route('account.orders.show', $order->public_token) }}" class="card block p-4">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-gray-800">{{ $order->order_number }}</span>
                                <span class="text-sm font-semibold text-gray-800">{{ rupiah($order->grand_total) }}</span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">{{ $order->created_at->translatedFormat('d M Y') }} • {{ $order->items->count() }} item</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span class="badge {{ $badgeColors[$order->status->color()] ?? $badgeColors['gray'] }}">{{ $order->status->label() }}</span>
                                <span class="badge {{ $badgeColors[$order->payment_status->color()] ?? $badgeColors['gray'] }}">{{ $order->payment_status->label() }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div>{{ $orders->links() }}</div>
            @endif
        </div>
    </div>
@endsection
