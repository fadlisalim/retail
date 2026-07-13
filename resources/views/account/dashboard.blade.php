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
