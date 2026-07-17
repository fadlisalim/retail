@props(['product', 'compact' => false])
<div class="flex flex-wrap items-baseline gap-2">
    @if ($product->requires_quotation || $product->price_status === 'call_for_price')
        <span class="text-lg font-bold text-brand-700">Minta Penawaran</span>
    @else
        <span class="text-lg font-extrabold text-gray-900">{{ rupiah($product->effectivePrice()) }}</span>
        {{-- Compact (product cards): only the current price. No strike-through original,
             no inline discount pill — the discount is a red corner badge on the image. --}}
        @if ($product->isOnSale() && ! $compact)
            <span class="text-sm text-gray-400 line-through">{{ rupiah($product->price) }}</span>
            <span class="badge bg-red-100 text-red-700">-{{ $product->discountPercent() }}%</span>
        @endif
    @endif
</div>
@unless ($product->requires_quotation)
    @if ((bool) setting('tax.enabled', config('rekasurya.tax.enabled')))
        <p class="text-xs text-gray-400">{{ $product->price_includes_tax ? 'Termasuk PPN' : 'Belum termasuk PPN' }}</p>
    @endif
@endunless
