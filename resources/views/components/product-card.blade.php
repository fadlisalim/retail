@props(['product'])
<article class="card group flex flex-col overflow-hidden transition hover:shadow-md">
    <div class="relative">
        <a href="{{ route('products.show', $product->slug) }}" class="block aspect-square overflow-hidden bg-gray-50">
            <img src="{{ $product->primaryImageUrl() }}" alt="{{ $product->name }}" loading="lazy"
                 class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
        </a>
        {{-- Status badges only (Clearance/Promo/condition). The custom price tag is
             moved out of the image, to a compact chip near the price, to reduce clutter. --}}
        <div class="pointer-events-none absolute left-2 top-2 flex flex-col gap-1">
            @foreach (array_slice($product->badges(true, false), 0, 2) as $badge)
                <x-badge :label="$badge" />
            @endforeach
        </div>
        <form action="{{ route('wishlist.toggle', $product->slug) }}" method="POST" class="absolute right-2 top-2">
            @csrf
            <button type="submit" title="Simpan ke wishlist" aria-label="Simpan {{ $product->name }} ke wishlist"
                    class="grid h-8 w-8 place-items-center rounded-full bg-white/90 text-gray-500 shadow-sm transition hover:text-red-500 focus-visible:ring-2 focus-visible:ring-brand-500">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"/></svg>
            </button>
        </form>
    </div>
    <div class="flex flex-1 flex-col gap-1 p-3">
        @if ($product->brand)
            <a href="{{ route('brands.show', $product->brand->slug) }}" class="text-xs font-medium text-brand-600 hover:underline">{{ $product->brand->name }}</a>
        @endif
        <a href="{{ route('products.show', $product->slug) }}" class="line-clamp-2 min-h-[2.5rem] text-sm font-medium text-gray-800 hover:text-brand-700">{{ $product->name }}</a>

        @if ($product->rating_count > 0)
            <x-stars :rating="$product->rating_avg" :count="$product->rating_count" />
        @endif

        @if (filled($product->badge_text))
            {{-- Price-trust tag as a small inline chip (no longer overlapping the image). --}}
            <span class="mt-1 inline-flex w-fit items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-200">
                <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                {{ $product->badge_text }}
            </span>
        @endif

        <div class="mt-auto pt-2">
            <x-price :product="$product" />
        </div>

        <div class="mt-2 flex items-center gap-2 text-xs text-gray-400">
            @if ($product->inStock())
                <span class="inline-flex items-center gap-1 text-green-600"><span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>Stok tersedia</span>
            @elseif ($product->requires_quotation)
                <span class="text-teal-600">Via penawaran</span>
            @else
                <span class="text-red-500">Stok habis</span>
            @endif
            @if ($product->sold_count > 0)<span>• {{ $product->sold_count }} terjual</span>@endif
        </div>

        {{-- Two actions on one row (mobile & desktop): a compact Detail + a context CTA.
             On small cards Detail is icon-only; on desktop both share the row equally. --}}
        <div class="mt-3 grid grid-cols-[auto_1fr] gap-2 lg:grid-cols-2">
            <a href="{{ route('products.show', $product->slug) }}" class="btn-outline whitespace-nowrap px-3 text-xs" aria-label="Lihat detail {{ $product->name }}">
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                <span class="hidden lg:inline">Detail</span>
            </a>

            @if ($product->requires_quotation)
                <a href="{{ route('quotations.create', ['produk' => $product->slug]) }}" class="btn-accent w-full whitespace-nowrap px-2 text-center text-xs">Penawaran</a>
            @elseif ($product->is_purchasable && $product->inStock() && $product->product_type !== 'variable')
                <form action="{{ route('cart.store') }}" method="POST" @submit="$store.cart.submit($event)">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <input type="hidden" name="quantity" value="1">
                    <button class="btn-primary w-full whitespace-nowrap px-2 text-xs">+ Keranjang</button>
                </form>
            @elseif ($product->is_purchasable && $product->inStock())
                <a href="{{ route('products.show', $product->slug) }}" class="btn-primary w-full whitespace-nowrap px-2 text-center text-xs">Pilih Varian</a>
            @else
                <button type="button" disabled class="btn-primary w-full cursor-not-allowed whitespace-nowrap px-2 text-xs opacity-50">Stok Habis</button>
            @endif
        </div>
    </div>
</article>
