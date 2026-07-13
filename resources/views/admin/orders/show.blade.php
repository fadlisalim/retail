@extends('layouts.admin')

@section('title', 'Pesanan ' . $order->order_number)

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
        $addr = $order->shippingAddress;
    @endphp

    <x-admin.page-header :title="'Pesanan ' . $order->order_number" :subtitle="$order->created_at?->format('d M Y, H:i')">
        <x-slot:actions>
            <a href="{{ route('admin.orders.index') }}" class="btn-outline">&larr; Kembali</a>
            @if ($order->invoice)
                <a href="{{ route('invoices.show', $order->invoice) }}" target="_blank" class="btn-outline">Lihat Invoice ↗</a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mb-4 flex flex-wrap gap-2">
        <span class="badge {{ $badgeClasses[$order->status->color()] ?? $badgeClasses['gray'] }}">{{ $order->status->label() }}</span>
        <span class="badge {{ $badgeClasses[$order->payment_status->color()] ?? $badgeClasses['gray'] }}">{{ $order->payment_status->label() }}</span>
        @if ($order->shipping_cost_confirmed)<span class="badge bg-green-100 text-green-700">Ongkir Dikonfirmasi</span>@endif
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Main --}}
        <div class="space-y-6 lg:col-span-2">
            {{-- Items --}}
            <div class="card overflow-x-auto">
                <h2 class="px-5 pt-5 font-semibold text-gray-900">Item Pesanan</h2>
                <table class="mt-3 w-full text-sm">
                    <thead class="border-y border-gray-100 bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-5 py-2">Produk</th>
                            <th class="px-5 py-2 text-right">Harga</th>
                            <th class="px-5 py-2 text-center">Qty</th>
                            <th class="px-5 py-2 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($order->items as $item)
                            <tr>
                                <td class="px-5 py-3">
                                    <div class="font-medium text-gray-800">{{ $item->name }}</div>
                                    <div class="text-xs text-gray-400">SKU: {{ $item->sku }}</div>
                                </td>
                                <td class="px-5 py-3 text-right">{{ rupiah($item->unit_price) }}</td>
                                <td class="px-5 py-3 text-center">{{ $item->quantity }}</td>
                                <td class="px-5 py-3 text-right font-semibold">{{ rupiah($item->line_total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- Totals --}}
                <dl class="space-y-1.5 border-t border-gray-100 px-5 py-4 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Subtotal</dt><dd>{{ rupiah($order->items_subtotal) }}</dd></div>
                    @if ((float) $order->product_discount > 0)
                        <div class="flex justify-between text-green-600"><dt>Diskon Produk</dt><dd>- {{ rupiah($order->product_discount) }}</dd></div>
                    @endif
                    @if ((float) $order->coupon_discount > 0)
                        <div class="flex justify-between text-green-600"><dt>Diskon Kupon {{ $order->coupon_code ? '('.$order->coupon_code.')' : '' }}</dt><dd>- {{ rupiah($order->coupon_discount) }}</dd></div>
                    @endif
                    <div class="flex justify-between"><dt class="text-gray-500">Ongkir</dt><dd>{{ rupiah($order->shipping_cost) }}</dd></div>
                    @if ((float) $order->packing_fee > 0)
                        <div class="flex justify-between"><dt class="text-gray-500">Biaya Packing</dt><dd>{{ rupiah($order->packing_fee) }}</dd></div>
                    @endif
                    @if ((float) $order->handling_fee > 0)
                        <div class="flex justify-between"><dt class="text-gray-500">Biaya Handling</dt><dd>{{ rupiah($order->handling_fee) }}</dd></div>
                    @endif
                    @if ((float) $order->insurance_fee > 0)
                        <div class="flex justify-between"><dt class="text-gray-500">Asuransi</dt><dd>{{ rupiah($order->insurance_fee) }}</dd></div>
                    @endif
                    <div class="flex justify-between"><dt class="text-gray-500">PPN</dt><dd>{{ rupiah($order->tax_amount) }}</dd></div>
                    <div class="flex justify-between border-t border-gray-100 pt-2 text-base font-bold"><dt>Total</dt><dd class="text-brand-700">{{ rupiah($order->grand_total) }}</dd></div>
                    @if ((float) $order->paid_amount > 0)
                        <div class="flex justify-between text-gray-500"><dt>Sudah Dibayar</dt><dd>{{ rupiah($order->paid_amount) }}</dd></div>
                    @endif
                </dl>
            </div>

            {{-- Customer & shipping --}}
            <div class="grid gap-6 sm:grid-cols-2">
                <div class="card p-5">
                    <h2 class="mb-3 font-semibold text-gray-900">Pelanggan</h2>
                    <dl class="space-y-1 text-sm">
                        <div><dt class="inline text-gray-500">Nama:</dt> <dd class="inline">{{ $order->customer_name ?? '—' }}</dd></div>
                        <div><dt class="inline text-gray-500">Email:</dt> <dd class="inline">{{ $order->customer_email ?? '—' }}</dd></div>
                        <div><dt class="inline text-gray-500">Telepon:</dt> <dd class="inline">{{ $order->customer_phone ?? '—' }}</dd></div>
                        @if ($order->user)
                            <div class="pt-1"><a href="{{ route('admin.customers.show', $order->user) }}" class="text-brand-700 hover:underline">Lihat profil pelanggan &rarr;</a></div>
                        @endif
                    </dl>
                </div>
                <div class="card p-5">
                    <h2 class="mb-3 font-semibold text-gray-900">Alamat Pengiriman</h2>
                    @if ($addr)
                        <div class="space-y-0.5 text-sm text-gray-700">
                            <p class="font-medium">{{ $addr->recipient_name }} — {{ $addr->phone }}</p>
                            @if ($addr->company_name)<p class="text-gray-500">{{ $addr->company_name }}</p>@endif
                            <p>{{ $addr->fullAddress() }}</p>
                        </div>
                    @else
                        <p class="text-sm text-gray-400">Tidak ada alamat pengiriman.</p>
                    @endif
                    @if ($order->shipping_service_name)
                        <p class="mt-2 text-xs text-gray-500">Layanan: {{ $order->shipping_service_name }}</p>
                    @endif
                </div>
            </div>

            {{-- Payments --}}
            <div class="card overflow-x-auto">
                <h2 class="px-5 pt-5 font-semibold text-gray-900">Pembayaran</h2>
                <table class="mt-3 w-full text-sm">
                    <thead class="border-y border-gray-100 bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-5 py-2">Metode</th>
                            <th class="px-5 py-2">Status</th>
                            <th class="px-5 py-2 text-right">Jumlah</th>
                            <th class="px-5 py-2 text-right">Dibayar</th>
                            <th class="px-5 py-2 text-right">Tanggal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse ($order->payments as $payment)
                            <tr>
                                <td class="px-5 py-2.5">{{ $payment->method }}{{ $payment->provider ? ' / '.$payment->provider : '' }}</td>
                                <td class="px-5 py-2.5">{{ $payment->status }}</td>
                                <td class="px-5 py-2.5 text-right">{{ rupiah($payment->amount) }}</td>
                                <td class="px-5 py-2.5 text-right">{{ rupiah($payment->amount_paid) }}</td>
                                <td class="px-5 py-2.5 text-right text-xs text-gray-400">{{ $payment->paid_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-6 text-center text-gray-400">Belum ada pembayaran.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Reservations --}}
            @if ($order->reservations->isNotEmpty())
                <div class="card overflow-x-auto">
                    <h2 class="px-5 pt-5 font-semibold text-gray-900">Reservasi Stok</h2>
                    <table class="mt-3 w-full text-sm">
                        <thead class="border-y border-gray-100 bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-5 py-2">Produk</th>
                                <th class="px-5 py-2 text-center">Qty</th>
                                <th class="px-5 py-2">Status</th>
                                <th class="px-5 py-2 text-right">Kedaluwarsa</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($order->reservations as $res)
                                <tr>
                                    <td class="px-5 py-2.5">{{ $res->product?->name ?? '#'.$res->product_id }}</td>
                                    <td class="px-5 py-2.5 text-center">{{ $res->quantity }}</td>
                                    <td class="px-5 py-2.5">{{ $res->status }}</td>
                                    <td class="px-5 py-2.5 text-right text-xs text-gray-400">{{ $res->expires_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Sidebar: actions + timeline --}}
        <div class="space-y-6">
            @can('order.manage')
                {{-- Update status --}}
                <div class="card p-5">
                    <h2 class="mb-3 font-semibold text-gray-900">Ubah Status</h2>
                    <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label for="status" class="input-label">Status Baru</label>
                            <select name="status" id="status" class="form-select">
                                @foreach (\App\Enums\OrderStatus::options() as $opt)
                                    <option value="{{ $opt['value'] }}" @selected($order->status->value === $opt['value'])>{{ $opt['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="internal_note" class="input-label">Catatan Internal</label>
                            <textarea name="internal_note" id="internal_note" rows="2" class="form-textarea">{{ old('internal_note') }}</textarea>
                        </div>
                        <div>
                            <label for="customer_note" class="input-label">Catatan untuk Pelanggan</label>
                            <textarea name="customer_note" id="customer_note" rows="2" class="form-textarea">{{ old('customer_note') }}</textarea>
                        </div>
                        <button type="submit" class="btn-primary w-full">Simpan Status</button>
                    </form>
                </div>

                {{-- Confirm shipping cost --}}
                <div class="card p-5">
                    <h2 class="mb-3 font-semibold text-gray-900">Konfirmasi Ongkir</h2>
                    <form method="POST" action="{{ route('admin.orders.shipping', $order) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label for="shipping_cost" class="input-label">Biaya Ongkir (Rp)</label>
                            <input type="number" step="1" min="0" name="shipping_cost" id="shipping_cost" value="{{ old('shipping_cost', (int) $order->shipping_cost) }}" class="form-input">
                        </div>
                        <button type="submit" class="btn-accent w-full">Konfirmasi Ongkir</button>
                    </form>
                </div>

                {{-- Ship --}}
                <div class="card p-5">
                    <h2 class="mb-3 font-semibold text-gray-900">Kirim Pesanan</h2>
                    <form method="POST" action="{{ route('admin.orders.ship', $order) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label for="tracking_number" class="input-label">No. Resi <span class="text-red-500">*</span></label>
                            <input type="text" name="tracking_number" id="tracking_number" value="{{ old('tracking_number') }}" required class="form-input">
                        </div>
                        <div>
                            <label for="provider" class="input-label">Kurir</label>
                            <input type="text" name="provider" id="provider" value="{{ old('provider', $order->shipping_service_name) }}" class="form-input">
                        </div>
                        <button type="submit" class="btn-primary w-full">Tandai Dikirim</button>
                    </form>
                </div>
            @endcan

            @can('payment.manage')
                @unless ($order->payment_status->value === 'paid')
                    <div class="card p-5">
                        <h2 class="mb-2 font-semibold text-gray-900">Verifikasi Pembayaran</h2>
                        <p class="mb-3 text-xs text-gray-500">Tandai pembayaran sebagai lunas dan komit stok.</p>
                        <form method="POST" action="{{ route('admin.orders.verify', $order) }}" onsubmit="return confirm('Verifikasi pembayaran pesanan ini sebagai lunas?')">
                            @csrf
                            <button type="submit" class="btn-primary w-full">Verifikasi Lunas</button>
                        </form>
                    </div>
                @endunless
            @endcan

            {{-- Timeline --}}
            <div class="card p-5">
                <h2 class="mb-3 font-semibold text-gray-900">Riwayat Status</h2>
                <ol class="relative space-y-4 border-l border-gray-200 pl-4">
                    @forelse ($order->statusHistories as $history)
                        @php $hStatus = \App\Enums\OrderStatus::tryFrom($history->status); @endphp
                        <li class="relative">
                            <span class="absolute -left-[21px] top-1 h-2.5 w-2.5 rounded-full bg-brand-500 ring-2 ring-white"></span>
                            <div class="flex items-center gap-2">
                                <span class="badge {{ $badgeClasses[$hStatus?->color() ?? 'gray'] ?? $badgeClasses['gray'] }}">{{ $hStatus?->label() ?? $history->status }}</span>
                            </div>
                            <p class="mt-1 text-xs text-gray-400">
                                {{ $history->created_at?->format('d/m/Y H:i') }}
                                @if ($history->changedBy) — oleh {{ $history->changedBy->name }} @endif
                            </p>
                            @if ($history->customer_note)<p class="mt-1 text-sm text-gray-600">{{ $history->customer_note }}</p>@endif
                            @if ($history->internal_note)<p class="mt-0.5 text-xs italic text-gray-400">Internal: {{ $history->internal_note }}</p>@endif
                            @if ($history->tracking_number)<p class="mt-0.5 text-xs text-gray-500">Resi: {{ $history->tracking_number }}</p>@endif
                        </li>
                    @empty
                        <li class="text-sm text-gray-400">Belum ada riwayat.</li>
                    @endforelse
                </ol>
            </div>
        </div>
    </div>
@endsection
