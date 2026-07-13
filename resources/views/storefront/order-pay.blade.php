@extends('layouts.storefront')

@section('title', 'Pembayaran '.$order->order_number.' — '.config('rekasurya.company.brand_name'))
@section('noindex', 'noindex')

@php
    $methodLabel = \Illuminate\Support\Str::of($payment?->method ?? $order->payment_method ?? 'Transfer Bank')->replace('_', ' ')->title();
    $vaNumber = $payment ? ($payment->reference ?: data_get($payment->meta, 'va_number')) : null;
    $instructions = $payment ? data_get($payment->meta, 'instructions') : null;
@endphp

@section('content')
    <x-breadcrumbs :items="[['label' => 'Pesanan', 'url' => route('orders.track', $order->public_token)], ['label' => 'Pembayaran']]" />

    <div class="mx-auto max-w-xl space-y-6">
        <header class="text-center">
            <h1 class="text-2xl font-bold text-gray-900">Selesaikan Pembayaran</h1>
            <p class="mt-1 text-sm text-gray-500">Pesanan {{ $order->order_number }}</p>
        </header>

        @unless ($order->shipping_cost_confirmed)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="alert">
                Ongkir sedang dikonfirmasi admin, total dapat berubah.
            </div>
        @endunless

        {{-- Amount --}}
        <section class="card p-6 text-center">
            <p class="text-sm text-gray-500">Total Pembayaran</p>
            <p class="mt-1 text-3xl font-extrabold text-brand-700 sm:text-4xl">{{ rupiah($order->grand_total) }}</p>
            <p class="mt-2 text-sm text-gray-500">Metode: <span class="font-medium text-gray-800">{{ $methodLabel }}</span></p>
        </section>

        @if ($payment)
            {{-- Reference / VA --}}
            @if ($vaNumber)
                <section class="card p-6" x-data="{ copied: false, copy() { navigator.clipboard.writeText('{{ $vaNumber }}').then(() => { this.copied = true; setTimeout(() => this.copied = false, 2000); }); } }">
                    <p class="text-sm text-gray-500">Nomor Virtual Account / Rekening Tujuan</p>
                    <div class="mt-2 flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                        <span class="font-mono text-lg font-bold tracking-wider text-gray-900">{{ $vaNumber }}</span>
                        <button type="button" @click="copy()" class="btn-outline shrink-0 text-xs">
                            <span x-show="! copied">Salin</span>
                            <span x-show="copied" x-cloak class="text-green-600">Tersalin!</span>
                        </button>
                    </div>
                    @if ($payment->meta && data_get($payment->meta, 'bank'))
                        <p class="mt-2 text-sm text-gray-500">Bank: <span class="font-medium text-gray-800">{{ data_get($payment->meta, 'bank') }}</span></p>
                    @endif
                </section>
            @endif

            {{-- Instructions --}}
            @if ($instructions)
                <section class="card p-6">
                    <h2 class="mb-3 font-semibold text-gray-800">Cara Pembayaran</h2>
                    @if (is_array($instructions))
                        <ol class="list-inside list-decimal space-y-1 text-sm text-gray-600">
                            @foreach ($instructions as $step)
                                <li>{{ $step }}</li>
                            @endforeach
                        </ol>
                    @else
                        <p class="whitespace-pre-line text-sm text-gray-600">{{ $instructions }}</p>
                    @endif
                </section>
            @endif

            {{-- Expiry --}}
            @if ($payment->expires_at)
                <div class="rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800">
                    Selesaikan pembayaran sebelum <span class="font-semibold">{{ $payment->expires_at->translatedFormat('d F Y, H:i') }} WIB</span>
                    (<span class="font-medium">{{ $payment->expires_at->diffForHumans() }}</span>).
                </div>
            @endif
        @else
            <section class="card p-6 text-center text-sm text-gray-600">
                <p>Instruksi pembayaran sedang disiapkan. Silakan cek kembali beberapa saat lagi atau hubungi tim kami.</p>
            </section>
        @endif

        <div class="flex flex-col gap-3 sm:flex-row">
            <a href="{{ route('orders.track', $order->public_token) }}" class="btn-primary flex-1">Lacak Pesanan</a>
            <a href="{{ route('products.index') }}" class="btn-outline flex-1">Lanjut Belanja</a>
        </div>
    </div>
@endsection
