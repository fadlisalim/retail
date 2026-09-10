@extends('layouts.storefront')
@section('title', $product->meta_title ?: $product->name)
@php
    $ogPriceLabel = $product->requires_quotation
        ? 'Minta penawaran'
        : rupiah($product->effectivePrice());
    $ogBaseDesc = $product->meta_description ?: \Illuminate\Support\Str::limit(strip_tags($product->short_description ?? ''), 130);
@endphp
@section('meta_description', $ogPriceLabel.' — '.$ogBaseDesc)
@if ($product->keywords)@section('meta_keywords', $product->keywords)@endif
@section('canonical', $product->canonical_url ?: route('products.show', $product->slug))
@section('og_type', 'product')
{{-- Landscape 1200x630 share card (product photo + name + price on a branded
     canvas). WhatsApp renders the LARGE preview for 1.91:1 images; a raw square
     product photo would only show as a small side thumbnail. --}}
@section('og_image', route('products.og', $product))
@section('og_image_type', 'image/png')
@section('og_image_width', '1200')
@section('og_image_height', '630')
@section('og_image_alt', $product->name)

@push('head')
<script type="application/ld+json">
{!! schema_ld([
    '@type' => 'Product',
    'name' => $product->name,
    'sku' => $product->sku,
    'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
    'description' => strip_tags($product->short_description ?? ''),
    'offers' => [
        '@type' => 'Offer',
        'priceCurrency' => 'IDR',
        'price' => $product->effectivePrice(),
        'availability' => $product->inStock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        'url' => route('products.show', $product->slug),
    ],
    'aggregateRating' => $product->rating_count > 0 ? [
        '@type' => 'AggregateRating',
        'ratingValue' => $product->rating_avg,
        'reviewCount' => $product->rating_count,
    ] : null,
]) !!}
</script>
@endpush

@push('head')
    <script>
        window.fbq && fbq('track', 'ViewContent', {
            content_type: 'product',
            content_ids: [@js($product->sku)],
            content_name: @js($product->name),
            value: {{ (float) $product->effectivePrice() }},
            currency: 'IDR',
        });
    </script>
@endpush

@php
    // Gallery media in sort order: photos + short videos. The FIRST item shows
    // first on the detail page; a video row carries its poster in `path`.
    $media = $product->images->sortBy('sort_order')->map(fn ($i) => [
        'type' => $i->video_path ? 'video' : 'image',
        'src' => $i->video_path ? asset('storage/'.$i->video_path) : asset('storage/'.$i->path),
        'poster' => $i->path ? asset('storage/'.$i->path) : $product->primaryImageUrl(),
    ])->values();
    // Guarantee at least the main photo is present (products with no image rows).
    $hasMainPhoto = $product->images->contains(fn ($i) => ! $i->video_path && $i->path === $product->main_image_path);
    if (! $hasMainPhoto && $product->main_image_path) {
        $media->prepend(['type' => 'image', 'src' => asset('storage/'.$product->main_image_path), 'poster' => asset('storage/'.$product->main_image_path)]);
    }
    if ($media->isEmpty()) {
        $media->push(['type' => 'image', 'src' => $product->primaryImageUrl(), 'poster' => $product->primaryImageUrl()]);
    }
    $media = $media->values();
    $variantData = $product->variants->map(fn ($v) => [
        'id' => $v->id, 'name' => $v->name, 'price' => $v->effectivePrice(),
        'base' => $v->effectiveBasePrice(), 'stock' => $v->stock, 'options' => $v->option_values,
        'image' => $v->image_path ? asset('storage/'.$v->image_path) : null,
    ]);
@endphp

