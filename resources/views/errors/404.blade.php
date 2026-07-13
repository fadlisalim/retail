@extends('layouts.storefront')

@section('title', 'Halaman Tidak Ditemukan (404) — '.config('rekasurya.company.brand_name'))
@section('noindex', 'noindex')

@section('content')
    <div class="mx-auto max-w-xl py-12 text-center sm:py-20">
        <p class="text-7xl font-extrabold text-brand-600 sm:text-8xl">404</p>
        <h1 class="mt-4 text-2xl font-bold text-gray-900">Halaman tidak ditemukan</h1>
        <p class="mt-2 text-gray-500">Maaf, halaman yang Anda cari tidak tersedia atau mungkin telah dipindahkan.</p>

        {{-- Search --}}
        <form action="{{ route('search') }}" method="GET" role="search" class="mx-auto mt-8 flex max-w-md items-center gap-2">
            <label for="q" class="sr-only">Cari produk</label>
            <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="Cari panel surya, inverter, baterai…"
                   class="form-input flex-1 rounded-full">
            <button type="submit" class="btn-primary rounded-full">Cari</button>
        </form>

        {{-- Helpful links --}}
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <a href="{{ route('home') }}" class="btn-primary">Kembali ke Beranda</a>
            <a href="{{ route('products.index') }}" class="btn-outline">Semua Produk</a>
            <a href="{{ route('promo') }}" class="btn-outline">Promo</a>
            <a href="{{ route('faq') }}" class="btn-outline">Bantuan</a>
        </div>

        <p class="mt-10 text-sm text-gray-500">
            Jelajahi kategori produk kami melalui menu navigasi di bagian atas halaman,
            atau <a href="{{ route('quotations.create') }}" class="font-medium text-brand-600 hover:underline">ajukan permintaan penawaran</a>.
        </p>
    </div>
@endsection
