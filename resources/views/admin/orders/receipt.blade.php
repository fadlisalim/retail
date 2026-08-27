@extends('layouts.admin')

@section('title', 'Kuitansi '.$order->order_number)

@section('content')
    @php($company = app(\App\Services\SettingService::class)->company())

    <div class="mb-4 flex flex-wrap items-center justify-between gap-2 print:hidden">
        <a href="{{ route('admin.orders.show', $order) }}" class="text-sm font-medium text-brand-700 hover:underline">← Kembali ke pesanan</a>
        <div class="flex gap-2">
            @if ($order->invoice)
                <a href="{{ route('invoices.show', $order->invoice) }}" target="_blank" rel="noopener" class="btn-outline text-sm">Lihat Invoice</a>
            @endif
            <button type="button" onclick="window.print()" class="btn-primary text-sm">🖨️ Cetak Kuitansi</button>
        </div>
    </div>

    {{-- Receipt sheet: plain borders so it prints cleanly in black & white. --}}
    <div class="mx-auto max-w-3xl rounded-xl border border-gray-300 bg-white p-8 print:rounded-none print:border-0 print:p-0">
        <div class="flex items-start justify-between gap-6 border-b-2 border-gray-800 pb-4">
            <div>
                <h1 class="text-2xl font-extrabold tracking-wide text-gray-900">KUITANSI</h1>
                <p class="mt-0.5 text-sm text-gray-500">No. {{ $order->invoice?->invoice_number ?? $order->order_number }}</p>
            </div>
            <div class="text-right text-sm">
                <p class="text-base font-bold text-gray-900">{{ $company['legal_name'] ?? brand() }}</p>
                <p class="max-w-xs text-gray-600">{{ $company['address'] ?? '' }}</p>
                <p class="text-gray-600">{{ $company['phone'] ?? '' }} @if (! empty($company['email'])) · {{ $company['email'] }} @endif</p>
                @if (! empty($company['npwp']))<p class="text-gray-600">NPWP: {{ $company['npwp'] }}</p>@endif
            </div>
        </div>

        <dl class="mt-5 grid grid-cols-1 gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
            {{-- Bentuk BLOK, bukan @php(...) inline: inline akan salah
                 berpasangan dengan @endphp blok di bawah dan menelan markup. --}}
            @php
                $snap = (array) ($order->invoice?->customer_snapshot ?? []);
            @endphp
            <div class="flex gap-2">
                <dt class="w-36 shrink-0 text-gray-500">Telah diterima dari</dt>
                <dd class="font-semibold text-gray-900">
                    @if (! empty($snap['company']))
                        {{ $snap['company'] }}@if (! empty($snap['pic']) || ! empty($snap['name'])) <span class="font-normal text-gray-600">(u.p. {{ $snap['pic'] ?? $snap['name'] }})</span>@endif
                    @else
                        {{ $snap['name'] ?? $order->customer_name }}@if (! empty($snap['pic'])) <span class="font-normal text-gray-600">(u.p. {{ $snap['pic'] }})</span>@endif
                    @endif
                </dd>
            </div>
            <div class="flex gap-2"><dt class="w-36 shrink-0 text-gray-500">Tanggal bayar</dt><dd class="font-medium text-gray-800">{{ ($order->paid_at ?? $order->created_at)->translatedFormat('d F Y') }}</dd></div>
            <div class="flex gap-2"><dt class="w-36 shrink-0 text-gray-500">No. HP / WA</dt><dd class="text-gray-800">{{ $order->customer_phone ?? '—' }}</dd></div>
            <div class="flex gap-2"><dt class="w-36 shrink-0 text-gray-500">No. Pesanan</dt><dd class="text-gray-800">{{ $order->order_number }}</dd></div>
            <div class="flex gap-2"><dt class="w-36 shrink-0 text-gray-500">Metode bayar</dt><dd class="text-gray-800">{{ $order->payment_method ?? '—' }}</dd></div>
            <div class="flex gap-2">
                <dt class="w-36 shrink-0 text-gray-500">Kanal</dt>
                <dd class="text-gray-800">{{ $order->channelLabel() }}@if ($order->external_reference) · {{ $order->external_reference }}@endif</dd>
            </div>
        </dl>

        <table class="mt-6 w-full text-sm">
            <thead>
                <tr class="border-y border-gray-300 text-left text-xs uppercase tracking-wide text-gray-500">
                    <th class="py-2">Uraian</th>
                    <th class="py-2 text-center">Qty</th>
                    <th class="py-2 text-right">Harga</th>
                    <th class="py-2 text-right">Jumlah</th>
                </tr>
            </thead>
            @php
                // Kuitansi mengikuti DOKUMEN invoice (termasuk suntingan admin);
                // pesanan tanpa invoice memakai angka pesanan apa adanya.
                $docItems = $order->invoice?->lineItems() ?: $order->items->map(fn ($i) => [
                    'name' => $i->name, 'sku' => $i->sku, 'quantity' => (int) $i->quantity,
                    'unit_price' => (float) $i->unit_price, 'line_total' => (float) $i->line_total,
                ])->all();
                $docSubtotal = (float) ($order->invoice?->subtotal ?? $order->items_subtotal);
                $docDiscount = (float) ($order->invoice?->discount ?? ((float) $order->product_discount + (float) $order->coupon_discount));
                $docShipping = (float) ($order->invoice?->shipping ?? $order->shipping_cost);
                $docTax = (float) ($order->invoice?->tax ?? $order->tax_amount);
                $docTotal = (float) ($order->invoice?->total ?? $order->grand_total);
            @endphp
            <tbody class="divide-y divide-gray-100">
                @foreach ($docItems as $item)
                    <tr>
                        <td class="py-2 pr-2 text-gray-800">{{ $item['name'] }}</td>
                        <td class="py-2 text-center text-gray-600">{{ $item['quantity'] }}</td>
                        <td class="py-2 text-right text-gray-600">{{ rupiah((float) $item['unit_price']) }}</td>
                        <td class="py-2 text-right font-medium text-gray-800">{{ rupiah((float) $item['line_total']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="mt-4 flex justify-end">
            <dl class="w-full max-w-xs space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Subtotal</dt><dd>{{ rupiah($docSubtotal) }}</dd></div>
                @if ($docDiscount > 0)
                    <div class="flex justify-between"><dt class="text-gray-500">Diskon</dt><dd class="text-red-600">- {{ rupiah($docDiscount) }}</dd></div>
                @endif
                @if ($docShipping > 0)
                    <div class="flex justify-between"><dt class="text-gray-500">Ongkos kirim</dt><dd>{{ rupiah($docShipping) }}</dd></div>
                @endif
                @if ($docTax > 0)
                    <div class="flex justify-between"><dt class="text-gray-500">PPN</dt><dd>{{ rupiah($docTax) }}</dd></div>
                @endif
                <div class="flex justify-between border-t border-gray-300 pt-2 text-base font-bold text-gray-900">
                    <dt>Total dibayar</dt><dd>{{ rupiah($docTotal) }}</dd>
                </div>
            </dl>
        </div>

        <p class="mt-5 rounded-lg bg-gray-50 p-3 text-sm text-gray-700 print:bg-white print:px-0">
            Terbilang: <span class="font-semibold">{{ \App\Support\Terbilang::rupiah($docTotal) }}</span>
        </p>

        <div class="mt-8 flex items-end justify-between">
            <p class="text-xs text-gray-400">Kuitansi ini sah tanpa tanda tangan basah.</p>
            <div class="text-center text-sm">
                <p class="text-gray-600">{{ ($order->paid_at ?? now())->translatedFormat('d F Y') }}</p>
                <div class="h-16"></div>
                <p class="border-t border-gray-400 px-8 pt-1 font-medium text-gray-800">{{ $company['legal_name'] ?? brand() }}</p>
            </div>
        </div>
    </div>
@endsection
