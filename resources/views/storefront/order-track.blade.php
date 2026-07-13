@extends('layouts.storefront')
@section('title', 'Lacak Pesanan '.$order->order_number)
@section('noindex', 'noindex')

@section('content')
    <x-breadcrumbs :items="[['label' => 'Lacak Pesanan']]" />

    <div class="grid gap-6 lg:grid-cols-[1fr_340px]">
        <div class="space-y-5">
            <div class="card p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h1 class="text-lg font-bold text-gray-900">Pesanan {{ $order->order_number }}</h1>
                        <p class="text-sm text-gray-500">Dibuat {{ $order->created_at->translatedFormat('d M Y H:i') }}</p>
                    </div>
                    @php($statusColor = ['gray' => 'bg-gray-100 text-gray-700','amber' => 'bg-amber-100 text-amber-700','blue' => 'bg-blue-100 text-blue-700','green' => 'bg-green-100 text-green-700','red' => 'bg-red-100 text-red-700'][$order->statusEnum()->color()] ?? 'bg-gray-100 text-gray-700')
                    <span class="badge {{ $statusColor }}">{{ $order->statusEnum()->label() }}</span>
                </div>
                @if (! $order->shipping_cost_confirmed)
                    <p class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">Ongkir pesanan ini sedang dikonfirmasi oleh admin. Total dapat berubah sebelum pembayaran.</p>
                @endif
            </div>

            {{-- Timeline --}}
            <div class="card p-4">
                <h2 class="mb-3 font-semibold text-gray-800">Riwayat Status</h2>
                <ol class="relative border-l border-gray-200">
                    @foreach ($order->statusHistories as $history)
                        <li class="mb-4 ml-4">
                            <span class="absolute -left-1.5 mt-1 h-3 w-3 rounded-full bg-brand-500"></span>
                            <p class="text-sm font-medium text-gray-800">{{ \App\Enums\OrderStatus::tryFrom($history->status)?->label() ?? $history->status }}</p>
                            <p class="text-xs text-gray-400">{{ $history->created_at->translatedFormat('d M Y H:i') }}</p>
                            @if ($history->customer_note)<p class="text-sm text-gray-600">{{ $history->customer_note }}</p>@endif
                            @if ($history->tracking_number)<p class="text-sm text-brand-600">No. Resi: {{ $history->tracking_number }}</p>@endif
                        </li>
                    @endforeach
                </ol>
            </div>

            {{-- Items --}}
            <div class="card p-4">
                <h2 class="mb-3 font-semibold text-gray-800">Item Pesanan</h2>
                <div class="divide-y divide-gray-100">
                    @foreach ($order->items as $item)
                        <div class="flex items-center gap-3 py-2 text-sm">
                            <div class="flex-1"><p class="font-medium text-gray-800">{{ $item->name }}</p><p class="text-xs text-gray-400">{{ $item->quantity }} × {{ rupiah($item->unit_price) }}</p></div>
                            <span class="font-medium">{{ rupiah($item->line_total) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="space-y-5">
            <div class="card p-4">
                <h2 class="mb-3 font-semibold text-gray-800">Ringkasan</h2>
                <dl class="space-y-1 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Subtotal</dt><dd>{{ rupiah($order->items_subtotal) }}</dd></div>
                    @if ($order->coupon_discount > 0)<div class="flex justify-between text-green-600"><dt>Voucher</dt><dd>−{{ rupiah($order->coupon_discount) }}</dd></div>@endif
                    <div class="flex justify-between"><dt class="text-gray-500">Ongkir</dt><dd>{{ $order->shipping_cost_confirmed ? rupiah($order->shipping_cost) : 'Dikonfirmasi' }}</dd></div>
                    @if ($order->packing_fee > 0)<div class="flex justify-between"><dt class="text-gray-500">Packing</dt><dd>{{ rupiah($order->packing_fee) }}</dd></div>@endif
                    @if ($order->insurance_fee > 0)<div class="flex justify-between"><dt class="text-gray-500">Asuransi</dt><dd>{{ rupiah($order->insurance_fee) }}</dd></div>@endif
                    <div class="flex justify-between"><dt class="text-gray-500">PPN</dt><dd>{{ rupiah($order->tax_amount) }}</dd></div>
                </dl>
                <div class="mt-2 flex justify-between border-t border-gray-100 pt-2 font-bold"><span>Total</span><span class="text-brand-700">{{ rupiah($order->grand_total) }}</span></div>
                <p class="mt-2 text-sm">Status bayar: <span class="font-medium">{{ $order->payment_status->label() }}</span></p>

                <div class="mt-3 flex flex-col gap-2">
                    @if ($order->payment_status->value === 'unpaid' && $order->shipping_cost_confirmed)
                        <a href="{{ route('orders.pay', $order->public_token) }}" class="btn-primary">Bayar Sekarang</a>
                    @endif
                    @if ($order->invoice)
                        <a href="{{ route('invoices.show', $order->invoice->public_token) }}" class="btn-outline">Lihat Invoice</a>
                    @endif
                </div>
            </div>

            @if ($order->shippingAddress)
                <div class="card p-4 text-sm">
                    <h2 class="mb-2 font-semibold text-gray-800">Alamat Pengiriman</h2>
                    <p class="font-medium">{{ $order->shippingAddress->recipient_name }}</p>
                    <p class="text-gray-500">{{ $order->shippingAddress->phone }}</p>
                    <p class="text-gray-500">{{ $order->shippingAddress->fullAddress() }}</p>
                </div>
            @endif
        </div>
    </div>
@endsection
