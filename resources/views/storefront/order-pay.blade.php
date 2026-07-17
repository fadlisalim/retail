@extends('layouts.storefront')

@section('title', 'Pembayaran '.$order->order_number.' — '.config('rekasurya.company.brand_name'))
@section('noindex', 'noindex')

@php
    $methodLabel = \Illuminate\Support\Str::of($payment?->method ?? $order->payment_method ?? 'Transfer Bank')->replace('_', ' ')->title();
    $vaNumber = $payment ? ($payment->reference ?: data_get($payment->meta, 'va_number')) : null;
    $instructions = $payment ? data_get($payment->meta, 'instructions') : null;
    $qrisImage = $payment ? data_get($payment->meta, 'qris_image') : null;
@endphp

@section('content')
    <x-breadcrumbs :items="[['label' => 'Pesanan', 'url' => route('orders.track', $order->public_token)], ['label' => 'Pembayaran']]" />

    <div class="mx-auto max-w-xl space-y-6">
        <header class="text-center">
            <h1 class="text-2xl font-bold text-gray-900">Selesaikan Pembayaran</h1>
            <p class="mt-1 text-sm text-gray-500">Terima kasih, pesanan <span class="font-medium text-gray-700">{{ $order->order_number }}</span> telah kami terima ({{ $order->items->count() }} item).</p>
        </header>

        @unless ($order->shipping_cost_confirmed)
            {{-- Freight/cargo: no payable amount or instructions until admin confirms ongkir. --}}
            <section class="card border-amber-200 bg-amber-50 p-6 text-center" role="alert">
                <h2 class="font-semibold text-amber-900">Menunggu Konfirmasi Ongkir</h2>
                <p class="mt-1 text-sm text-amber-800">Pesanan Anda mengandung barang kargo. Tim kami akan mengonfirmasi ongkir terlebih dahulu, lalu nominal &amp; instruksi pembayaran akan aktif di halaman ini. Kami akan memberi tahu Anda.</p>
            </section>
        @else

        {{-- Amount --}}
        <section class="card p-6 text-center">
            <p class="text-sm text-gray-500">Total Pembayaran</p>
            <p class="mt-1 text-3xl font-extrabold text-brand-700 sm:text-4xl">{{ rupiah($order->grand_total) }}</p>
            <p class="mt-2 text-sm text-gray-500">Metode: <span class="font-medium text-gray-800">{{ $methodLabel }}</span></p>
        </section>

        @if ($payment)
            {{-- Static QRIS image --}}
            @if ($qrisImage)
                <section class="card p-6 text-center">
                    <p class="mb-3 text-sm font-medium text-gray-700">Scan QRIS untuk membayar</p>
                    <img src="{{ \Illuminate\Support\Str::startsWith($qrisImage, ['http', 'data:']) ? $qrisImage : asset('storage/'.$qrisImage) }}"
                         alt="QRIS pembayaran" class="mx-auto w-full max-w-[16rem] rounded-lg border border-gray-200">
                    <p class="mt-2 text-xs text-gray-400">Bayar sesuai total, lalu unggah bukti / konfirmasi ke admin.</p>
                </section>
            @endif

            {{-- Reference / bank account(s) --}}
            @if ($vaNumber)
                <section class="card p-6" x-data="{ copied: false }">
                    <p class="text-sm text-gray-500">Nomor Virtual Account / Rekening Tujuan</p>
                    <div class="mt-2 flex items-start justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                        <span x-ref="acct" class="whitespace-pre-line font-mono text-base font-bold leading-relaxed text-gray-900">{{ $vaNumber }}</span>
                        <button type="button" class="btn-outline shrink-0 text-xs" aria-label="Salin nomor rekening"
                                @click="navigator.clipboard?.writeText($refs.acct.innerText.trim()); copied = true; setTimeout(() => copied = false, 2000)">
                            <span x-show="! copied">Salin</span>
                            <span x-show="copied" x-cloak role="status" aria-live="polite" class="text-green-600">Tersalin!</span>
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
                @if ($payment->expires_at->isPast())
                    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                        Batas waktu pembayaran telah lewat. Silakan hubungi tim kami untuk melanjutkan.
                    </div>
                @else
                    <div class="rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800">
                        Selesaikan pembayaran sebelum <span class="font-semibold">{{ $payment->expires_at->translatedFormat('d F Y, H:i') }} WIB</span>
                        (<span class="font-medium">{{ $payment->expires_at->diffForHumans() }}</span>).
                    </div>
                @endif
            @endif
        @else
            <section class="card p-6 text-center text-sm text-gray-600">
                <p>Instruksi pembayaran sedang disiapkan. Silakan cek kembali beberapa saat lagi atau hubungi tim kami.</p>
            </section>
        @endif
        @endunless

        <div class="flex flex-col gap-3 sm:flex-row">
            <a href="{{ route('orders.track', $order->public_token) }}" class="btn-primary flex-1">Lacak Pesanan</a>
            <a href="{{ route('products.index') }}" class="btn-outline flex-1">Lanjut Belanja</a>
        </div>
        @if ($whatsappEnabled)
            <a href="{{ whatsapp_link('Halo Rekasurya, saya ingin konfirmasi pembayaran pesanan '.$order->order_number.'.') }}" target="_blank" rel="noopener" class="flex items-center justify-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm font-medium text-green-700 hover:bg-green-100">
                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163a11.867 11.867 0 0 1-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 0 1 8.413 3.488 11.824 11.824 0 0 1 3.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 0 1-5.688-1.448L.057 24z"/></svg>
                Konfirmasi pembayaran via WhatsApp
            </a>
        @endif
    </div>
@endsection