@section('content')
    <x-breadcrumbs :items="$breadcrumbs" />

    <div x-data="{
        media: {{ Illuminate\Support\Js::from($media) }},
        active: 0,
        variantImage: null,
        zoom: false,
        qty: {{ $product->min_purchase }},
        {{-- Auto-pilih varian pertama yang MASIH ADA STOK — mencegah pembeli
             mengira sudah memilih (chip habis tidak bisa diklik) lalu item
             masuk keranjang tanpa varian. --}}
        variantId: {{ $product->variants->first(fn ($v) => $v->stock > 0)?->id ?? ($product->variants->count() === 1 ? $product->variants->first()->id : 'null') }},
        variants: {{ Illuminate\Support\Js::from($variantData) }},
        basePrice: {{ $product->effectivePrice() }},
        get current() { return this.variants.find(v => v.id === this.variantId) },
        get price() { return this.current ? this.current.price : this.basePrice },
        get stock() { return this.current ? this.current.stock : {{ $product->stock }} },
        rupiah(n) { return 'Rp ' + Math.round(n).toLocaleString('id-ID') },
        cur() {
            if (this.variantImage) return { type: 'image', src: this.variantImage, poster: this.variantImage };
            return this.media[this.active] || this.media[0] || { type: 'image', src: '', poster: '' };
        },
        pick(i) { this.active = i; this.variantImage = null; },
        init() {
            // Swap the main image when a variant with its own image is picked.
            this.$watch('variantId', (id) => {
                const v = this.variants.find(x => x.id === id);
                this.variantImage = (v && v.image) ? v.image : null;
            });
        },
    }" class="grid gap-8 lg:grid-cols-2">

        {{-- Gallery --}}
        <div class="min-w-0">
            <div class="card group relative block w-full overflow-hidden">
                {{-- Photo: object-contain over a blurred fill of itself, click to zoom. --}}
                <template x-if="cur().type === 'image'">
                    <button type="button" @click="zoom = true" class="relative block w-full cursor-zoom-in">
                        <div class="absolute inset-0 scale-110 bg-cover bg-center blur-2xl" :style="`background-image:url('${cur().src}')`" aria-hidden="true"></div>
                        <div class="absolute inset-0 bg-white/40" aria-hidden="true"></div>
                        <img :src="cur().src" alt="{{ $product->name }}" class="relative aspect-square w-full object-contain">
                        <span class="absolute bottom-2 right-2 inline-flex items-center gap-1 rounded-full bg-black/50 px-2 py-1 text-[11px] text-white opacity-0 transition group-hover:opacity-100">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.2-5.2m1.95-4.55a6.75 6.75 0 1 1-13.5 0 6.75 6.75 0 0 1 13.5 0ZM10.5 7.5v6m3-3h-6"/></svg>
                            Klik untuk zoom
                        </span>
                    </button>
                </template>
                {{-- Short video: autoplays muted + loops (browsers only allow autoplay
                     when muted). x-init forces the muted property + kicks off play so
                     it starts reliably; users can unmute/pause via the controls. --}}
                <template x-if="cur().type === 'video'">
                    <video :src="cur().src" :poster="cur().poster" controls playsinline autoplay muted loop preload="auto"
                           x-init="$el.muted = true; $nextTick(() => $el.play().catch(() => {}))"
                           class="aspect-square w-full bg-black object-contain"></video>
                </template>
            </div>

            <div class="mt-3 flex gap-2 overflow-x-auto" x-show="media.length > 1" x-cloak>
                <template x-for="(m, i) in media" :key="i">
                    <button type="button" @click="pick(i)"
                            :class="(active === i && !variantImage) ? 'border-brand-500' : 'border-gray-200'"
                            class="relative h-16 w-16 shrink-0 overflow-hidden rounded-lg border-2">
                        <img :src="m.poster || m.src" alt="{{ $product->name }}" class="h-full w-full object-cover" loading="lazy">
                        <span x-show="m.type === 'video'" class="absolute inset-0 grid place-items-center bg-black/25">
                            <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                        </span>
                    </button>
                </template>
            </div>
        </div>

        {{-- Zoom lightbox (photos only) --}}
        <div x-show="zoom" x-cloak x-transition.opacity
             @click="zoom = false" @keydown.escape.window="zoom = false"
             class="fixed inset-0 z-[80] flex items-center justify-center bg-black/85 p-4">
            <img :src="cur().src" alt="{{ $product->name }}" class="max-h-[90vh] max-w-full object-contain">
            <button type="button" @click="zoom = false" class="absolute right-4 top-4 grid h-10 w-10 place-items-center rounded-full bg-white/15 text-2xl text-white hover:bg-white/25" aria-label="Tutup">&times;</button>
        </div>

        {{-- Purchase panel --}}
        <div class="min-w-0">
            <div class="mb-2 flex flex-wrap gap-1">
                {{-- Condition is shown as a labeled line below, not as a badge here. --}}
                @foreach ($product->badges(includeCondition: false) as $badge)<x-badge :label="$badge" />@endforeach
            </div>

            @if ($product->brand)
                <a href="{{ route('brands.show', $product->brand->slug) }}"
                   class="group inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white py-1 pl-1 pr-2.5 shadow-sm transition hover:border-brand-300 hover:shadow"
                   aria-label="Lihat semua produk {{ $product->brand->name }}">
                    <span class="grid h-7 w-7 shrink-0 place-items-center overflow-hidden rounded-full bg-gray-50 ring-1 ring-gray-100">
                        @if ($product->brand->logo_path)
                            <img src="{{ asset('storage/'.$product->brand->logo_path) }}" alt="{{ $product->brand->name }}" class="h-full w-full object-contain p-0.5">
                        @else
                            <span class="text-xs font-bold text-brand-600">{{ mb_strtoupper(mb_substr($product->brand->name, 0, 1)) }}</span>
                        @endif
                    </span>
                    <span class="text-sm font-semibold text-gray-700 group-hover:text-brand-700">{{ $product->brand->name }}</span>
                    <svg class="h-3.5 w-3.5 text-gray-400 transition group-hover:translate-x-0.5 group-hover:text-brand-500" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </a>
            @endif
            {{-- Judul + nama varian terpilih (mis. "PLTS AMAL … — AMAL 2000") --}}
            <h1 class="mt-1.5 text-2xl font-bold text-gray-900">{{ $product->name }}<template x-if="current"><span> — <span class="text-brand-700" x-text="current.name"></span></span></template></h1>

            <div class="mt-2 flex flex-wrap items-center gap-3 text-sm text-gray-500">
                <span>SKU: {{ $product->sku }}</span>
                @if ($product->model)<span>• Model: {{ $product->model }}</span>@endif
                @if ($product->rating_count > 0)
                    <span class="flex items-center gap-1"><x-stars :rating="$product->rating_avg" /> {{ number_format($product->rating_avg, 1) }} ({{ $product->rating_count }})</span>
                @endif
                @if ($product->sold_count > 0)<span>• {{ $product->sold_count }} terjual</span>@endif
            </div>

            {{-- Bagikan / share link --}}
            @php
                $affiliate = auth()->user()?->affiliate;
                $isActiveAffiliate = $affiliate && $affiliate->isActive();
                // Short share links (energi.click/s/xxxx). Idempotent per URL.
                $productShort = \App\Models\ShortLink::for(route('products.show', $product->slug));
                $shareUrl = $productShort->shortUrl();
                // Referral link keeps the affiliate code visible: /s/{kode}-{KODE-AFILIATOR}
                $affiliateShareUrl = $isActiveAffiliate
                    ? \App\Models\ShortLink::referral(route('products.show', $product->slug), $productShort->code, $affiliate->code)->shortUrl()
                    : null;
                // Per-product commission (rate override, else default) + its rupiah value.
                $affiliateRate = app(\App\Services\AffiliateService::class)->rateForProduct($product);
                $commissionQuotable = ! $product->requires_quotation && $product->price_status !== 'call_for_price';
                $commissionAmount = $commissionQuotable ? (int) round($product->effectivePrice() * $affiliateRate / 100) : null;
                $rateLabel = rtrim(rtrim(number_format($affiliateRate, 1), '0'), '.').'%';
            @endphp
            <div class="mt-3 flex flex-wrap items-center gap-2"
                 x-data="{
                    copied: '',
                    async copy(text, tag) {
                        try { await navigator.clipboard.writeText(text); } catch (e) {}
                        this.copied = tag; setTimeout(() => { if (this.copied === tag) this.copied = ''; }, 2000);
                    },
                 }">
                <span class="text-sm text-gray-500">Bagikan:</span>
                <button type="button" @click="copy(@js($shareUrl), 'link')" aria-label="Salin link produk"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-600 transition hover:border-brand-400 hover:text-brand-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>
                    <span x-text="copied === 'link' ? 'Tersalin!' : 'Salin Link'"></span>
                </button>
                @if ($isActiveAffiliate)
                    <button type="button" @click="copy(@js($affiliateShareUrl), 'aff')" aria-label="Salin link afiliator produk ini"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-brand-200 bg-brand-50 px-3 py-1.5 text-sm font-semibold text-brand-700 transition hover:bg-brand-100">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"/></svg>
                        <span x-text="copied === 'aff' ? 'Tersalin!' : 'Salin Link Afiliator'"></span>
                    </button>
                @endif
            </div>

            {{-- Concrete commission for an active affiliate sharing THIS product. --}}
            @if ($isActiveAffiliate && $affiliateRate > 0)
                <p class="mt-2 flex items-center gap-1.5 text-sm text-brand-700">
                    <svg class="h-4 w-4 shrink-0 text-brand-500" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    <span>Komisi kamu: <span class="font-bold">{{ $rateLabel }}</span>@if ($commissionAmount) <span class="font-bold">({{ rupiah($commissionAmount) }})</span>@endif per penjualan</span>
                </p>
            @endif

            {{-- Persuasive affiliate invite (guest / not-yet an affiliate). --}}
            @unless ($isActiveAffiliate)
                @if ($affiliate)
                    <a href="{{ route('account.affiliate.dashboard') }}" class="mt-2 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 hover:bg-amber-100">
                        <svg class="h-4 w-4 shrink-0 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                        <span>Afiliasi kamu: <span class="font-semibold">{{ $affiliate->status->label() }}</span>. Setelah aktif, kamu bisa bagikan link afiliator produk ini &amp; dapat komisi.</span>
                    </a>
                @else
                    <a href="{{ auth()->check() ? route('account.affiliate.register') : route('affiliate.landing') }}"
                       class="group mt-2 flex items-center gap-3 rounded-xl border border-brand-200 bg-gradient-to-r from-brand-50 to-white p-3 transition hover:border-brand-300 hover:shadow-sm">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-brand-100 text-brand-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"/></svg>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-bold leading-snug text-brand-800">Komisi Afiliator {{ $rateLabel }}@if ($commissionAmount) <span class="text-brand-600">({{ rupiah($commissionAmount) }})</span>@endif</span>
                            <span class="block text-xs leading-snug text-gray-500">Jadi Afiliator — bagikan produk ini, dapat komisi tiap penjualan.</span>
                        </span>
                        <span class="hidden shrink-0 whitespace-nowrap rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white transition group-hover:bg-brand-700 sm:inline-block">Gabung gratis</span>
                        <svg class="h-5 w-5 shrink-0 text-brand-400 sm:hidden" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                    </a>
                @endif
            @endunless

            {{-- Price --}}
            <div class="mt-4 rounded-xl bg-gray-50 p-4">
                @if ($product->requires_quotation)
                    <p class="text-2xl font-extrabold text-brand-700">Hubungi untuk Penawaran</p>
                    <p class="text-sm text-gray-500">Produk ini tersedia melalui permintaan penawaran.</p>
                @else
                    <div class="flex flex-wrap items-baseline gap-3">
                        <span class="text-3xl font-extrabold text-gray-900" x-text="rupiah(price)"></span>
                        @if ($product->isOnSale())
                            <span class="text-lg text-gray-400 line-through">{{ rupiah($product->price) }}</span>
                            <span class="badge bg-red-100 text-red-700">Hemat {{ $product->discountPercent() }}%</span>
                        @endif
                    </div>
                    @if ((bool) setting('tax.enabled', config('rekasurya.tax.enabled')))
                    <p class="mt-1 text-xs text-gray-400">{{ $product->price_includes_tax ? 'Harga sudah termasuk PPN' : 'Harga belum termasuk PPN' }}</p>
                    @endif
                @endif
            </div>

            {{-- Variants --}}
            @if ($product->variants->isNotEmpty())
                <div class="mt-4">
                    <p class="mb-2 text-sm font-semibold text-gray-700">Pilih Varian</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($product->variants as $variant)
                            <button type="button" @click="variantId = {{ $variant->id }}"
                                    :class="variantId === {{ $variant->id }} ? 'border-brand-500 bg-brand-50 text-brand-700 ring-1 ring-brand-500' : 'border-gray-300 text-gray-600 hover:border-brand-400'"
                                    class="flex items-center gap-2 rounded-lg border py-1.5 pr-3 text-sm {{ $variant->image_path ? 'pl-1.5' : 'pl-3' }} disabled:opacity-40"
                                    @disabled($variant->stock <= 0)>
                                @if ($variant->image_path)
                                    <img src="{{ asset('storage/'.$variant->image_path) }}" alt="{{ $variant->name }}" class="h-9 w-9 shrink-0 rounded object-cover" loading="lazy">
                                @endif
                                <span class="font-medium">{{ $variant->name }}</span>
                                @if ($variant->stock <= 0)<span class="text-xs text-red-400">(habis)</span>@endif
                            </button>
                        @endforeach
                    </div>
                    <p x-show="!variantId" x-cloak class="mt-2 text-xs font-medium text-amber-600">Pilih varian dulu untuk menambah ke keranjang.</p>
                </div>
            @endif

            {{-- Stock + qty --}}
            <div class="mt-4 flex items-center gap-4">
                <span class="text-sm" x-show="stock > 0"><span class="font-medium text-green-600">Stok tersedia</span> <span class="text-gray-400" x-text="'(' + stock + ' {{ $product->unit }})'"></span></span>
                <span class="text-sm font-medium text-red-500" x-show="stock <= 0">Stok habis</span>
            </div>

            @unless ($product->requires_quotation)
                <div class="mt-3 flex items-center gap-3">
                    <label class="text-sm text-gray-600">Jumlah</label>
                    <div class="inline-flex items-center rounded-lg border border-gray-300">
                        <button type="button" @click="qty = Math.max({{ $product->min_purchase }}, qty - 1)" class="px-3 py-2 text-gray-500">−</button>
                        <input type="number" x-model.number="qty" min="{{ $product->min_purchase }}" class="w-14 border-0 text-center text-sm focus:ring-0">
                        <button type="button" @click="qty = Math.min(stock, qty + 1)" class="px-3 py-2 text-gray-500">+</button>
                    </div>
                    @if ($product->min_purchase > 1)<span class="text-xs text-gray-400">Min. {{ $product->min_purchase }}</span>@endif
                </div>
            @endunless

            @if (! $product->requires_quotation && ! $product->pickup_only)
                @include('partials.shipping-estimate')
            @endif

            {{-- Condition acknowledgement note --}}
            @if ($product->requiresConditionAck() && $product->conditionDetail)
                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                    <p class="font-semibold">Kondisi: {{ $product->conditionEnum()->label() }}</p>
                    @if ($product->conditionDetail->defect_notes)<p class="mt-1">{{ $product->conditionDetail->defect_notes }}</p>@endif
                    <p class="mt-1 text-xs">Persetujuan kondisi akan diminta saat checkout.</p>
                </div>
            @endif

            {{-- Actions: one primary CTA + WhatsApp, wishlist/compare as small icons --}}
            <div class="mt-5 flex flex-col gap-2 sm:flex-row">
                @if ($product->requires_quotation)
                    <a href="{{ route('quotations.create', ['produk' => $product->slug]) }}" class="btn-accent flex-1">Minta Penawaran</a>
                @elseif ($product->is_purchasable)
                    <form action="{{ route('cart.store') }}" method="POST" class="flex-1" @submit="$store.cart.submit($event)">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <input type="hidden" name="variant_id" :value="variantId">
                        <input type="hidden" name="quantity" :value="qty">
                        <button type="submit" class="btn-primary w-full"
                                :disabled="stock <= 0 || ({{ $product->variants->isNotEmpty() ? 'true' : 'false' }} && !variantId)">Tambah ke Keranjang</button>
                    </form>
                @endif
                @php
                    $chatProduct = [
                        'slug' => $product->slug,
                        'name' => $product->name,
                        'url' => route('products.show', $product->slug),
                        'price' => rupiah($product->effectivePrice()),
                        'image' => $product->primaryImageUrl(),
                        'in_stock' => $product->inStock(),
                    ];
                @endphp
                {{-- Tokopedia-style product chat with a HUMAN admin: opens the
                     Chat Toko widget with this product attached. --}}
                <button type="button" @click="$dispatch('open-site-chat', { product: @js($chatProduct) })"
                        class="btn-outline flex-1 text-brand-700">💬 Tanya Produk Ini</button>
            </div>
            <div class="mt-2 flex gap-2 text-sm text-gray-500">
                <form action="{{ route('wishlist.toggle', $product->slug) }}" method="POST">@csrf<button class="inline-flex items-center gap-1 rounded-lg px-2 py-1 hover:bg-gray-50" aria-label="Simpan ke wishlist" title="Simpan ke wishlist"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"/></svg>Wishlist</button></form>
                <form action="{{ route('compare.add', $product->slug) }}" method="POST">@csrf<button class="inline-flex items-center gap-1 rounded-lg px-2 py-1 hover:bg-gray-50" aria-label="Bandingkan produk" title="Bandingkan produk"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5"/></svg>Bandingkan</button></form>
            </div>

            {{-- Condition, shown as a clear labeled line. --}}
            <div class="mt-3 border-t border-gray-100 pt-3 text-sm">
                <span class="text-gray-500">Kondisi:</span>
                <span class="font-semibold text-gray-800">{{ $product->conditionEnum()->label() }}</span>
                @if ($product->warranty)
                    <span class="text-gray-300">•</span>
                    @php $warranty = preg_replace('/^garansi\s*/i', '', trim($product->warranty)); @endphp
                    <span class="text-gray-500">Garansi:</span>
                    <span class="font-medium text-gray-700">{{ $warranty ?: $product->warranty }}</span>
                @endif
            </div>
        </div>

        {{-- Mobile sticky purchase bar — shares the purchase Alpine scope; sits above the bottom nav. --}}
        <div class="fixed inset-x-0 bottom-14 z-30 border-t border-gray-200 bg-white px-4 py-2.5 shadow-[0_-4px_16px_rgba(0,0,0,0.08)] lg:hidden">
            <div class="mx-auto flex max-w-7xl items-center gap-3">
                <div class="min-w-0 flex-1">
                    @if ($product->requires_quotation)
                        <p class="text-sm font-bold text-brand-700">Via Penawaran</p>
                    @else
                        <p class="truncate text-lg font-extrabold text-gray-900" x-text="rupiah(price)"></p>
                    @endif
                </div>
                @if ($product->requires_quotation)
                    <a href="{{ route('quotations.create', ['produk' => $product->slug]) }}" class="btn-accent shrink-0">Minta Penawaran</a>
                @elseif ($product->is_purchasable)
                    <form action="{{ route('cart.store') }}" method="POST" @submit="$store.cart.submit($event)" class="shrink-0">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <input type="hidden" name="variant_id" :value="variantId">
                        <input type="hidden" name="quantity" :value="qty">
                        <button type="submit" class="btn-primary" :disabled="stock <= 0" x-text="stock <= 0 ? 'Stok Habis' : 'Tambah ke Keranjang'"></button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- Video — tampil langsung (tanpa tab) agar pelanggan bisa langsung memutar. --}}
    @if ($product->videos->isNotEmpty())
        <section class="mt-8">
            <h2 class="mb-3 text-base font-bold text-gray-900 sm:text-lg">Video Produk</h2>
            <div class="space-y-4">
                @foreach ($product->videos as $video)
                    @if ($video->embedUrl())
                        <div class="mx-auto aspect-video w-full max-w-2xl overflow-hidden rounded-2xl bg-black">
                            <iframe src="{{ $video->embedUrl() }}" class="h-full w-full" title="{{ $video->title ?: 'Video produk' }}" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                        </div>
                    @else
                        <a href="{{ $video->url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-sm text-brand-600 hover:underline"><svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>{{ $video->title ?: 'Tonton video' }}</a>
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    {{-- Detail sections: tabs on desktop, accordion on mobile --}}
    @php
        $tabs = ['deskripsi' => 'Deskripsi'];
        if ($product->attributeValues->isNotEmpty() || $product->specifications) $tabs['spesifikasi'] = 'Spesifikasi';
        if ($product->bundleItems->isNotEmpty()) $tabs['isi'] = 'Isi Paket';
        if ($product->documents->isNotEmpty()) $tabs['dokumen'] = 'Dokumen ('.$product->documents->count().')';
        $tabs['pengiriman'] = 'Pengiriman & Garansi';
        $firstTab = array_key_first($tabs);
    @endphp
    <div class="mt-10" x-data="{ tab: '{{ $firstTab }}' }">
        {{-- Desktop tab bar --}}
        <div role="tablist" class="hidden gap-1 overflow-x-auto border-b border-gray-200 lg:flex">
            @foreach ($tabs as $key => $label)
                <button type="button" role="tab" :aria-selected="tab === '{{ $key }}'" @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-800'"
                        class="shrink-0 border-b-2 px-4 py-3 text-sm font-semibold transition">{{ $label }}</button>
            @endforeach
        </div>

        <div class="divide-y divide-gray-100 lg:divide-y-0">
            {{-- Deskripsi --}}
            <section>
                <button type="button" @click="tab = tab === 'deskripsi' ? '' : 'deskripsi'" :aria-expanded="tab === 'deskripsi'" class="flex w-full items-center justify-between py-4 text-left lg:hidden">
                    <span class="font-semibold text-gray-800">Deskripsi</span>
                    <svg class="h-5 w-5 shrink-0 text-gray-400 transition" :class="tab === 'deskripsi' && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                </button>
                <div x-show="tab === 'deskripsi'" x-cloak class="pb-6 lg:pt-6">
                    <div class="prose max-w-none text-sm text-gray-700">
                        {!! linkify_buttons($product->description ?: '<p>'.e($product->short_description).'</p>') !!}
                    </div>
                </div>
            </section>

            {{-- Spesifikasi --}}
            @if ($product->attributeValues->isNotEmpty() || $product->specifications)
                <section>
                    <button type="button" @click="tab = tab === 'spesifikasi' ? '' : 'spesifikasi'" :aria-expanded="tab === 'spesifikasi'" class="flex w-full items-center justify-between py-4 text-left lg:hidden">
                        <span class="font-semibold text-gray-800">Spesifikasi</span>
                        <svg class="h-5 w-5 shrink-0 text-gray-400 transition" :class="tab === 'spesifikasi' && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                    </button>
                    <div x-show="tab === 'spesifikasi'" x-cloak class="pb-6 lg:pt-6">
                        @if ($product->attributeValues->isNotEmpty())
                            <div class="overflow-x-auto">
                                <table class="w-full max-w-2xl text-sm">
                                    <tbody>
                                        @foreach ($product->attributeValues->sortBy('attribute.sort_order') as $av)
                                            <tr class="border-b border-gray-100">
                                                <th class="w-2/5 py-2.5 pr-4 text-left align-top font-medium text-gray-500">{{ $av->attribute->name }}</th>
                                                <td class="py-2.5 align-top text-gray-800">{{ $av->displayValue() }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="prose max-w-none text-sm text-gray-700">{!! $product->specifications !!}</div>
                        @endif
                    </div>
                </section>
            @endif

            {{-- Isi Paket --}}
            @if ($product->bundleItems->isNotEmpty())
                <section>
                    <button type="button" @click="tab = tab === 'isi' ? '' : 'isi'" :aria-expanded="tab === 'isi'" class="flex w-full items-center justify-between py-4 text-left lg:hidden">
                        <span class="font-semibold text-gray-800">Isi Paket</span>
                        <svg class="h-5 w-5 shrink-0 text-gray-400 transition" :class="tab === 'isi' && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                    </button>
                    <div x-show="tab === 'isi'" x-cloak class="pb-6 lg:pt-6">
                        <ul class="divide-y divide-gray-100">
                            @foreach ($product->bundleItems as $item)
                                <li class="flex items-center justify-between py-2 text-sm">
                                    <span>{{ $item->component_label ? $item->component_label.': ' : '' }}<a href="{{ route('products.show', $item->component->slug) }}" class="text-brand-600 hover:underline">{{ $item->component->name }}</a></span>
                                    <span class="text-gray-500">{{ $item->quantity }} unit{{ $item->is_replaceable ? ' • dapat diganti' : '' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </section>
            @endif

            {{-- Dokumen --}}
            @if ($product->documents->isNotEmpty())
                <section>
                    <button type="button" @click="tab = tab === 'dokumen' ? '' : 'dokumen'" :aria-expanded="tab === 'dokumen'" class="flex w-full items-center justify-between py-4 text-left lg:hidden">
                        <span class="font-semibold text-gray-800">Dokumen</span>
                        <svg class="h-5 w-5 shrink-0 text-gray-400 transition" :class="tab === 'dokumen' && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                    </button>
                    <div x-show="tab === 'dokumen'" x-cloak class="pb-6 lg:pt-6">
                        <ul class="space-y-2">
                            @foreach ($product->documents as $doc)
                                <li>
                                    <a href="{{ asset('storage/'.$doc->path) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-3 text-sm text-gray-700 transition hover:border-brand-400 hover:text-brand-700">
                                        <svg class="h-5 w-5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                                        {{ $doc->title }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </section>
            @endif

            {{-- Pengiriman & garansi --}}
            <section>
                <button type="button" @click="tab = tab === 'pengiriman' ? '' : 'pengiriman'" :aria-expanded="tab === 'pengiriman'" class="flex w-full items-center justify-between py-4 text-left lg:hidden">
                    <span class="font-semibold text-gray-800">Pengiriman &amp; Garansi</span>
                    <svg class="h-5 w-5 shrink-0 text-gray-400 transition" :class="tab === 'pengiriman' && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                </button>
                <div x-show="tab === 'pengiriman'" x-cloak class="pb-6 lg:pt-6">
                    <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                        @if ($product->warranty)<div class="flex gap-2"><dt class="w-28 shrink-0 text-gray-500">Garansi</dt><dd class="font-medium text-gray-700">{{ $product->warranty }}</dd></div>@endif
                        <div class="flex gap-2"><dt class="w-28 shrink-0 text-gray-500">Estimasi</dt><dd class="font-medium text-gray-700">{{ $product->estimated_processing ?: '1–3 hari kerja' }}</dd></div>
                        <div class="flex gap-2"><dt class="w-28 shrink-0 text-gray-500">Pengiriman</dt><dd class="font-medium text-gray-700">{{ $product->requires_freight ? 'Kargo (ongkir dikonfirmasi)' : 'Reguler & kargo' }}{{ $product->pickup_only ? ' • Ambil di lokasi' : '' }}</dd></div>
                        <div class="flex gap-2"><dt class="w-28 shrink-0 text-gray-500">Berat</dt><dd class="font-medium text-gray-700">{{ number_format($product->weight_grams / 1000, 2) }} kg</dd></div>
                    </dl>
                    <p class="mt-3 text-xs text-gray-400">Kebijakan retur: lihat <a href="{{ route('pages.show', 'kebijakan-retur') }}" class="text-brand-600 hover:underline">halaman kebijakan retur</a>.</p>
                </div>
            </section>
        </div>
    </div>

    {{-- Reviews --}}
    <section class="mt-10" id="ulasan">
        <h2 class="mb-4 text-lg font-bold text-gray-900">Rating &amp; Ulasan</h2>
        <div class="grid gap-6 @if ($product->rating_count > 0) md:grid-cols-[240px_1fr] @endif">
            {{-- Rating summary only when there are ratings (avoid a big empty "0.0" block). --}}
            @if ($product->rating_count > 0)
                <div class="card p-4 text-center">
                    <p class="text-4xl font-extrabold text-gray-900">{{ number_format($product->rating_avg, 1) }}</p>
                    <x-stars :rating="$product->rating_avg" class="justify-center" />
                    <p class="mt-1 text-sm text-gray-500">{{ $product->rating_count }} ulasan</p>
                    <div class="mt-3 space-y-1">
                        @foreach ($ratingDistribution as $star => $n)
                            <div class="flex items-center gap-2 text-xs">
                                <span class="w-3 text-gray-500">{{ $star }}</span>
                                <div class="h-2 flex-1 overflow-hidden rounded bg-gray-100"><div class="h-full bg-amber-400" style="width: {{ $product->rating_count ? ($n / $product->rating_count * 100) : 0 }}%"></div></div>
                                <span class="w-6 text-right text-gray-400">{{ $n }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div>
                @if ($canReview)
                    <details class="card mb-4 p-4">
                        <summary class="cursor-pointer text-sm font-semibold text-brand-700">Tulis ulasan Anda (pembelian terverifikasi)</summary>
                        <form action="{{ route('reviews.store', $product->slug) }}" method="POST" enctype="multipart/form-data" class="mt-3 space-y-3">
                            @csrf
                            @php($reviewableItem = \App\Models\OrderItem::where('product_id', $product->id)->whereHas('order', fn($q) => $q->where('user_id', auth()->id())->where('status', 'completed'))->whereDoesntHave('review')->first())
                            <input type="hidden" name="order_item_id" value="{{ $reviewableItem?->id }}">
                            <div>
                                <label class="input-label">Rating</label>
                                <select name="rating" class="form-select w-32">@for ($i = 5; $i >= 1; $i--)<option value="{{ $i }}">{{ $i }} bintang</option>@endfor</select>
                            </div>
                            <x-form.input name="title" label="Judul (opsional)" />
                            <x-form.textarea name="comment" label="Komentar" rows="3" />
                            <input type="file" name="photos[]" accept="image/*" multiple class="text-sm">
                            <button class="btn-primary">Kirim Ulasan</button>
                        </form>
                    </details>
                @endif

                @forelse ($reviews as $review)
                    <article class="border-b border-gray-100 py-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <x-stars :rating="$review->rating" />
                                @if ($review->is_verified_purchase)<span class="badge bg-green-100 text-green-700">Pembelian Terverifikasi</span>@endif
                            </div>
                            <span class="text-xs text-gray-400">{{ $review->created_at->translatedFormat('d M Y') }}</span>
                        </div>
                        @if ($review->title)<p class="mt-1 font-semibold text-gray-800">{{ $review->title }}</p>@endif
                        <p class="text-sm text-gray-600">{{ $review->comment }}</p>
                        <p class="mt-1 text-xs text-gray-400">{{ $review->user?->name }}</p>
                        @if ($review->media->isNotEmpty())
                            <div class="mt-2 flex gap-2">
                                @foreach ($review->media as $m)<img src="{{ asset('storage/'.$m->path) }}" alt="Foto ulasan" class="h-16 w-16 rounded object-cover" loading="lazy">@endforeach
                            </div>
                        @endif
                        @if ($review->admin_reply)
                            <div class="mt-2 rounded-lg bg-brand-50 p-2 text-sm text-brand-800"><strong>Rekasurya:</strong> {{ $review->admin_reply }}</div>
                        @endif
                        @auth
                            <form action="{{ route('reviews.helpful', $review) }}" method="POST" class="mt-1 inline">@csrf<button class="inline-flex items-center gap-1 text-xs text-gray-400 hover:text-brand-600"><svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.633 10.5c.806 0 1.533-.446 2.031-1.08a9.041 9.041 0 0 1 2.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 0 0 .322-1.672V2.75a.75.75 0 0 1 .75-.75 2.25 2.25 0 0 1 2.25 2.25c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 0 1-2.649 7.521c-.388.482-.987.729-1.605.729H14.23c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 0 0-1.423-.23H5.904M6.633 10.5a2.25 2.25 0 0 0-2.25 2.25v6a2.25 2.25 0 0 0 2.25 2.25H6.75"/></svg>Membantu ({{ $review->helpful_count }})</button></form>
                        @endauth
                    </article>
                @empty
                    <p class="text-sm text-gray-400">Belum ada ulasan. Jadilah yang pertama memberi ulasan.</p>
                @endforelse
                <div class="mt-4">{{ $reviews->links() }}</div>
            </div>
        </div>
    </section>

    {{-- Q&A --}}
    <section class="mt-10">
        <h2 class="mb-4 text-lg font-bold text-gray-900">Tanya Jawab Produk</h2>
        @auth
            <form action="{{ route('questions.store', $product->slug) }}" method="POST" class="card mb-1 flex flex-col gap-2 p-4 sm:flex-row">
                @csrf
                @unless (auth()->user()->waNumber())
                    <input name="whatsapp" value="{{ old('whatsapp') }}" placeholder="No. WhatsApp Anda" class="form-input sm:w-48" required inputmode="tel">
                @endunless
                <input name="question" value="{{ old('question') }}" placeholder="Tulis pertanyaan Anda tentang produk ini…" class="form-input flex-1" required minlength="5">
                <button class="btn-primary">Kirim</button>
            </form>
            @error('whatsapp')<p class="mb-2 text-sm text-red-600">{{ $message }}</p>@enderror
            @error('question')<p class="mb-2 text-sm text-red-600">{{ $message }}</p>@enderror
            <p class="mb-4 text-xs text-gray-400">Jawaban kami tampil di halaman ini dan dikirim ke WhatsApp Anda. Nomor WA tidak ditampilkan utuh ke publik.</p>
        @else
            <div class="card mb-4 flex flex-col items-start gap-2 p-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-gray-600">Masuk dulu untuk bertanya — jawabannya kami kirim juga ke WhatsApp Anda.</p>
                <a href="{{ route('login', ['redirect' => url()->current()]) }}" class="btn-primary text-sm">Masuk / Daftar</a>
            </div>
        @endauth
        <div class="space-y-3">
            @forelse ($product->questions as $q)
                <div class="card p-4">
                    <p class="text-sm font-medium text-gray-800">T: {{ $q->question }}</p>
                    <p class="text-xs text-gray-400">{{ $q->name }}@if ($q->maskedPhone()) · {{ $q->maskedPhone() }}@endif · {{ $q->created_at?->format('d M Y') }}</p>
                    @foreach ($q->answers as $a)
                        <p class="mt-1 text-sm text-gray-600">J: {{ $a->answer }} @if($a->is_staff)<span class="badge bg-brand-100 text-brand-700">Rekasurya</span>@endif</p>
                    @endforeach
                </div>
            @empty
                <p class="text-sm text-gray-400">Belum ada pertanyaan.</p>
            @endforelse
        </div>
    </section>

    <x-product-row title="Produk Terkait" :products="$related" />
    <x-product-row title="Sering Dibeli Bersama" :products="$boughtTogether" />
    <x-product-row title="Produk Serupa" :products="$similar" />
    <x-product-row title="Terakhir Dilihat" :products="$recentlyViewed" />

    {{-- Spacer so the mobile sticky purchase bar never covers the last content. --}}
    <div class="h-24 lg:hidden" aria-hidden="true"></div>
@endsection
