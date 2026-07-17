@props(['product'])
@php($topBadge = array_values($product->badges())[0] ?? null)
{{-- Compact card: the whole card is a link (stretched over the title). No cart /
     detail buttons — actions happen on the product page. --}}
<article class="card group relative flex flex-col overflow-hidden transition hover:shadow-md">
    <div class="relative">
        <div class="block aspect-square overflow-hidden bg-gray-50">
            <img src="{{ $product->primaryImageUrl() }}" alt="{{ $product->name }}" loading="lazy"
                 class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
        </div>

        {{-- Top row: one priority badge (left) + discount (right). One flex row so
             they can never overlap; the left badge clips if unusually long. --}}
        <div class="pointer-events-none absolute inset-x-2 top-2 flex items-start justify-between gap-2">
            <div class="flex min-w-0 overflow-hidden">
                @if ($topBadge)<x-badge :label="$topBadge" />@endif
            </div>
            @if ($product->isOnSale())
                <span class="inline-flex shrink-0 items-center gap-0.5 rounded-md bg-red-600 py-0.5 pl-1 pr-1.5 text-xs font-bold text-white shadow-sm">
                    <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/></svg>
                    -{{ $product->discountPercent() }}%
                </span>
            @endif
        </div>

        {{-- Wishlist stays clickable above the stretched card link. --}}
        <form action="{{ route('wishlist.toggle', $product->slug) }}" method="POST" class="absolute bottom-2 right-2 z-10">
            @csrf
            <button type="submit" title="Simpan ke wishlist" aria-label="Simpan {{ $product->name }} ke wishlist"
                    class="grid h-8 w-8 place-items-center rounded-full bg-white/90 text-gray-500 shadow-sm transition hover:text-red-500 focus-visible:ring-2 focus-visible:ring-brand-500">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"/></svg>
            </button>
        </form>
    </div>

    <div class="flex flex-1 flex-col gap-1 p-3">
        @if ($product->brand)
            <a href="{{ route('brands.show', $product->brand->slug) }}" class="relative z-10 w-fit text-xs font-medium text-brand-600 hover:underline">{{ $product->brand->name }}</a>
        @endif
        {{-- Stretched link: makes the whole card tappable. --}}
        <a href="{{ route('products.show', $product->slug) }}" class="line-clamp-2 min-h-[2.5rem] text-sm font-medium text-gray-800 after:absolute after:inset-0 hover:text-brand-700">{{ $product->name }}</a>

        @if ($product->rating_count > 0)
            <x-stars :rating="$product->rating_avg" :count="$product->rating_count" />
        @endif

        <div class="mt-auto pt-2">
            <x-price :product="$product" compact />
        </div>

        <div class="mt-1.5 flex items-center gap-2 text-xs text-gray-400">
            @if ($product->inStock())
                <span class="inline-flex items-center gap-1 text-green-600"><span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>Stok tersedia</span>
            @elseif ($product->requires_quotation)
                <span class="text-teal-600">Via penawaran</span>
            @else
                <span class="text-red-500">Stok habis</span>
            @endif
            @if ($product->sold_count > 0)<span>• {{ $product->sold_count }} terjual</span>@endif
        </div>
    </div>
</article>
