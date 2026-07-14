@props(['product'])
<div class="flex flex-wrap items-baseline gap-2">
    @if ($product->requires_quotation || $product->price_status === 'call_for_price')
        <span class="text-lg font-bold text-brand-700">Minta Penawaran</span>
    @else
        <span class="text-lg font-extrabold text-gray-900">{{ rupiah($product->effectivePrice()) }}</span>
        @if ($product->isOnSale())
            <span class="text-sm text-gray-400 line-through">{{ rupiah($product->price) }}</span>
            <span class="badge bg-red-100 text-red-700">-{{ $product->discountPercent() }}%</span>
        @endif
    @endif
</div>
@unless ($product->requires_quotation)
    @php($ppnOn = (bool) setting('tax.enabled', config('rekasurya.tax.enabled')))
    <p class="text-xs text-gray-400">{{ ! $ppnOn || $product->price_includes_tax ? 'Termasuk PPN' : 'Belum termasuk PPN' }}</p>
@endunless
