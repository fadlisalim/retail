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
    ]);
@endphp

@section('content')
    <x-breadcrumbs :items="$breadcrumbs" />

    <div x-data="{
        gallery: '{{ $gallery->first() }}',
        qty: {{ $product->min_purchase }},
        variantId: {{ $product->variants->count() === 1 ? $product->variants->first()->id : 'null' }},
        variants: {{ Illuminate\Support\Js::from($variantData) }},
        basePrice: {{ $product->effectivePrice() }},
        get current() { return this.variants.find(v => v.id === this.variantId) },
        get price() { return this.current ? this.current.price : this.basePrice },
        get stock() { return this.current ? this.current.stock : {{ $product->stock }} },
        rupiah(n) { return 'Rp ' + Math.round(n).toLocaleString('id-ID') },
    }" class="grid gap-8 lg:grid-cols-2">

        {{-- Gallery --}}
        <div>
            <div class="card overflow-hidden">
                <img :src="gallery" alt="{{ $product->name }}" class="aspect-square w-full object-contain">
            </div>
            @if ($gallery->count() > 1)
                <div class="mt-3 flex gap-2 overflow-x-auto">
                    @foreach ($gallery as $img)
                        <button @click="gallery = '{{ $img }}'" :class="gallery === '{{ $img }}' ? 'border-brand-500' : 'border-gray-200'" class="h-16 w-16 shrink-0 overflow-hidden rounded-lg border-2">
                            <img src="{{ $img }}" alt="{{ $product->name }} thumbnail" class="h-full w-full object-cover" loading="lazy">
                        </button>
                    @endforeach
                </div>
            @endif
            @if ($product->videos->isNotEmpty())
                <div class="mt-4">
                    <p class="mb-1 text-sm font-semibold text-gray-700">Video Produk</p>
                    @foreach ($product->videos as $video)
                        <a href="{{ $video->url }}" target="_blank" rel="noopener" class="text-sm text-brand-600 hover:underline">▶ {{ $video->title ?: 'Tonton video' }}</a>
                    @endforeach
                </div>
            @endif
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
                                    :class="variantId === {{ $variant->id }} ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-gray-300 text-gray-600'"
                                    class="rounded-lg border px-3 py-1.5 text-sm" @disabled($variant->stock <= 0)>
                                {{ $variant->name }}
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

            {{-- Actions --}}
            <div class="mt-5 flex flex-col gap-2 sm:flex-row">
                @if ($product->requires_quotation)
                    <a href="{{ route('quotations.create', ['produk' => $product->slug]) }}" class="btn-accent flex-1">Minta Penawaran</a>
                @elseif ($product->is_purchasable)
                    <form action="{{ route('cart.store') }}" method="POST" class="flex flex-1 gap-2" @submit="$store.cart.submit($event)">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <input type="hidden" name="variant_id" :value="variantId">
                        <input type="hidden" name="quantity" :value="qty">
                        <button type="submit" class="btn-outline flex-1" :disabled="stock <= 0">+ Keranjang</button>
                        <button type="submit" name="buy_now" value="1" class="btn-primary flex-1" :disabled="stock <= 0">Beli Sekarang</button>
                    </form>
                @endif
            </div>
            <div class="mt-2 flex flex-wrap gap-2">
                @if ($whatsappEnabled)
                    <a href="{{ whatsapp_link($waMsg) }}" target="_blank" rel="noopener" class="btn-outline flex-1 text-green-700">Konsultasi WhatsApp</a>
                @endif
                <form action="{{ route('wishlist.toggle', $product->slug) }}" method="POST">@csrf<button class="btn-outline" title="Wishlist">♡ Wishlist</button></form>
                <form action="{{ route('compare.add', $product->slug) }}" method="POST">@csrf<button class="btn-outline" title="Bandingkan">⇄ Bandingkan</button></form>
            </div>

            {{-- Shipping / warranty quick info --}}
            <dl class="mt-5 space-y-2 border-t border-gray-100 pt-4 text-sm">
                @if ($product->warranty)<div class="flex gap-2"><dt class="w-32 text-gray-500">Garansi</dt><dd class="font-medium text-gray-700">{{ $product->warranty }}</dd></div>@endif
                <div class="flex gap-2"><dt class="w-32 text-gray-500">Estimasi proses</dt><dd class="font-medium text-gray-700">{{ $product->estimated_processing ?: '1–3 hari kerja' }}</dd></div>
                <div class="flex gap-2"><dt class="w-32 text-gray-500">Pengiriman</dt><dd class="font-medium text-gray-700">{{ $product->requires_freight ? 'Kargo / ongkir dikonfirmasi' : 'Reguler & kargo' }}{{ $product->pickup_only ? ' • Ambil di lokasi' : '' }}</dd></div>
                <div class="flex gap-2"><dt class="w-32 text-gray-500">Berat</dt><dd class="font-medium text-gray-700">{{ number_format($product->weight_grams / 1000, 2) }} kg</dd></div>
            </dl>
        </div>
    </div>

    {{-- Details tabs --}}
    <div class="mt-10" x-data="{ tab: 'desc' }">
        <div class="flex gap-1 overflow-x-auto border-b border-gray-200 text-sm">
            @foreach (['desc' => 'Deskripsi', 'spec' => 'Spesifikasi', 'bundle' => 'Isi Paket', 'docs' => 'Dokumen', 'shipping' => 'Pengiriman & Retur'] as $key => $label)
                @if ($key !== 'bundle' || $product->bundleItems->isNotEmpty())
                    <button @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500'" class="whitespace-nowrap border-b-2 px-4 py-2 font-medium">{{ $label }}</button>
                @endif
            @endforeach
        </div>

        <div class="py-5">
            <div x-show="tab === 'desc'" class="prose max-w-none text-sm text-gray-700">
                {!! $product->description ?: '<p>'.e($product->short_description).'</p>' !!}
            </div>

            <div x-show="tab === 'spec'" x-cloak>
                @if ($product->attributeValues->isNotEmpty())
                    <table class="w-full max-w-2xl text-sm">
                        <tbody>
                            @foreach ($product->attributeValues->sortBy('attribute.sort_order') as $av)
                                <tr class="border-b border-gray-100">
                                    <th class="w-1/2 py-2 text-left font-medium text-gray-500">{{ $av->attribute->name }}</th>
                                    <td class="py-2 text-gray-800">{{ $av->displayValue() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="prose max-w-none text-sm text-gray-700">{!! $product->specifications ?: '<p class="text-gray-400">Belum ada spesifikasi teknis.</p>' !!}</div>
                @endif
            </div>

            @if ($product->bundleItems->isNotEmpty())
                <div x-show="tab === 'bundle'" x-cloak>
                    <ul class="divide-y divide-gray-100">
                        @foreach ($product->bundleItems as $item)
                            <li class="flex items-center justify-between py-2 text-sm">
                                <span>{{ $item->component_label ? $item->component_label.': ' : '' }}<a href="{{ route('products.show', $item->component->slug) }}" class="text-brand-600 hover:underline">{{ $item->component->name }}</a></span>
                                <span class="text-gray-500">{{ $item->quantity }} unit{{ $item->is_replaceable ? ' • dapat diganti' : '' }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-3 rounded-lg bg-gray-50 p-3 text-xs text-gray-500">Estimasi produksi energi bergantung pada lokasi, cuaca, orientasi, dan kondisi instalasi.</p>
                </div>
            @endif

            <div x-show="tab === 'docs'" x-cloak>
                @if ($product->documents->isNotEmpty())
                    <ul class="space-y-2">
                        @foreach ($product->documents as $doc)
                            <li><a href="{{ asset('storage/'.$doc->path) }}" target="_blank" rel="noopener" class="text-sm text-brand-600 hover:underline">📄 {{ $doc->title }} ({{ ucfirst($doc->type) }})</a></li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-gray-400">Belum ada dokumen tersedia.</p>
                @endif
            </div>

            <div x-show="tab === 'shipping'" x-cloak class="space-y-3 text-sm text-gray-700">
                <p><strong>Pengiriman:</strong> {{ $product->requires_freight ? 'Barang besar/berat dikirim via kargo, ongkir dikonfirmasi setelah checkout.' : 'Reguler & kargo, ongkir dihitung otomatis berdasarkan berat & volume.' }}</p>
                <p><strong>Kebijakan Retur:</strong> Lihat <a href="{{ route('pages.show', 'kebijakan-retur') }}" class="text-brand-600 hover:underline">kebijakan retur</a> kami. {{ $product->requiresConditionAck() ? 'Produk kondisi khusus mengikuti ketentuan pada deskripsi.' : '' }}</p>
            </div>
        </div>
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
