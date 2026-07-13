@extends('layouts.admin')

@section('title', 'Dashboard')

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
        $maxSales = max(1, collect($salesChart)->max('value'));
    @endphp

    <x-admin.page-header title="Dashboard" subtitle="Ringkasan operasional toko" />

    {{-- KPI cards --}}
    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5">
        <x-admin.stat-card label="Pendapatan (Lunas)" :value="rupiah($totalRevenue)" color="green" />
        <x-admin.stat-card label="Total Pesanan" :value="number_format($orderCount, 0, ',', '.')" color="brand" />
        <x-admin.stat-card label="Belum Dibayar" :value="number_format($unpaidOrders, 0, ',', '.')" color="amber" sub="Menunggu pembayaran" />
        <x-admin.stat-card label="Perlu Diproses" :value="number_format($toProcess, 0, ',', '.')" color="blue" sub="Verified / proses / kemas" />
        <x-admin.stat-card label="Customer Baru" :value="number_format($newCustomers, 0, ',', '.')" color="green" sub="30 hari terakhir" />
        <x-admin.stat-card label="Stok Menipis" :value="number_format($lowStock, 0, ',', '.')" color="amber" />
        <x-admin.stat-card label="Stok Habis" :value="number_format($outOfStock, 0, ',', '.')" color="red" />
        <x-admin.stat-card label="Penawaran Baru" :value="number_format($pendingQuotations, 0, ',', '.')" color="blue" />
        <x-admin.stat-card label="Review Dilaporkan" :value="number_format($pendingReviews, 0, ',', '.')" color="red" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- Sales chart (last 14 days) --}}
        <div class="card p-5 lg:col-span-2">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-semibold text-gray-900">Pendapatan 14 Hari Terakhir</h2>
                <span class="text-xs text-gray-500">Total: {{ rupiah(collect($salesChart)->sum('value')) }}</span>
            </div>
            <div class="flex h-48 items-end gap-1.5">
                @foreach ($salesChart as $point)
                    @php $pct = (int) round(($point['value'] / $maxSales) * 100); @endphp
                    <div class="group flex flex-1 flex-col items-center justify-end">
                        <div class="relative w-full rounded-t bg-brand-500/80 transition hover:bg-brand-600"
                             style="height: {{ max($pct, 2) }}%"
                             title="{{ $point['label'] }}: {{ rupiah($point['value']) }}">
                            <span class="pointer-events-none absolute -top-6 left-1/2 hidden -translate-x-1/2 whitespace-nowrap rounded bg-gray-900 px-1.5 py-0.5 text-[10px] text-white group-hover:block">
                                {{ rupiah($point['value']) }}
                            </span>
                        </div>
                        <span class="mt-1 text-[10px] text-gray-400">{{ $point['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Orders by status --}}
        <div class="card p-5">
            <h2 class="mb-4 font-semibold text-gray-900">Pesanan per Status</h2>
            <ul class="space-y-2 text-sm">
                @forelse (\App\Enums\OrderStatus::cases() as $case)
                    @php $count = (int) ($ordersByStatus[$case->value] ?? 0); @endphp
                    @if ($count > 0)
                        <li class="flex items-center justify-between gap-2">
                            <span class="badge {{ $badgeClasses[$case->color()] ?? $badgeClasses['gray'] }}">{{ $case->label() }}</span>
                            <span class="font-semibold text-gray-700">{{ number_format($count, 0, ',', '.') }}</span>
                        </li>
                    @endif
                @empty
                    <li class="text-gray-400">Belum ada pesanan.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- Recent orders --}}
        <div class="card overflow-x-auto lg:col-span-2">
            <div class="flex items-center justify-between px-5 pt-5">
                <h2 class="font-semibold text-gray-900">Pesanan Terbaru</h2>
                <a href="{{ route('admin.orders.index') }}" class="text-sm text-brand-700 hover:underline">Semua pesanan &rarr;</a>
            </div>
            <table class="mt-3 w-full text-sm">
                <thead class="border-y border-gray-100 bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-5 py-2">No. Pesanan</th>
                        <th class="px-5 py-2">Pelanggan</th>
                        <th class="px-5 py-2">Status</th>
                        <th class="px-5 py-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($recentOrders as $order)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-2.5">
                                <a href="{{ route('admin.orders.show', $order) }}" class="font-medium text-brand-700 hover:underline">{{ $order->order_number }}</a>
                                <div class="text-xs text-gray-400">{{ $order->created_at?->format('d/m/Y H:i') }}</div>
                            </td>
                            <td class="px-5 py-2.5">{{ $order->customer_name ?? $order->user?->name ?? '—' }}</td>
                            <td class="px-5 py-2.5">
                                <span class="badge {{ $badgeClasses[$order->status->color()] ?? $badgeClasses['gray'] }}">{{ $order->status->label() }}</span>
                            </td>
                            <td class="px-5 py-2.5 text-right font-semibold">{{ rupiah($order->grand_total) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-8 text-center text-gray-400">Belum ada pesanan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Top products --}}
        <div class="card p-5">
            <h2 class="mb-4 font-semibold text-gray-900">Produk Terlaris</h2>
            <ul class="space-y-3">
                @forelse ($topProducts as $product)
                    <li class="flex items-center gap-3">
                        <img src="{{ $product->primaryImageUrl() }}" alt="{{ $product->name }}" class="h-10 w-10 flex-none rounded object-cover">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-800">{{ $product->name }}</p>
                            <p class="text-xs text-gray-400">{{ $product->sku }}</p>
                        </div>
                        <span class="flex-none text-sm font-semibold text-brand-700">{{ number_format($product->sold_count, 0, ',', '.') }} terjual</span>
                    </li>
                @empty
                    <li class="text-sm text-gray-400">Belum ada data penjualan.</li>
                @endforelse
            </ul>
        </div>
    </div>
@endsection
