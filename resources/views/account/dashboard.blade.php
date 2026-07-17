@extends('layouts.storefront')

@section('title', 'Dashboard Akun — '.config('rekasurya.company.brand_name'))
@section('noindex', 'noindex')

@section('content')
    <x-breadcrumbs :items="[['label' => 'Akun']]" />

    <div class="grid gap-6 lg:grid-cols-[240px_1fr]">
        @include('partials.account-nav')

        <div class="min-w-0 space-y-6">
            {{-- Greeting --}}
            <section class="card bg-gradient-to-r from-brand-600 to-brand-700 p-6 text-white">
                <h1 class="text-xl font-bold sm:text-2xl">Halo, {{ $user->name }} 👋</h1>
                <p class="mt-1 text-sm text-brand-50">Selamat datang kembali di {{ config('rekasurya.company.brand_name') }}.</p>
            </section>

            {{-- Affiliate CTA — status-aware. Non-members get an attention-grabbing
                 (pulsing) red invite; members see useful status/balance. --}}
            @php
                $aff = $user->affiliate;
                $affIcon = 'M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244';
                $arrowIcon = 'M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3';
            @endphp
            @if (! $aff)
                <section class="relative overflow-hidden rounded-xl bg-gradient-to-br from-red-600 via-rose-600 to-red-700 p-5 text-white shadow-md sm:p-6" aria-label="Ajakan gabung program afiliasi">
                    {{-- Pinging "look at me" dot — auto-disabled under prefers-reduced-motion. --}}
                    <span class="absolute right-4 top-4 flex h-3 w-3">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span>
                        <span class="relative inline-flex h-3 w-3 rounded-full bg-white"></span>
                    </span>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-start gap-3">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-white/20">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $affIcon }}" />
                                </svg>
                            </span>
                            <div>
                                <span class="inline-block rounded-full bg-white/20 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide">Program Afiliasi</span>
                                <h2 class="mt-1 text-lg font-bold leading-snug sm:text-xl">Dapatkan komisi hingga 10%!</h2>
                                <p class="mt-0.5 text-sm text-red-50">Rekomendasikan produk energi surya &amp; raih komisi. Gratis, tanpa modal.</p>
                            </div>
                        </div>
                        <a href="{{ route('affiliate.landing') }}"
                           class="inline-flex shrink-0 animate-pulse items-center justify-center gap-1.5 rounded-lg bg-white px-5 py-2.5 font-bold text-red-600 shadow transition hover:animate-none hover:bg-red-50">
                            Gabung Sekarang
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $arrowIcon }}" />
                            </svg>
                        </a>
                    </div>
                </section>
            @elseif ($aff->isActive())
                <a href="{{ route('account.affiliate.dashboard') }}" class="block rounded-xl border border-brand-200 bg-brand-50 p-5 transition hover:bg-brand-100/60" aria-label="Buka dashboard afiliasi">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-brand-100">
                                <svg class="h-6 w-6 text-brand-600" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $affIcon }}" />
                                </svg>
                            </span>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-brand-600">Afiliator Aktif</p>
                                <p class="mt-0.5 text-sm text-gray-600">Saldo komisi tersedia</p>
                                <p class="text-lg font-bold text-brand-700">{{ rupiah($aff->availableBalance()) }}</p>
                            </div>
                        </div>
                        <span class="hidden shrink-0 items-center gap-1 text-sm font-semibold text-brand-700 sm:inline-flex">
                            Buka Dashboard
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $arrowIcon }}" />
                            </svg>
                        </span>
                    </div>
                </a>
            @elseif ($aff->status === \App\Enums\AffiliateStatus::Pending)
                <a href="{{ route('account.affiliate.dashboard') }}" class="block rounded-xl border border-amber-200 bg-amber-50 p-5 transition hover:bg-amber-100/60" aria-label="Cek status pendaftaran afiliasi">
                    <div class="flex items-center gap-3">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-amber-100">
                            <svg class="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </span>
                        <div>
                            <p class="font-semibold text-amber-900">Pendaftaran Afiliasi Sedang Diverifikasi</p>
                            <p class="mt-0.5 text-sm text-amber-800">Tim kami sedang meninjau data Anda. Klik untuk melihat status.</p>
                        </div>
                    </div>
                </a>
            @else
                <a href="{{ route('account.affiliate.dashboard') }}" class="block rounded-xl border border-gray-200 bg-gray-50 p-5 transition hover:bg-gray-100" aria-label="Lihat status afiliasi">
                    <div class="flex items-center gap-3">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-gray-200">
                            <svg class="h-6 w-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $affIcon }}" />
                            </svg>
                        </span>
                        <div>
                            <p class="font-semibold text-gray-800">Status Afiliasi: {{ $aff->status->label() }}</p>
                            <p class="mt-0.5 text-sm text-gray-500">Klik untuk melihat detail program afiliasi Anda.</p>
                        </div>
                    </div>
                </a>
            @endif

            {{-- Stat tiles --}}
            <section aria-label="Ringkasan akun">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <a href="{{ route('account.orders') }}" class="card p-4 transition hover:shadow-md">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Pesanan</p>
                        <p class="mt-1 text-2xl font-bold text-brand-700">{{ $orderCount }}</p>
                    </a>
                    <a href="{{ route('account.quotations') }}" class="card p-4 transition hover:shadow-md">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Quotation</p>
                        <p class="mt-1 text-2xl font-bold text-brand-700">{{ $quotationCount }}</p>
                    </a>
                    <a href="{{ route('account.reviews') }}" class="card p-4 transition hover:shadow-md">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Review</p>
                        <p class="mt-1 text-2xl font-bold text-brand-700">{{ $reviewCount }}</p>
                    </a>
                    <a href="{{ route('account.notifications') }}" class="card p-4 transition hover:shadow-md">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Notifikasi</p>
                        <p class="mt-1 text-2xl font-bold {{ $unreadNotifications > 0 ? 'text-accent-600' : 'text-brand-700' }}">{{ $unreadNotifications }}</p>
                    </a>
                </div>
            </section>

            {{-- Recent orders --}}
            <section class="card overflow-hidden">
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                    <h2 class="font-semibold text-gray-800">Pesanan Terbaru</h2>
                    <a href="{{ route('account.orders') }}" class="text-sm font-medium text-brand-600 hover:underline">Lihat semua</a>
                </div>

                @if ($recentOrders->isEmpty())
                    <div class="px-4 py-10 text-center">
                        <p class="text-sm text-gray-500">Anda belum memiliki pesanan.</p>
                        <a href="{{ route('products.index') }}" class="btn-primary mt-4">Mulai Belanja</a>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[560px] text-sm">
                            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3 font-medium">No. Pesanan</th>
                                    <th class="px-4 py-3 font-medium">Tanggal</th>
                                    <th class="px-4 py-3 font-medium">Status</th>
                                    <th class="px-4 py-3 text-right font-medium">Total</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($recentOrders as $order)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 font-medium text-gray-800">{{ $order->order_number }}</td>
                                        <td class="px-4 py-3 text-gray-500">{{ $order->created_at->translatedFormat('d M Y') }}</td>
                                        <td class="px-4 py-3">
                                            <span class="badge bg-brand-100 text-brand-700">{{ $order->status->label() }}</span>
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
                @endif
            </section>
        </div>
    </div>
@endsection
