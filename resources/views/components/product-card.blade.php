@props(['product'])
<article class="card group flex flex-col overflow-hidden transition hover:shadow-md">
    <a href="{{ route('products.show', $product->slug) }}" class="relative block aspect-square overflow-hidden bg-gray-50">
        <img src="{{ $product->primaryImageUrl() }}" alt="{{ $product->name }}" loading="lazy"
             class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
        <div class="absolute left-2 top-2 flex flex-col gap-1">
            @foreach (array_slice($product->badges(), 0, 2) as $badge)
                <x-badge :label="$badge" />
            @endforeach
        </div>
    </a>
    <div class="flex flex-1 flex-col gap-1 p-3">
        @if ($product->brand)
            <a href="{{ route('brands.show', $product->brand->slug) }}" class="text-xs font-medium text-brand-600 hover:underline">{{ $product->brand->name }}</a>
        @endif
        <a href="{{ route('products.show', $product->slug) }}" class="line-clamp-2 min-h-[2.5rem] text-sm font-medium text-gray-800 hover:text-brand-700">{{ $product->name }}</a>

        @if ($product->rating_count > 0)
            <x-stars :rating="$product->rating_avg" :count="$product->rating_count" />
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

        @if ($product->requires_quotation)
            <a href="{{ route('quotations.create', ['produk' => $product->slug]) }}" class="btn-outline mt-3 w-full text-xs">Minta Penawaran</a>
        @elseif ($product->is_purchasable && $product->inStock() && $product->product_type !== 'variable')
            <form action="{{ route('cart.store') }}" method="POST" class="mt-3" @submit="$store.cart.submit($event)">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="quantity" value="1">
                <button class="btn-primary w-full text-xs">+ Keranjang</button>
            </form>
        @else
            <a href="{{ route('products.show', $product->slug) }}" class="btn-outline mt-3 w-full text-xs">Lihat Detail</a>
        @endif
    </div>
</article>
