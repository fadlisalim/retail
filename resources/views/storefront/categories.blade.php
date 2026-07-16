@extends('layouts.storefront')
@section('title', 'Kategori Produk — '.config('rekasurya.company.brand_name'))

@section('content')
    <x-breadcrumbs :items="[['label' => 'Kategori']]" />

    <div class="mb-5">
        <h1 class="text-xl font-bold text-gray-900 sm:text-2xl">Kategori Produk</h1>
        <p class="mt-1 text-sm text-gray-500">Jelajahi produk berdasarkan kategori.</p>
    </div>

    @if ($categories->isEmpty())
        <p class="text-sm text-gray-400">Belum ada kategori.</p>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($categories as $cat)
                <div class="card flex flex-col p-4">
                    <a href="{{ route('categories.show', $cat->slug) }}" class="group flex items-center gap-3">
                        <span class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-full bg-brand-50 text-brand-600">
                            @if ($cat->image_path)
                                <img src="{{ asset('storage/'.$cat->image_path) }}" alt="{{ $cat->name }}" class="h-full w-full object-cover">
                            @else
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
                            @endif
                        </span>
                        <span class="min-w-0">
                            <span class="block font-semibold text-gray-900 group-hover:text-brand-700">{{ $cat->name }}</span>
                            <span class="text-xs text-brand-600">Lihat semua →</span>
                        </span>
                    </a>

                    @if ($cat->activeChildren->isNotEmpty())
                        <ul class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1.5 border-t border-gray-100 pt-3">
                            @foreach ($cat->activeChildren as $child)
                                <li>
                                    <a href="{{ route('categories.show', $child->slug) }}" class="block truncate text-sm text-gray-600 hover:text-brand-700">{{ $child->name }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
@endsection
