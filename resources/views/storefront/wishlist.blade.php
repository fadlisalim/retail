@extends('layouts.storefront')

@section('title', 'Wishlist Saya — '.config('rekasurya.company.brand_name'))
@section('noindex', 'noindex')

@section('content')
    <x-breadcrumbs :items="[['label' => 'Wishlist']]" />

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-gray-900">Wishlist Saya</h1>
        @if ($wishlist->items->isNotEmpty())
            <form action="{{ route('wishlist.share') }}" method="POST">
                @csrf
                <button type="submit" class="btn-outline text-sm">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z" /></svg>
                    Bagikan Wishlist
                </button>
            </form>
        @endif
    </div>

    @if ($wishlist->items->isEmpty())
        <div class="card px-4 py-16 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" /></svg>
            <p class="mt-4 text-sm text-gray-500">Wishlist Anda masih kosong.</p>
            <a href="{{ route('products.index') }}" class="btn-primary mt-4">Jelajahi Produk</a>
        </div>
    @else
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($wishlist->items as $item)
                @continue(! $item->product)
                <div class="relative">
                    <form action="{{ route('wishlist.toggle', $item->product->slug) }}" method="POST" class="absolute right-2 top-2 z-10">
                        @csrf
                        <button type="submit" aria-label="Hapus dari wishlist"
                                class="grid h-8 w-8 place-items-center rounded-full bg-white/90 text-red-500 shadow hover:bg-red-50">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                        </button>
                    </form>
                    <x-product-card :product="$item->product" />
                </div>
            @endforeach
        </div>
    @endif
@endsection
