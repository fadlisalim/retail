@extends('layouts.storefront')
@section('title', config('rekasurya.company.brand_name').' — '.config('rekasurya.company.tagline'))

@push('head')
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    'name' => config('rekasurya.company.legal_name'),
    'url' => url('/'),
    'description' => config('rekasurya.company.tagline'),
], JSON_UNESCAPED_SLASHES) !!}
</script>
@endpush

@section('content')
{{-- Mobile reorders sections via flex `order`; desktop keeps source order (sm:block). --}}
<div class="flex flex-col sm:block">
    {{-- 1. Hero slider (auto-advance, arrows + dots) --}}
    <section class="order-1 mt-2 sm:order-none">
        @if ($heroBanners->isNotEmpty())
            <x-banner-slider :banners="$heroBanners" />
        @else
            <div class="-mx-4 flex min-h-[220px] flex-col justify-center gap-3 rounded-none bg-gradient-to-r from-brand-700 to-brand-500 p-6 text-white sm:mx-0 sm:min-h-[300px] sm:rounded-2xl sm:p-12">
                <h1 class="max-w-xl text-2xl font-extrabold sm:text-4xl">Energi Cerdas, Tinggal Klik!</h1>
                <p class="max-w-lg text-sm text-brand-50">Panel surya, inverter, baterai lithium, paket PLTS, dan barang sisa proyek dengan harga terbaik.</p>
                <a href="{{ route('products.index') }}" class="btn-accent mt-2 w-fit">Belanja Sekarang</a>
            </div>
        @endif
    </section>

    {{-- Customer journey selector — help retail vs project buyers pick a path. --}}
    <section class="order-2 mt-6 sm:order-none" aria-labelledby="journey-heading">
        <h2 id="journey-heading" class="sr-only">Mulai dari kebutuhan Anda</h2>
        {{-- Mobile: thin single-line rows (icon + title + chevron). Desktop: full cards. --}}
        <div class="grid gap-2 sm:grid-cols-3 sm:gap-3">
            {{-- Beli produk --}}
            <a href="{{ route('products.index') }}"
               class="card group flex items-center gap-3 p-3 transition hover:border-brand-400 hover:shadow-md sm:items-start sm:p-4 sm:hover:-translate-y-0.5">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600 sm:h-11 sm:w-11">
                    <svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/></svg>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block font-semibold text-gray-900">Beli Produk</span>
                    <span class="mt-0.5 hidden text-sm text-gray-500 sm:block">Sudah tahu produk yang dibutuhkan? Belanja langsung dari katalog.</span>
                    <span class="mt-2 hidden items-center gap-1 text-sm font-medium text-brand-600 group-hover:gap-1.5 sm:inline-flex">Lihat Katalog
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    </span>
                </span>
                <svg class="h-5 w-5 shrink-0 text-gray-300 sm:hidden" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            </a>
            {{-- Pasang PLTS --}}
            <a href="{{ route('categories.show', 'paket-plts') }}"
               class="card group flex items-center gap-3 p-3 transition hover:border-brand-400 hover:shadow-md sm:items-start sm:p-4 sm:hover:-translate-y-0.5">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600 sm:h-11 sm:w-11">
                    <svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/></svg>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block font-semibold text-gray-900">Pasang PLTS</span>
                    <span class="mt-0.5 hidden text-sm text-gray-500 sm:block">Solusi lengkap tenaga surya untuk rumah, kantor, atau toko.</span>
                    <span class="mt-2 hidden items-center gap-1 text-sm font-medium text-brand-600 group-hover:gap-1.5 sm:inline-flex">Pilih Paket PLTS
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    </span>
                </span>
                <svg class="h-5 w-5 shrink-0 text-gray-300 sm:hidden" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            </a>
            {{-- Kebutuhan proyek --}}
            <a href="{{ route('quotations.create') }}"
               class="card group flex items-center gap-3 p-3 transition hover:border-accent-400 hover:shadow-md sm:items-start sm:p-4 sm:hover:-translate-y-0.5">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-accent-500/10 text-accent-600 sm:h-11 sm:w-11">
                    <svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block font-semibold text-gray-900">Kebutuhan Proyek</span>
                    <span class="mt-0.5 hidden text-sm text-gray-500 sm:block">Untuk kontraktor, perusahaan, & pengadaan skala besar.</span>
                    <span class="mt-2 hidden items-center gap-1 text-sm font-medium text-accent-600 group-hover:gap-1.5 sm:inline-flex">Minta Penawaran
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    </span>
                </span>
                <svg class="h-5 w-5 shrink-0 text-gray-300 sm:hidden" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            </a>
        </div>
    </section>

    {{-- CLEARANCE + PROMO — two side-by-side panels, right after the hero. --}}
    @if ($clearance->isNotEmpty() || $promos->isNotEmpty())
        <section class="order-2 -mx-4 mt-6 grid gap-4 sm:mx-0 sm:order-none lg:grid-cols-2">
            {{-- Clearance panel --}}
            @if ($clearance->isNotEmpty())
                <div class="overflow-hidden border-y border-red-200 bg-gradient-to-br from-red-50 via-orange-50 to-white p-4 sm:rounded-2xl sm:border sm:p-5"
                     x-data="{ scroll(dir) { const t = $refs.track; t.scrollBy({ left: dir * (t.clientWidth * 0.85), behavior: 'smooth' }); } }">
                    <div class="mb-3 flex items-end justify-between gap-2">
                        <div class="min-w-0">
                            <span class="inline-flex items-center gap-1 rounded-full bg-red-600 px-3 py-1 text-sm font-extrabold uppercase tracking-wide text-white shadow-sm">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/></svg>
                                Clearance
                            </span>
                            <p class="mt-1 truncate text-xs text-gray-600">Stok terbatas • <span class="font-semibold text-red-600">Termurah!</span></p>
                        </div>
                        <a href="{{ route('clearance') }}" class="shrink-0 text-xs font-semibold text-red-600 hover:underline">Lihat semua →</a>
                    </div>
                    <div x-ref="track" class="flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-smooth pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        @foreach ($clearance as $product)
                            <div class="w-[47%] shrink-0 snap-start sm:w-[31%] lg:w-[47%] xl:w-[31%]">
                                <x-product-card :product="$product" />
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Promo panel --}}
            @if ($promos->isNotEmpty())
                <div class="overflow-hidden border-y border-brand-200 bg-gradient-to-br from-brand-50 via-teal-50 to-white p-4 sm:rounded-2xl sm:border sm:p-5"
                     x-data="{ scroll(dir) { const t = $refs.track; t.scrollBy({ left: dir * (t.clientWidth * 0.85), behavior: 'smooth' }); } }">
                    <div class="mb-3 flex items-end justify-between gap-2">
                        <div class="min-w-0">
                            <span class="inline-flex items-center gap-1 rounded-full bg-brand-600 px-3 py-1 text-sm font-extrabold uppercase tracking-wide text-white shadow-sm">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/></svg>
                                Promo &amp; Penawaran
                            </span>
                            <p class="mt-1 truncate text-xs text-gray-600">Harga spesial • <span class="font-semibold text-brand-700">Hemat lebih banyak!</span></p>
                        </div>
                        <a href="{{ route('promo') }}" class="shrink-0 text-xs font-semibold text-brand-700 hover:underline">Lihat semua →</a>
                    </div>
                    <div x-ref="track" class="flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-smooth pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        @foreach ($promos as $product)
                            <div class="w-[47%] shrink-0 snap-start sm:w-[31%] lg:w-[47%] xl:w-[31%]">
                                <x-product-card :product="$product" />
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>
    @endif

    {{-- 2. Grid banners (baris banner 1/2/3 kolom) --}}
    @if ($gridBanners->isNotEmpty())
        <section class="order-4 -mx-4 mt-6 grid grid-cols-1 items-start gap-4 sm:mx-0 sm:order-none sm:grid-cols-6">
            @foreach ($gridBanners as $banner)
                <a @if ($banner->button_url) href="{{ $banner->button_url }}" @endif
                   class="group relative block overflow-hidden rounded-none sm:rounded-2xl {{ $banner->spanClass() }}">
                    @if ($banner->image_desktop_path)
                        <picture>
                            @if ($banner->image_mobile_path)
                                <source media="(max-width: 640px)" srcset="{{ asset('storage/'.$banner->image_mobile_path) }}">
                            @endif
                            {{-- Show the full artwork (no crop) — the uploaded image defines its own ratio (mis. 3:1 / 2:1). --}}
                            <img src="{{ asset('storage/'.$banner->image_desktop_path) }}"
                                 alt="{{ $banner->title ?: 'Banner' }}"
                                 class="block h-auto w-full transition duration-300 group-hover:scale-105">
                        </picture>
                    @else
                        <div class="h-40 w-full bg-gradient-to-br from-brand-700 to-brand-500 sm:h-56"></div>
                    @endif
                </a>
            @endforeach
        </section>
    @endif

    {{-- 3. Kategori — semua kategori, scrollable horizontal --}}
    @if ($shortcutCategories->isNotEmpty())
        <section class="order-5 mt-8 sm:order-none">
            <div class="mb-3 flex items-end justify-between">
                <h2 class="text-lg font-bold text-gray-900 sm:text-xl">Kategori</h2>
                <a href="{{ route('categories.index') }}" class="text-sm font-medium text-brand-600 hover:underline">Semua kategori →</a>
            </div>
            <div class="flex snap-x gap-3 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                @foreach ($shortcutCategories as $cat)
                    <a href="{{ route('categories.show', $cat->slug) }}" class="group flex w-[4.5rem] shrink-0 snap-start flex-col items-center gap-2 text-center sm:w-24">
                        <span class="grid h-16 w-16 place-items-center overflow-hidden rounded-full bg-brand-50 text-brand-600 ring-1 ring-brand-100 transition group-hover:bg-brand-100 sm:h-20 sm:w-20">
                            @if ($cat->image_path ?? false)
                                <img src="{{ asset('storage/'.$cat->image_path) }}" alt="{{ $cat->name }}" class="h-full w-full object-cover">
                            @else
                                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
                            @endif
                        </span>
                        <span class="line-clamp-2 text-xs font-medium leading-tight text-gray-700">{{ $cat->name }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Trust bar --}}
    <section class="order-6 mt-6 grid grid-cols-2 gap-3 sm:order-none sm:grid-cols-4">
        @foreach ([
            ['Produk Bersertifikat', 'Kualitas tier-1 & bergaransi resmi'],
            ['Konsultasi Teknis', 'Tim ahli PLTS siap membantu'],
            ['Harga Transparan', 'Harga sudah termasuk pajak'],
            ['Layanan Instalasi', 'Survei & pemasangan profesional'],
        ] as [$t, $d])
            <div class="card flex items-start gap-2 p-3">
                <span class="mt-0.5 text-brand-500">✔</span>
                <div><p class="text-sm font-semibold text-gray-800">{{ $t }}</p><p class="text-xs text-gray-500">{{ $d }}</p></div>
            </div>
        @endforeach
    </section>

    {{-- Produk Terpopuler — di mobile tepat setelah band Clearance --}}
    <div class="order-3 sm:order-none">
        <x-product-carousel title="Produk Terpopuler" :products="$featured" :view-all="route('products.index', ['featured' => 1])" />
    </div>

    {{-- Sisa section — urutan sumber dipakai apa adanya di desktop --}}
    <div class="order-7 sm:order-none">
    {{-- Akses cepat ke katalog lengkap --}}
    <div class="mt-8 flex justify-center">
        <a href="{{ route('products.index') }}"
           class="inline-flex items-center gap-2 rounded-xl border border-brand-200 bg-brand-50 px-8 py-3 text-base font-semibold text-brand-700 transition hover:border-brand-400 hover:bg-brand-100">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
            Lihat Semua Produk
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
        </a>
    </div>

    <x-product-carousel title="Produk Terbaru" :products="$newest" :view-all="route('products.new')" />

    {{-- Video section (YouTube landscape/portrait) --}}
    @if ($videoBanners->isNotEmpty())
        <section class="mt-10">
            <h2 class="mb-4 text-lg font-bold text-gray-900 sm:text-xl">Video</h2>
            <div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-6">
                @foreach ($videoBanners as $banner)
                    @php($embed = $banner->youtubeEmbedUrl())
                    <div class="{{ $banner->spanClass() }}">
                        @if ($banner->title)<p class="mb-1 text-sm font-semibold text-gray-700">{{ $banner->title }}</p>@endif
                        @if ($embed)
                            <div class="relative mx-auto w-full overflow-hidden rounded-2xl bg-black {{ $banner->is_portrait ? 'aspect-[9/16] max-w-xs' : 'aspect-video' }}">
                                <iframe src="{{ $embed }}" class="absolute inset-0 h-full w-full" title="{{ $banner->title ?: 'Video' }}" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                            </div>
                        @elseif ($banner->button_url)
                            <a href="{{ $banner->button_url }}" target="_blank" rel="noopener" class="text-sm text-brand-600 hover:underline">▶ {{ $banner->title ?: 'Tonton video' }}</a>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- 9. Brand Terpopuler --}}
    @if ($brands->isNotEmpty())
        <section class="mt-10">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="text-lg font-bold text-gray-900 sm:text-xl">Brand Terpopuler</h2>
                <a href="{{ route('brands.index') }}" class="text-sm font-medium text-brand-600 hover:underline">Semua brand →</a>
            </div>
            <div class="grid grid-cols-3 gap-3 sm:grid-cols-6">
                @foreach ($brands as $brand)
                    <a href="{{ route('brands.show', $brand->slug) }}" class="card grid h-20 place-items-center p-4 text-center transition hover:-translate-y-0.5 hover:border-brand-400 hover:shadow-md">
                        @if ($brand->logo_path ?? false)
                            <img src="{{ asset('storage/'.$brand->logo_path) }}" alt="{{ $brand->name }}" class="max-h-12 max-w-full object-contain">
                        @else
                            <span class="text-sm font-semibold text-gray-600 transition group-hover:text-brand-700">{{ $brand->name }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- 10-11 most viewed / top rated --}}
    <x-product-carousel title="Paling Banyak Dilihat" :products="$mostViewed" />
    <x-product-carousel title="Rating Terbaik" :products="$topRated" />

    {{-- 13. Services / consultation --}}
    <section class="mt-10 grid gap-4 rounded-2xl bg-gray-900 p-6 text-white sm:grid-cols-2 sm:p-10">
        <div>
            <h2 class="text-xl font-bold sm:text-2xl">Butuh Solusi PLTS untuk Rumah atau Proyek?</h2>
            <p class="mt-2 text-sm text-gray-300">Tim Rekasurya siap membantu konsultasi kebutuhan, survei lokasi, hingga instalasi. Dapatkan penawaran khusus untuk pengadaan skala proyek.</p>
            <div class="mt-4 flex flex-wrap gap-3">
                <a href="{{ route('quotations.create') }}" class="btn-accent">Minta Penawaran</a>
            </div>
        </div>
        @if ($quotationBanner)
            <div class="rounded-xl bg-white/5 p-5">
                <p class="text-sm font-semibold text-accent-300">{{ $quotationBanner->subtitle }}</p>
                <p class="mt-1 text-lg font-bold">{{ $quotationBanner->title }}</p>
                <p class="mt-1 text-sm text-gray-300">{{ $quotationBanner->description }}</p>
            </div>
        @endif
    </section>

    {{-- 15. Articles --}}
    @if ($articles->isNotEmpty())
        <section class="mt-10">
            <div class="mb-3 flex items-end justify-between">
                <h2 class="text-lg font-bold text-gray-900 sm:text-xl">Panduan &amp; Artikel Energi Surya</h2>
                <a href="{{ route('articles.index') }}" class="text-sm font-medium text-brand-600 hover:underline">Semua artikel →</a>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach ($articles as $article)
                    <a href="{{ route('articles.show', $article->slug) }}" class="card overflow-hidden transition hover:shadow-md">
                        <div class="aspect-video bg-brand-50"></div>
                        <div class="p-4">
                            <p class="text-xs text-gray-400">{{ $article->published_at?->translatedFormat('d M Y') }}</p>
                            <h3 class="mt-1 line-clamp-2 font-semibold text-gray-800">{{ $article->title }}</h3>
                            <p class="mt-1 line-clamp-2 text-sm text-gray-500">{{ $article->excerpt }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- 16. Testimonials --}}
    @if ($testimonials->isNotEmpty())
        <section class="mt-10">
            <h2 class="mb-3 text-lg font-bold text-gray-900 sm:text-xl">Kata Pelanggan Kami</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($testimonials as $review)
                    <figure class="card p-4">
                        <x-stars :rating="$review->rating" />
                        <blockquote class="mt-2 line-clamp-4 text-sm text-gray-600">"{{ $review->comment }}"</blockquote>
                        <figcaption class="mt-3 text-xs font-medium text-gray-500">{{ $review->user?->name ?? 'Pelanggan' }} • {{ $review->product?->name }}</figcaption>
                    </figure>
                @endforeach
            </div>
        </section>
    @endif
    </div>{{-- /order-6 sisa section --}}
</div>{{-- /flex wrapper --}}
@endsection
