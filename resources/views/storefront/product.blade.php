@extends('layouts.storefront')
@section('title', $product->meta_title ?: $product->name)
@section('meta_description', $product->meta_description ?: \Illuminate\Support\Str::limit(strip_tags($product->short_description ?? ''), 155))
@section('canonical', $product->canonical_url ?: route('products.show', $product->slug))
@section('og_type', 'product')
@section('og_image', $product->primaryImageUrl())

@push('head')
<script type="application/ld+json">
{!! json_encode(array_filter([
    '@context' => 'https://schema.org',
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
]), JSON_UNESCAPED_SLASHES) !!}
</script>
@endpush

@php
    $waMsg = 'Halo Rekasurya, saya ingin berkonsultasi mengenai produk: '.$product->name.'. Link: '.route('products.show', $product->slug);
    $gallery = $product->images->pluck('path')->map(fn ($p) => asset('storage/'.$p))->prepend($product->primaryImageUrl())->unique()->values();
    $variantData = $product->variants->map(fn ($v) => [
        'id' => $v->id, 'name' => $v->name, 'price' => $v->effectivePrice(),
        'base' => $v->effectiveBasePrice(), 'stock' => $v->stock, 'options' => $v->option_values,
        'image' => $v->image_path ? asset('storage/'.$v->image_path) : null,
    ]);
@endphp

