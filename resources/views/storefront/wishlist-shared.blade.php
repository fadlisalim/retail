@extends('layouts.storefront')

@section('title', 'Wishlist Dibagikan — '.config('rekasurya.company.brand_name'))
@section('noindex', 'noindex')

@section('content')
    <x-breadcrumbs :items="[['label' => 'Wishlist Dibagikan']]" />

    <header class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Wishlist Dibagikan</h1>
        <p class="mt-1 text-sm text-gray-500">Daftar produk pilihan yang dibagikan kepada Anda.</p>
    </header>

    @if ($wishlist->items->isEmpty())
        <div class="card px-4 py-16 text-center">
            <p class="text-sm text-gray-500">Wishlist ini kosong.</p>
            <a href="{{ route('products.index') }}" class="btn-primary mt-4">Jelajahi Produk</a>
        </div>
    @else
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($wishlist->items as $item)
                @continue(! $item->product)
                <x-product-card :product="$item->product" />
            @endforeach
        </div>
    @endif
@endsection
