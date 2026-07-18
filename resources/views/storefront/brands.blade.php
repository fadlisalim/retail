@extends('layouts.storefront')
@section('title', 'Brand — '.config('rekasurya.company.brand_name'))
@section('meta_description', 'Jelajahi produk energi surya berdasarkan brand: panel surya, inverter, baterai lithium, dan lainnya.')

@section('content')
    <x-breadcrumbs :items="[['label' => 'Brand']]" />

    <div class="mb-5">
        <h1 class="text-xl font-bold text-gray-900 sm:text-2xl">Belanja per Brand</h1>
        <p class="mt-1 text-sm text-gray-500">Pilih brand untuk melihat semua produknya.</p>
    </div>

    @if ($brands->isEmpty())
        <p class="text-sm text-gray-400">Belum ada brand.</p>
    @else
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($brands as $brand)
                <a href="{{ route('brands.show', $brand->slug) }}"
                   class="card group flex flex-col items-center justify-center gap-2 p-4 text-center transition hover:-translate-y-0.5 hover:border-brand-400 hover:shadow-md">
                    <span class="grid h-16 w-full place-items-center">
                        @if ($brand->logo_path)
                            <img src="{{ asset('storage/'.$brand->logo_path) }}" alt="{{ $brand->name }}" class="max-h-14 max-w-full object-contain">
                        @else
                            <span class="text-base font-bold text-gray-700 transition group-hover:text-brand-700">{{ $brand->name }}</span>
                        @endif
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold text-gray-900 group-hover:text-brand-700">{{ $brand->name }}</span>
                        <span class="text-xs text-gray-400">{{ $brand->products_count }} produk</span>
                    </span>
                </a>
            @endforeach
        </div>
    @endif
@endsection
