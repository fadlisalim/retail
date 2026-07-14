@extends('layouts.storefront')

@section('title', 'Perbandingan Produk — '.config('rekasurya.company.brand_name'))
@section('noindex', 'noindex')

@section('content')
    <x-breadcrumbs :items="[['label' => 'Perbandingan']]" />

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-gray-900">Perbandingan Produk</h1>
        @if ($products->isNotEmpty())
            <form action="{{ route('compare.clear') }}" method="POST">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-outline text-sm">Kosongkan</button>
            </form>
        @endif
    </div>

    @if ($products->isEmpty())
        <div class="card px-4 py-16 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5" /></svg>
            <p class="mt-4 text-sm text-gray-500">Belum ada produk untuk dibandingkan.</p>
            <a href="{{ route('products.index') }}" class="btn-primary mt-4">Jelajahi Produk</a>
        </div>
    @else
        <div class="card overflow-x-auto">
            <table class="w-full min-w-[640px] text-sm">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th scope="col" class="w-40 bg-gray-50 p-4 text-left align-top text-xs font-medium uppercase tracking-wide text-gray-500">Produk</th>
                        @foreach ($products as $p)
                            <th scope="col" class="min-w-[200px] border-l border-gray-100 p-4 align-top">
                                <div class="relative">
                                    <form action="{{ route('compare.remove', $p->slug) }}" method="POST" class="absolute right-0 top-0">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" aria-label="Hapus dari perbandingan" class="grid h-7 w-7 place-items-center rounded-full text-gray-400 hover:bg-red-50 hover:text-red-500">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                        </button>
                                    </form>
                                    <a href="{{ route('products.show', $p->slug) }}" class="block">
                                        <img src="{{ $p->primaryImageUrl() }}" alt="{{ $p->name }}" loading="lazy" class="mx-auto h-28 w-28 rounded-lg border border-gray-100 object-cover">
                                        <span class="mt-3 block text-center text-sm font-semibold text-gray-800 hover:text-brand-700">{{ $p->name }}</span>
                                    </a>
                                    <div class="mt-3">
                                        @if ($p->requires_quotation)
                                            <a href="{{ route('quotations.create', ['produk' => $p->slug]) }}" class="btn-outline w-full text-xs">Minta Penawaran</a>
                                        @else
                                            <form action="{{ route('cart.store') }}" method="POST" @submit="$store.cart.submit($event)">
                                                @csrf
                                                <input type="hidden" name="product_id" value="{{ $p->id }}">
                                                <input type="hidden" name="quantity" value="1">
                                                <button type="submit" class="btn-primary w-full text-xs" @disabled(! $p->inStock())>{{ $p->inStock() ? '+ Keranjang' : 'Stok Habis' }}</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr>
                        <th scope="row" class="bg-gray-50 p-4 text-left font-medium text-gray-600">Brand</th>
                        @foreach ($products as $p)
                            <td class="border-l border-gray-100 p-4 text-gray-700">{{ $p->brand?->name ?? '—' }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <th scope="row" class="bg-gray-50 p-4 text-left font-medium text-gray-600">Harga</th>
                        @foreach ($products as $p)
                            <td class="border-l border-gray-100 p-4 font-semibold text-gray-900">
                                @if ($p->requires_quotation)
                                    <span class="text-brand-700">Minta Penawaran</span>
                                @else
                                    {{ rupiah($p->effectivePrice()) }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    <tr>
                        <th scope="row" class="bg-gray-50 p-4 text-left font-medium text-gray-600">Rating</th>
                        @foreach ($products as $p)
                            <td class="border-l border-gray-100 p-4">
                                @if ($p->rating_count > 0)
                                    <x-stars :rating="$p->rating_avg" :count="$p->rating_count" />
                                @else
                                    <span class="text-gray-400">Belum ada</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    <tr>
                        <th scope="row" class="bg-gray-50 p-4 text-left font-medium text-gray-600">Ketersediaan</th>
                        @foreach ($products as $p)
                            <td class="border-l border-gray-100 p-4">
                                @if ($p->inStock())
                                    <span class="inline-flex items-center gap-1 text-green-600"><span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>Tersedia</span>
                                @else
                                    <span class="text-red-500">Habis</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    <tr>
                        <th scope="row" class="bg-gray-50 p-4 text-left font-medium text-gray-600">Garansi</th>
                        @foreach ($products as $p)
                            <td class="border-l border-gray-100 p-4 text-gray-700">{{ $p->warranty ?: '—' }}</td>
                        @endforeach
                    </tr>

                    @foreach ($attributes as $attr)
                        @php
                            $distinct = $products
                                ->map(fn ($p) => $p->attributeValues->firstWhere('attribute_id', $attr->id)?->displayValue())
                                ->filter()
                                ->unique();
                            $differs = $distinct->count() > 1;
                        @endphp
                        <tr>
                            <th scope="row" class="bg-gray-50 p-4 text-left font-medium text-gray-600">{{ $attr->name }}</th>
                            @foreach ($products as $p)
                                @php($val = $p->attributeValues->firstWhere('attribute_id', $attr->id)?->displayValue())
                                <td class="border-l border-gray-100 p-4 {{ $differs ? 'bg-amber-50 font-medium text-gray-800' : 'text-gray-700' }}">{{ $val ?: '—' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-xs text-gray-400">Baris dengan latar kuning menandakan perbedaan spesifikasi antar produk.</p>
    @endif
@endsection
