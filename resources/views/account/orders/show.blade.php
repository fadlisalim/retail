@extends('layouts.storefront')

@section('title', 'Pesanan '.$order->order_number.' — '.config('rekasurya.company.brand_name'))
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
    <x-breadcrumbs :items="[
        ['label' => 'Akun', 'url' => route('account.dashboard')],
        ['label' => 'Pesanan', 'url' => route('account.orders')],
        ['label' => $order->order_number],
    ]" />

    <div class="grid gap-6 lg:grid-cols-[240px_1fr]">
        @include('partials.account-nav')

        <div class="min-w-0 space-y-6">
            {{-- Header --}}
            <section class="card p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 class="text-xl font-bold text-gray-800">{{ $order->order_number }}</h1>
                        <p class="mt-1 text-sm text-gray-500">Dibuat {{ $order->created_at->translatedFormat('d F Y, H:i') }} WIB</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span class="badge {{ $badgeColors[$order->status->color()] ?? $badgeColors['gray'] }}">{{ $order->status->label() }}</span>
                            <span class="badge {{ $badgeColors[$order->payment_status->color()] ?? $badgeColors['gray'] }}">{{ $order->payment_status->label() }}</span>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('orders.track', $order->public_token) }}" class="btn-outline text-sm">Lacak Pesanan</a>
                        @if ($order->invoice)
                            <a href="{{ route('invoices.show', $order->invoice->public_token) }}" class="btn-outline text-sm">Invoice</a>
                        @endif
                        @if ($order->payment_status === \App\Enums\PaymentStatus::Unpaid)
                            <a href="{{ route('orders.pay', $order->public_token) }}" class="btn-accent text-sm">Bayar Sekarang</a>
                        @endif
                    </div>
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-3">
                {{-- Items + totals --}}
                <div class="space-y-6 lg:col-span-2">
                    <section class="card overflow-hidden">
                        <h2 class="border-b border-gray-100 px-4 py-3 font-semibold text-gray-800">Rincian Produk</h2>
                        <ul class="divide-y divide-gray-100">
                            @foreach ($order->items as $item)
                                <li class="flex gap-3 p-4">
                                    @if ($item->product)
                                        <a href="{{ route('products.show', $item->product->slug) }}" class="shrink-0">
                                            <img src="{{ $item->product->primaryImageUrl() }}" alt="{{ $item->name }}" loading="lazy" class="h-16 w-16 rounded-lg border border-gray-100 object-cover">
                                        </a>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        @if ($item->product)
                                            <a href="{{ route('products.show', $item->product->slug) }}" class="font-medium text-gray-800 hover:text-brand-700">{{ $item->name }}</a>
                                        @else
                                            <p class="font-medium text-gray-800">{{ $item->name }}</p>
                                        @endif
                                        <p class="text-xs text-gray-400">SKU: {{ $item->sku }}</p>
                                        <p class="mt-1 text-sm text-gray-500">{{ rupiah($item->unit_price) }} &times; {{ $item->quantity }}</p>
                                    </div>
                                    <div class="text-right font-semibold text-gray-800">{{ rupiah($item->line_total) }}</div>
                                </li>
                            @endforeach
                        </ul>
                    </section>

                    {{-- Status timeline --}}
                    <section class="card p-6">
                        <h2 class="mb-4 font-semibold text-gray-800">Riwayat Status</h2>
                        @if ($order->statusHistories->isEmpty())
                            <p class="text-sm text-gray-500">Belum ada riwayat status.</p>
                        @else
                            <ol class="relative space-y-6 border-l border-gray-200 pl-6">
                                @foreach ($order->statusHistories as $history)
                                    @php($label = \App\Enums\OrderStatus::tryFrom($history->status)?->label() ?? $history->status)
                                    <li class="relative">
                                        <span class="absolute -left-[27px] top-1 h-3 w-3 rounded-full border-2 border-white bg-brand-500 {{ $loop->first ? 'ring-2 ring-brand-200' : '' }}"></span>
                                        <p class="text-sm font-semibold text-gray-800">{{ $label }}</p>
                                        <p class="text-xs text-gray-400">{{ $history->created_at?->translatedFormat('d M Y, H:i') }} WIB</p>
                                        @if ($history->customer_note)
                                            <p class="mt-1 text-sm text-gray-600">{{ $history->customer_note }}</p>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </section>
                </div>

                {{-- Sidebar: totals, address, payment --}}
                <div class="space-y-6">
                    <section class="card p-6">
                        <h2 class="mb-4 font-semibold text-gray-800">Ringkasan Pembayaran</h2>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between"><dt class="text-gray-500">Subtotal</dt><dd class="text-gray-800">{{ rupiah($order->items_subtotal) }}</dd></div>
                            @if ((float) $order->coupon_discount > 0)
                                <div class="flex justify-between"><dt class="text-gray-500">Diskon Kupon</dt><dd class="text-green-600">-{{ rupiah($order->coupon_discount) }}</dd></div>
                            @endif
                            <div class="flex justify-between"><dt class="text-gray-500">Ongkos Kirim</dt><dd class="text-gray-800">{{ rupiah($order->shipping_cost) }}</dd></div>
                            @if ((float) $order->packing_fee > 0)
                                <div class="flex justify-between"><dt class="text-gray-500">Biaya Pengemasan</dt><dd class="text-gray-800">{{ rupiah($order->packing_fee) }}</dd></div>
                            @endif
                            @if ((float) $order->handling_fee > 0)
                                <div class="flex justify-between"><dt class="text-gray-500">Biaya Penanganan</dt><dd class="text-gray-800">{{ rupiah($order->handling_fee) }}</dd></div>
                            @endif
                            @if ((float) $order->insurance_fee > 0)
                                <div class="flex justify-between"><dt class="text-gray-500">Asuransi</dt><dd class="text-gray-800">{{ rupiah($order->insurance_fee) }}</dd></div>
                            @endif
                            @if ((float) $order->tax_amount > 0)
                                <div class="flex justify-between"><dt class="text-gray-500">PPN</dt><dd class="text-gray-800">{{ rupiah($order->tax_amount) }}</dd></div>
                            @endif
                            <div class="flex justify-between border-t border-gray-100 pt-2 text-base font-bold">
                                <dt class="text-gray-800">Total</dt><dd class="text-brand-700">{{ rupiah($order->grand_total) }}</dd>
                            </div>
                        </dl>
                        @unless ($order->shipping_cost_confirmed)
                            <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">Ongkir masih menunggu konfirmasi admin, total dapat berubah.</p>
                        @endunless
                    </section>

                    <section class="card p-6">
                        <h2 class="mb-3 font-semibold text-gray-800">Alamat Pengiriman</h2>
                        @if ($order->shippingAddress)
                            <p class="text-sm font-medium text-gray-800">{{ $order->shippingAddress->recipient_name }}</p>
                            <p class="text-sm text-gray-500">{{ $order->shippingAddress->phone }}</p>
                            @if ($order->shippingAddress->company_name)
                                <p class="text-sm text-gray-500">{{ $order->shippingAddress->company_name }}</p>
                            @endif
                            <p class="mt-2 text-sm text-gray-600">{{ $order->shippingAddress->fullAddress() }}</p>
                        @else
                            <p class="text-sm text-gray-500">Ambil di lokasi / tidak ada alamat pengiriman.</p>
                        @endif
                    </section>

                    <section class="card p-6">
                        <h2 class="mb-3 font-semibold text-gray-800">Pembayaran</h2>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-500">Status</span>
                            <span class="badge {{ $badgeColors[$order->payment_status->color()] ?? $badgeColors['gray'] }}">{{ $order->payment_status->label() }}</span>
                        </div>
                        @if ($order->payment_method)
                            <div class="mt-2 flex items-center justify-between text-sm">
                                <span class="text-gray-500">Metode</span>
                                <span class="text-gray-800">{{ \Illuminate\Support\Str::of($order->payment_method)->replace('_', ' ')->title() }}</span>
                            </div>
                        @endif
                        @if ((float) $order->paid_amount > 0)
                            <div class="mt-2 flex items-center justify-between text-sm">
                                <span class="text-gray-500">Dibayar</span>
                                <span class="text-gray-800">{{ rupiah($order->paid_amount) }}</span>
                            </div>
                        @endif
                        @if ($order->payment_status === \App\Enums\PaymentStatus::Unpaid)
                            <a href="{{ route('orders.pay', $order->public_token) }}" class="btn-accent mt-4 w-full">Bayar Sekarang</a>
                        @endif
                    </section>
                </div>
            </div>
        </div>
    </div>
@endsection
