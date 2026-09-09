@extends('layouts.admin')

@section('title', 'Pesanan')

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
        $activeStatus = request('status');
    @endphp

    <x-admin.page-header title="Pesanan" subtitle="Kelola dan pantau seluruh pesanan">
        <x-slot:actions>
            @can('order.manage')
                <a href="{{ route('admin.orders.create') }}" class="btn-primary">+ Input Pesanan Manual</a>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    {{-- Status tabs --}}
    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('admin.orders.index', array_filter(['q' => request('q'), 'payment_status' => request('payment_status')])) }}"
           class="rounded-full px-3 py-1.5 {{ ! $activeStatus ? 'bg-brand-600 font-semibold text-white' : 'bg-white text-gray-600 hover:bg-gray-100' }}">
            Semua <span class="ml-1 text-xs opacity-70">{{ number_format($totalCount, 0, ',', '.') }}</span>
        </a>
        @foreach (\App\Enums\OrderStatus::cases() as $case)
            @php $count = (int) ($statusCounts[$case->value] ?? 0); @endphp
            <a href="{{ route('admin.orders.index', array_filter(['status' => $case->value, 'q' => request('q'), 'payment_status' => request('payment_status')])) }}"
               class="rounded-full px-3 py-1.5 {{ $activeStatus === $case->value ? 'bg-brand-600 font-semibold text-white' : 'bg-white text-gray-600 hover:bg-gray-100' }}">
                {{ $case->label() }} <span class="ml-1 text-xs opacity-70">{{ $count }}</span>
            </a>
        @endforeach
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('admin.orders.index') }}" class="card mb-4 flex flex-wrap items-end gap-3 p-4">
        @if ($activeStatus)<input type="hidden" name="status" value="{{ $activeStatus }}">@endif
        <div class="min-w-52 flex-1">
            <label for="q" class="input-label">Cari</label>
            <input type="text" name="q" id="q" value="{{ request('q') }}" placeholder="No. pesanan / nama / email" class="form-input">
        </div>
        <div class="w-full sm:w-56">
            <label for="payment_status" class="input-label">Status Pembayaran</label>
            <select name="payment_status" id="payment_status" class="form-select">
                <option value="">Semua</option>
                @foreach (\App\Enums\PaymentStatus::options() as $opt)
                    <option value="{{ $opt['value'] }}" @selected(request('payment_status') === $opt['value'])>{{ $opt['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn-primary">Filter</button>
            <a href="{{ route('admin.orders.index') }}" class="btn-outline">Reset</a>
        </div>
    </form>

    {{-- Table --}}
    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">No. Pesanan</th>
                    <th class="px-4 py-3">Pelanggan</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Pembayaran</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-right">Tanggal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($orders as $order)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.orders.show', $order) }}" class="font-medium text-brand-700 hover:underline">{{ $order->order_number }}</a>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-gray-800">{{ $order->customer_name ?? $order->user?->name ?? '—' }}</div>
                            <div class="text-xs text-gray-400">{{ $order->customer_email }}</div>
                            @php
                                $waPhone = app(\App\Services\WhatsAppService::class)->normalize($order->customer_phone);
                            @endphp
                            @if ($waPhone)
                                <a href="https://wa.me/{{ $waPhone }}" target="_blank" rel="noopener"
                                   class="mt-0.5 inline-flex items-center gap-1 text-xs font-medium text-green-600 hover:underline"
                                   title="Buka WhatsApp pelanggan">💬 {{ $order->customer_phone }}</a>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="badge {{ $badgeClasses[$order->status->color()] ?? $badgeClasses['gray'] }}">{{ $order->status->label() }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="badge {{ $badgeClasses[$order->payment_status->color()] ?? $badgeClasses['gray'] }}">{{ $order->payment_status->label() }}</span>
                        </td>
                        <td class="px-4 py-3 text-right font-semibold">{{ rupiah($order->grand_total) }}</td>
                        <td class="px-4 py-3 text-right text-xs text-gray-400">{{ $order->created_at?->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">Tidak ada pesanan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $orders->links() }}</div>
@endsection
