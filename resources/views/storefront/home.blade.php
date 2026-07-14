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
    {{-- 1. Hero banner --}}
    <section class="mt-2" x-data="{ active: 0 }">
        @if ($heroBanners->isNotEmpty())
            <div class="relative overflow-hidden rounded-2xl">
                @foreach ($heroBanners as $i => $banner)
                    <div x-show="active === {{ $i }}" x-transition class="relative">
                        @if ($banner->image_desktop_path)
                            {{-- Image banner: uploaded artwork as the hero, optional text overlay + link --}}
                            <a @if ($banner->button_url) href="{{ $banner->button_url }}" @endif class="relative block">
                                <picture>
                                    @if ($banner->image_mobile_path)
                                        <source media="(max-width: 640px)" srcset="{{ asset('storage/'.$banner->image_mobile_path) }}">
                                    @endif
                                    <img src="{{ asset('storage/'.$banner->image_desktop_path) }}" alt="{{ $banner->title ?: 'Banner' }}" class="h-52 w-full object-cover sm:h-80">
                                </picture>
                                @if ($banner->title || $banner->description)
                                    <div class="absolute inset-0 flex flex-col justify-center gap-2 bg-gradient-to-r from-black/60 via-black/25 to-transparent p-6 text-white sm:p-12">
                                        <span class="text-xs font-semibold uppercase tracking-wide text-accent-300">{{ $banner->subtitle }}</span>
                                        <h1 class="max-w-xl text-2xl font-extrabold drop-shadow sm:text-4xl">{{ $banner->title }}</h1>
                                        <p class="max-w-lg text-sm drop-shadow sm:text-base">{{ $banner->description }}</p>
                                        @if ($banner->button_url)
                                            <span class="btn-accent mt-2 w-fit">{{ $banner->button_text ?: 'Belanja Sekarang' }}</span>
                                        @endif
                                    </div>
                                @endif
                            </a>
                        @else
                            {{-- No image: gradient + text --}}
                            <div class="flex min-h-[220px] flex-col justify-center gap-3 bg-gradient-to-r from-brand-700 to-brand-500 p-6 text-white sm:min-h-[320px] sm:p-12">
                                <span class="text-xs font-semibold uppercase tracking-wide text-accent-300">{{ $banner->subtitle }}</span>
                                <h1 class="max-w-xl text-2xl font-extrabold sm:text-4xl">{{ $banner->title }}</h1>
                                <p class="max-w-lg text-sm text-brand-50 sm:text-base">{{ $banner->description }}</p>
                                @if ($banner->button_url)
                                    <a href="{{ $banner->button_url }}" class="btn-accent mt-2 w-fit">{{ $banner->button_text ?: 'Belanja Sekarang' }}</a>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
                @if ($heroBanners->count() > 1)
                    <div class="absolute bottom-4 left-1/2 flex -translate-x-1/2 gap-2">
                        @foreach ($heroBanners as $i => $b)
                            <button @click="active = {{ $i }}" :class="active === {{ $i }} ? 'bg-white' : 'bg-white/50'" class="h-2 w-2 rounded-full" aria-label="Banner {{ $i + 1 }}"></button>
                        @endforeach
                    </div>
                @endif
            </div>
        @else
            <div class="flex min-h-[220px] flex-col justify-center gap-3 rounded-2xl bg-gradient-to-r from-brand-700 to-brand-500 p-6 text-white sm:min-h-[300px] sm:p-12">
                <h1 class="max-w-xl text-2xl font-extrabold sm:text-4xl">Energi Cerdas, Tinggal Klik!</h1>
                <p class="max-w-lg text-sm text-brand-50">Panel surya, inverter, baterai lithium, paket PLTS, dan barang sisa proyek dengan harga terbaik.</p>
                <a href="{{ route('products.index') }}" class="btn-accent mt-2 w-fit">Belanja Sekarang</a>
            </div>
        @endif
    </section>

    {{-- 2. Grid banners (baris banner 1/2/3 kolom) --}}
    @if ($gridBanners->isNotEmpty())
        <section class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-6">
            @foreach ($gridBanners as $banner)
                <a @if ($banner->button_url) href="{{ $banner->button_url }}" @endif
                   class="group relative block overflow-hidden rounded-2xl {{ $banner->spanClass() }}">
                    @if ($banner->image_desktop_path)
                        <img src="{{ asset('storage/'.($banner->image_mobile_path ?: $banner->image_desktop_path)) }}"
                             alt="{{ $banner->title ?: 'Banner' }}"
                             class="h-40 w-full object-cover transition duration-300 group-hover:scale-105 sm:h-56">
                    @else
                        <div class="h-40 w-full bg-gradient-to-br from-brand-700 to-brand-500 sm:h-56"></div>
                    @endif
                    @if ($banner->title || $banner->description)
                        <div class="absolute inset-0 flex flex-col justify-end gap-1 bg-gradient-to-t from-black/65 to-transparent p-4 text-white">
                            <h3 class="text-lg font-bold drop-shadow">{{ $banner->title }}</h3>
                            @if ($banner->description)<p class="text-xs text-white/90 drop-shadow">{{ $banner->description }}</p>@endif
                            @if ($banner->button_url && $banner->button_text)<span class="mt-1 text-xs font-semibold text-accent-300">{{ $banner->button_text }} →</span>@endif
                        </div>
                    @endif
                </a>
            @endforeach
        </section>
    @endif

    {{-- 3. Category shortcuts --}}
    @if ($shortcutCategories->isNotEmpty())
        <section class="mt-6">
            <div class="grid grid-cols-3 gap-3 sm:grid-cols-6">
                @foreach ($shortcutCategories as $cat)
                    <a href="{{ route('categories.show', $cat->slug) }}" class="card flex flex-col items-center gap-2 p-3 text-center transition hover:border-brand-400 hover:shadow">
                        <span class="grid h-12 w-12 place-items-center rounded-full bg-brand-50 text-brand-600">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
                        </span>
                        <span class="text-xs font-medium text-gray-700">{{ $cat->name }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Trust bar --}}
    <section class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ([
            ['Produk Bersertifikat', 'Kualitas tier-1 & bergaransi resmi'],
            ['Konsultasi Teknis', 'Tim ahli PLTS siap membantu'],
            ['Harga Transparan', 'Termasuk info PPN & ongkir'],
            ['Layanan Instalasi', 'Survei & pemasangan profesional'],
        ] as [$t, $d])
            <div class="card flex items-start gap-2 p-3">
                <span class="mt-0.5 text-brand-500">✔</span>
                <div><p class="text-sm font-semibold text-gray-800">{{ $t }}</p><p class="text-xs text-gray-500">{{ $d }}</p></div>
            </div>
        @endforeach
    </section>

    {{-- 4-6 product rows --}}
    <x-product-row title="Produk Pilihan" :products="$featured" :view-all="route('products.index', ['featured' => 1])" />
    <x-product-row title="Paket PLTS Populer" subtitle="Solusi lengkap on-grid, off-grid, & hybrid" :products="$packages" :view-all="route('products.index', ['category' => 'paket-plts'])" />
    <x-product-row title="Produk Terbaru" :products="$newest" :view-all="route('products.new')" />

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

    {{-- 7. Promo & clearance banner + row --}}
    <x-product-row title="Promo & Clearance" :products="$promos" :view-all="route('promo')" />

    {{-- 8. Project surplus --}}
    <x-product-row title="Barang Sisa Proyek" subtitle="Kondisi jelas, harga hemat" :products="$surplus" :view-all="route('surplus')" />

    {{-- 9. Brands --}}
    @if ($brands->isNotEmpty())
        <section class="mt-8">
            <h2 class="mb-3 text-lg font-bold text-gray-900 sm:text-xl">Belanja Berdasarkan Brand</h2>
            <div class="grid grid-cols-3 gap-3 sm:grid-cols-6">
                @foreach ($brands as $brand)
                    <a href="{{ route('brands.show', $brand->slug) }}" class="card grid place-items-center p-4 text-center text-sm font-semibold text-gray-600 transition hover:border-brand-400 hover:text-brand-700">{{ $brand->name }}</a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- 10-11 most viewed / top rated --}}
    <x-product-row title="Paling Banyak Dilihat" :products="$mostViewed" />
    <x-product-row title="Rating Terbaik" :products="$topRated" />

    {{-- 13. Services / consultation --}}
    <section class="mt-10 grid gap-4 rounded-2xl bg-gray-900 p-6 text-white sm:grid-cols-2 sm:p-10">
        <div>
            <h2 class="text-xl font-bold sm:text-2xl">Butuh Solusi PLTS untuk Rumah atau Proyek?</h2>
            <p class="mt-2 text-sm text-gray-300">Tim Rekasurya siap membantu konsultasi kebutuhan, survei lokasi, hingga instalasi. Dapatkan penawaran khusus untuk pengadaan skala proyek.</p>
            <div class="mt-4 flex flex-wrap gap-3">
                <a href="{{ route('quotations.create') }}" class="btn-accent">Minta Penawaran</a>
                @if ($whatsappEnabled)
                    <a href="{{ whatsapp_link('Halo Rekasurya, saya ingin konsultasi kebutuhan PLTS.') }}" target="_blank" rel="noopener" class="btn-outline bg-white/10 text-white hover:bg-white/20">Konsultasi WhatsApp</a>
                @endif
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
@endsection