@section('content')
    <x-breadcrumbs :items="$breadcrumbs" />

    <div x-data="{
        gallery: '{{ $gallery->first() }}',
        zoom: false,
        qty: {{ $product->min_purchase }},
        variantId: {{ $product->variants->count() === 1 ? $product->variants->first()->id : 'null' }},
        variants: {{ Illuminate\Support\Js::from($variantData) }},
        basePrice: {{ $product->effectivePrice() }},
        get current() { return this.variants.find(v => v.id === this.variantId) },
        get price() { return this.current ? this.current.price : this.basePrice },
        get stock() { return this.current ? this.current.stock : {{ $product->stock }} },
        rupiah(n) { return 'Rp ' + Math.round(n).toLocaleString('id-ID') },
        init() {
            // Swap the main gallery image when a variant with its own image is picked.
            this.$watch('variantId', (id) => {
                const v = this.variants.find(x => x.id === id);
                if (v && v.image) this.gallery = v.image;
            });
        },
    }" class="grid gap-8 lg:grid-cols-2">

        {{-- Gallery --}}
        <div>
            {{-- Main image: object-contain (never cropped) over a blurred fill of itself. --}}
            <button type="button" @click="zoom = true" class="card group relative block w-full cursor-zoom-in overflow-hidden">
                <div class="absolute inset-0 scale-110 bg-cover bg-center blur-2xl" :style="`background-image:url('${gallery}')`" aria-hidden="true"></div>
                <div class="absolute inset-0 bg-white/40" aria-hidden="true"></div>
                <img :src="gallery" alt="{{ $product->name }}" class="relative aspect-square w-full object-contain">
                <span class="absolute bottom-2 right-2 rounded-full bg-black/50 px-2 py-1 text-[11px] text-white opacity-0 transition group-hover:opacity-100">🔍 Klik untuk zoom</span>
            </button>
            @if ($gallery->count() > 1)
                <div class="mt-3 flex gap-2 overflow-x-auto">
                    @foreach ($gallery as $img)
                        <button @click="gallery = '{{ $img }}'" :class="gallery === '{{ $img }}' ? 'border-brand-500' : 'border-gray-200'" class="h-16 w-16 shrink-0 overflow-hidden rounded-lg border-2">
                            <img src="{{ $img }}" alt="{{ $product->name }} thumbnail" class="h-full w-full object-cover" loading="lazy">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Zoom lightbox --}}
        <div x-show="zoom" x-cloak x-transition.opacity
             @click="zoom = false" @keydown.escape.window="zoom = false"
             class="fixed inset-0 z-[80] flex items-center justify-center bg-black/85 p-4">
            <img :src="gallery" alt="{{ $product->name }}" class="max-h-[90vh] max-w-full object-contain">
            <button type="button" @click="zoom = false" class="absolute right-4 top-4 grid h-10 w-10 place-items-center rounded-full bg-white/15 text-2xl text-white hover:bg-white/25" aria-label="Tutup">&times;</button>
        </div>

        {{-- Purchase panel --}}
        <div>
            <div class="mb-2 flex flex-wrap gap-1">
                @foreach ($product->badges() as $badge)<x-badge :label="$badge" />@endforeach
            </div>

            @if ($product->brand)
                <a href="{{ route('brands.show', $product->brand->slug) }}" class="text-sm font-medium text-brand-600 hover:underline">{{ $product->brand->name }}</a>
            @endif
            <h1 class="mt-1 text-2xl font-bold text-gray-900">{{ $product->name }}</h1>

            <div class="mt-2 flex flex-wrap items-center gap-3 text-sm text-gray-500">
                <span>SKU: {{ $product->sku }}</span>
                @if ($product->model)<span>• Model: {{ $product->model }}</span>@endif
                @if ($product->rating_count > 0)
                    <span class="flex items-center gap-1"><x-stars :rating="$product->rating_avg" /> {{ number_format($product->rating_avg, 1) }} ({{ $product->rating_count }})</span>
                @endif
                @if ($product->sold_count > 0)<span>• {{ $product->sold_count }} terjual</span>@endif
            </div>

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
                            </button>
                        @endforeach
                    </div>
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
                        <button type="submit" class="btn-primary w-full" :disabled="stock <= 0">Tambah Keranjang</button>
                    </form>
                @endif
                @if ($whatsappEnabled)
                    <a href="{{ whatsapp_link($waMsg) }}" target="_blank" rel="noopener" class="btn-outline flex-1 text-green-700">Tanya WhatsApp</a>
                @endif
            </div>
            <div class="mt-2 flex gap-2 text-sm text-gray-500">
                <form action="{{ route('wishlist.toggle', $product->slug) }}" method="POST">@csrf<button class="inline-flex items-center gap-1 rounded-lg px-2 py-1 hover:bg-gray-50" title="Simpan ke wishlist">♡ Wishlist</button></form>
                <form action="{{ route('compare.add', $product->slug) }}" method="POST">@csrf<button class="inline-flex items-center gap-1 rounded-lg px-2 py-1 hover:bg-gray-50" title="Bandingkan produk">⇄ Bandingkan</button></form>
            </div>
        </div>
    </div>

    {{-- Details tabs --}}
    <div class="mt-10 space-y-10">
        {{-- Deskripsi --}}
        <section>
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">Deskripsi</h2>
            <div class="prose max-w-none text-sm text-gray-700">
                {!! linkify_buttons($product->description ?: '<p>'.e($product->short_description).'</p>') !!}
            </div>
        </section>

        {{-- Spesifikasi --}}
        @if ($product->attributeValues->isNotEmpty() || $product->specifications)
            <section>
                <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">Spesifikasi</h2>
                @if ($product->attributeValues->isNotEmpty())
                    <table class="w-full max-w-2xl text-sm">
                        <tbody>
                            @foreach ($product->attributeValues->sortBy('attribute.sort_order') as $av)
                                <tr class="border-b border-gray-100">
                                    <th class="w-1/2 py-2.5 text-left font-medium text-gray-500">{{ $av->attribute->name }}</th>
                                    <td class="py-2.5 text-gray-800">{{ $av->displayValue() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="prose max-w-none text-sm text-gray-700">{!! $product->specifications !!}</div>
                @endif
            </section>
        @endif

        {{-- Isi Paket --}}
        @if ($product->bundleItems->isNotEmpty())
            <section>
                <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">Isi Paket</h2>
                <ul class="divide-y divide-gray-100">
                    @foreach ($product->bundleItems as $item)
                        <li class="flex items-center justify-between py-2 text-sm">
                            <span>{{ $item->component_label ? $item->component_label.': ' : '' }}<a href="{{ route('products.show', $item->component->slug) }}" class="text-brand-600 hover:underline">{{ $item->component->name }}</a></span>
                            <span class="text-gray-500">{{ $item->quantity }} unit{{ $item->is_replaceable ? ' • dapat diganti' : '' }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Dokumen --}}
        @if ($product->documents->isNotEmpty())
            <section>
                <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">Dokumen</h2>
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
            </section>
        @endif

        {{-- Video --}}
        @if ($product->videos->isNotEmpty())
            <section>
                <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">Video Produk</h2>
                <div class="space-y-4">
                    @foreach ($product->videos as $video)
                        @php($embed = $video->embedUrl())
                        @if ($embed)
                            <div class="mx-auto aspect-video w-full max-w-3xl overflow-hidden rounded-2xl bg-black">
                                <iframe src="{{ $embed }}" class="h-full w-full" title="{{ $video->title ?: 'Video produk' }}" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                            </div>
                        @else
                            <a href="{{ $video->url }}" target="_blank" rel="noopener" class="text-sm text-brand-600 hover:underline">▶ {{ $video->title ?: 'Tonton video' }}</a>
                        @endif
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Pengiriman & garansi (ringkas) --}}
        <section>
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">Pengiriman &amp; Garansi</h2>
            <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                @if ($product->warranty)<div class="flex gap-2"><dt class="w-28 shrink-0 text-gray-500">Garansi</dt><dd class="font-medium text-gray-700">{{ $product->warranty }}</dd></div>@endif
                <div class="flex gap-2"><dt class="w-28 shrink-0 text-gray-500">Estimasi</dt><dd class="font-medium text-gray-700">{{ $product->estimated_processing ?: '1–3 hari kerja' }}</dd></div>
                <div class="flex gap-2"><dt class="w-28 shrink-0 text-gray-500">Pengiriman</dt><dd class="font-medium text-gray-700">{{ $product->requires_freight ? 'Kargo (ongkir dikonfirmasi)' : 'Reguler & kargo' }}{{ $product->pickup_only ? ' • Ambil di lokasi' : '' }}</dd></div>
                <div class="flex gap-2"><dt class="w-28 shrink-0 text-gray-500">Berat</dt><dd class="font-medium text-gray-700">{{ number_format($product->weight_grams / 1000, 2) }} kg</dd></div>
            </dl>
            <p class="mt-3 text-xs text-gray-400">Kebijakan retur: lihat <a href="{{ route('pages.show', 'kebijakan-retur') }}" class="text-brand-600 hover:underline">halaman kebijakan retur</a>.</p>
        </section>
    </div>

    {{-- Reviews --}}
    <section class="mt-10" id="ulasan">
        <h2 class="mb-4 text-lg font-bold text-gray-900">Rating &amp; Ulasan</h2>
        <div class="grid gap-6 md:grid-cols-[240px_1fr]">
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
                            <form action="{{ route('reviews.helpful', $review) }}" method="POST" class="mt-1 inline">@csrf<button class="text-xs text-gray-400 hover:text-brand-600">👍 Membantu ({{ $review->helpful_count }})</button></form>
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
        <form action="{{ route('questions.store', $product->slug) }}" method="POST" class="card mb-4 flex flex-col gap-2 p-4 sm:flex-row">
            @csrf
            @guest<input name="name" placeholder="Nama Anda" class="form-input sm:w-48" required>@endguest
            <input name="question" placeholder="Tulis pertanyaan Anda tentang produk ini…" class="form-input flex-1" required minlength="5">
            <button class="btn-primary">Kirim</button>
        </form>
        <div class="space-y-3">
            @forelse ($product->questions as $q)
                <div class="card p-4">
                    <p class="text-sm font-medium text-gray-800">T: {{ $q->question }}</p>
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

    {{-- Mobile sticky purchase bar --}}
    @unless ($product->requires_quotation)
        <div class="fixed inset-x-0 bottom-14 z-30 border-t border-gray-200 bg-white p-3 shadow-lg lg:hidden"
             x-data="{ price: {{ $product->effectivePrice() }}, rupiah(n){ return 'Rp '+Math.round(n).toLocaleString('id-ID') } }">
            <form action="{{ route('cart.store') }}" method="POST" class="flex items-center gap-3" @submit="$store.cart.submit($event)">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="quantity" value="{{ $product->min_purchase }}">
                <div class="flex-1">
                    <p class="text-xs text-gray-400">Harga</p>
                    <p class="text-lg font-bold text-gray-900">{{ rupiah($product->effectivePrice()) }}</p>
                </div>
                <button class="btn-primary" @if(!$product->inStock()) disabled @endif>+ Keranjang</button>
            </form>
        </div>
    @endunless
@endsection
