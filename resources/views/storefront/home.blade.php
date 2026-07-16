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
            <div class="group relative overflow-hidden rounded-2xl"
                 x-data="{
                    active: 0,
                    count: {{ $heroBanners->count() }},
                    timer: null,
                    go(i) { this.active = (i + this.count) % this.count; },
                    next() { this.go(this.active + 1); },
                    prev() { this.go(this.active - 1); },
                    start() { if (this.count > 1) this.timer = setInterval(() => this.next(), 6000); },
                    stop() { clearInterval(this.timer); },
                 }"
                 x-init="start()"
                 @mouseenter="stop()" @mouseleave="start()">
                @foreach ($heroBanners as $i => $banner)
                    <div x-show="active === {{ $i }}" x-transition.opacity.duration.500ms class="relative" @if ($i !== 0) style="display:none" @endif>
                        @if ($banner->image_desktop_path)
                            {{-- Image-only banner: the uploaded artwork IS the design, just wrap it in the link. --}}
                            <a @if ($banner->button_url) href="{{ $banner->button_url }}" @endif class="block">
                                <picture>
                                    @if ($banner->image_mobile_path)
                                        <source media="(max-width: 640px)" srcset="{{ asset('storage/'.$banner->image_mobile_path) }}">
                                    @endif
                                    {{-- Show the artwork in full (no crop) so nothing is cut off on mobile. --}}
                                    <img src="{{ asset('storage/'.$banner->image_desktop_path) }}" alt="{{ $banner->title ?: 'Banner' }}" class="block h-auto w-full">
                                </picture>
                            </a>
                        @else
                            {{-- No image: gradient + text --}}
                            <div class="flex min-h-[220px] flex-col justify-center gap-3 bg-gradient-to-r from-brand-700 to-brand-500 p-6 text-white sm:min-h-[384px] sm:p-12">
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
                    {{-- Prev / next arrows (appear on hover, always tappable on mobile) --}}
                    <button type="button" @click="prev()" aria-label="Banner sebelumnya"
                            class="absolute left-3 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-full bg-white/80 text-gray-800 shadow transition hover:bg-white sm:opacity-0 sm:group-hover:opacity-100">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
                    </button>
                    <button type="button" @click="next()" aria-label="Banner berikutnya"
                            class="absolute right-3 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-full bg-white/80 text-gray-800 shadow transition hover:bg-white sm:opacity-0 sm:group-hover:opacity-100">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                    </button>
                    {{-- Dots --}}
                    <div class="absolute bottom-4 left-1/2 flex -translate-x-1/2 gap-2">
                        @foreach ($heroBanners as $i => $b)
                            <button type="button" @click="go({{ $i }})" :class="active === {{ $i }} ? 'w-6 bg-white' : 'w-2 bg-white/60'" class="h-2 rounded-full transition-all" aria-label="Banner {{ $i + 1 }}"></button>
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
        <section class="order-3 mt-6 grid grid-cols-1 gap-4 sm:order-none sm:grid-cols-6">
            @foreach ($gridBanners as $banner)
                <a @if ($banner->button_url) href="{{ $banner->button_url }}" @endif
                   class="group relative block overflow-hidden rounded-2xl {{ $banner->spanClass() }}">
                    @if ($banner->image_desktop_path)
                        <picture>
                            @if ($banner->image_mobile_path)
                                <source media="(max-width: 640px)" srcset="{{ asset('storage/'.$banner->image_mobile_path) }}">
                            @endif
                            <img src="{{ asset('storage/'.$banner->image_desktop_path) }}"
                                 alt="{{ $banner->title ?: 'Banner' }}"
                                 class="h-40 w-full object-cover transition duration-300 group-hover:scale-105 sm:h-56">
                        </picture>
                    @else
                        <div class="h-40 w-full bg-gradient-to-br from-brand-700 to-brand-500 sm:h-56"></div>
                    @endif
                </a>
            @endforeach
        </section>
    @endif

    {{-- 3. Kategori Unggulan --}}
    @if ($shortcutCategories->isNotEmpty())
        <section class="order-4 mt-8 sm:order-none">
            <div class="mb-3 flex items-end justify-between">
                <h2 class="text-lg font-bold text-gray-900 sm:text-xl">Kategori Unggulan</h2>
                <a href="{{ route('categories.index') }}" class="text-sm font-medium text-brand-600 hover:underline">Semua kategori →</a>
            </div>
            <div class="grid grid-cols-3 gap-3 sm:grid-cols-6">
                @foreach ($shortcutCategories as $cat)
                    <a href="{{ route('categories.show', $cat->slug) }}" class="card group flex flex-col items-center gap-2 p-3 text-center transition hover:-translate-y-0.5 hover:border-brand-400 hover:shadow-md">
                        <span class="grid h-14 w-14 place-items-center overflow-hidden rounded-full bg-brand-50 text-brand-600 transition group-hover:bg-brand-100">
                            @if ($cat->image_path ?? false)
                                <img src="{{ asset('storage/'.$cat->image_path) }}" alt="{{ $cat->name }}" class="h-full w-full object-cover">
                            @else
                                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
                            @endif
                        </span>
                        <span class="line-clamp-2 text-xs font-medium text-gray-700">{{ $cat->name }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Trust bar --}}
    <section class="order-5 mt-6 grid grid-cols-2 gap-3 sm:order-none sm:grid-cols-4">
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

    {{-- Produk Terpopuler — di mobile naik ke atas (tepat setelah hero) --}}
    <div class="order-2 sm:order-none">
        <x-product-carousel title="Produk Terpopuler" :products="$featured" :view-all="route('products.index', ['featured' => 1])" />
    </div>

    {{-- Sisa section — urutan sumber dipakai apa adanya di desktop --}}
    <div class="order-6 sm:order-none">
    <x-product-carousel title="Paket PLTS Populer" subtitle="Solusi lengkap on-grid, off-grid, & hybrid" :products="$packages" :view-all="route('products.index', ['category' => 'paket-plts'])" />
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

    {{-- 7. Promo --}}
    <x-product-carousel title="Promo & Penawaran" :products="$promos" :view-all="route('promo')" />

    {{-- 8. CLEARANCE — dedicated, visually distinct band (barang sisa proyek, open box, bekas). --}}
    @if ($clearance->isNotEmpty())
        <section class="mt-10 overflow-hidden rounded-2xl border border-red-200 bg-gradient-to-br from-red-50 via-orange-50 to-white p-4 sm:p-6"
                 x-data="{ scroll(dir) { const t = $refs.clearanceTrack; t.scrollBy({ left: dir * (t.clientWidth * 0.85), behavior: 'smooth' }); } }">
            <div class="mb-4 flex items-end justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1 rounded-full bg-red-600 px-3 py-1 text-sm font-extrabold uppercase tracking-wide text-white shadow-sm">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/></svg>
                            Clearance
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-gray-600">Stok terbatas • Kondisi jelas • Harga miring</p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <a href="{{ route('clearance') }}" class="text-sm font-semibold text-red-600 hover:underline">Lihat semua →</a>
                    <div class="hidden items-center gap-1 sm:flex">
                        <button type="button" @click="scroll(-1)" aria-label="Sebelumnya" class="grid h-8 w-8 place-items-center rounded-full border border-red-200 bg-white text-red-600 transition hover:bg-red-600 hover:text-white">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
                        </button>
                        <button type="button" @click="scroll(1)" aria-label="Berikutnya" class="grid h-8 w-8 place-items-center rounded-full border border-red-200 bg-white text-red-600 transition hover:bg-red-600 hover:text-white">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                        </button>
                    </div>
                </div>
            </div>
            <div x-ref="clearanceTrack" class="flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-smooth pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                @foreach ($clearance as $product)
                    <div class="w-[46%] shrink-0 snap-start sm:w-[31%] md:w-[23%] lg:w-[18.5%]">
                        <x-product-card :product="$product" />
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- 9. Brand Terpopuler --}}
    @if ($brands->isNotEmpty())
        <section class="mt-10">
            <h2 class="mb-3 text-lg font-bold text-gray-900 sm:text-xl">Brand Terpopuler</h2>
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
    </div>{{-- /order-6 sisa section --}}
</div>{{-- /flex wrapper --}}
@endsection
