@extends('layouts.storefront')
@section('title', $title ?? ($heading ?? 'Produk'))
@if(!empty($metaDescription))@section('meta_description', $metaDescription)@endif
@if(!empty($noindex))@section('noindex', 'noindex')@endif

@section('content')
    @if (($brandBanners ?? collect())->isNotEmpty())
        <div class="mb-4">
            <x-banner-slider :banners="$brandBanners" />
        </div>
    @endif

    <x-breadcrumbs :items="$breadcrumbs ?? []" />

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900 sm:text-2xl">{{ $heading ?? 'Produk' }}</h1>
            <p class="text-sm text-gray-500">{{ $products->total() }} produk ditemukan</p>
        </div>

        {{-- Sort --}}
        <form method="GET" class="flex items-center gap-2" id="sortForm">
            @foreach ($filters as $k => $v)
                @if ($k !== 'sort' && !is_array($v) && $v !== null && $v !== '')
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endif
            @endforeach
            <label for="sort" class="text-sm text-gray-500">Urutkan</label>
            <select name="sort" id="sort" onchange="document.getElementById('sortForm').submit()" class="form-select w-auto text-sm">
                @foreach ($sortOptions as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['sort'] ?? 'relevance') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
    </div>

    {{-- Active filter chips --}}
    @php
        $qc = request()->query();
        $mkUrl = fn ($params) => url()->current().(! empty($params) ? '?'.http_build_query($params) : '');
        $clean = fn ($c) => collect($c)->reject(fn ($v) => $v === [] || $v === null || $v === '')->all();
        $condMap = ['new' => 'Baru', 'new_minor_defect' => 'Baru - Minor Defect', 'new_project_surplus' => 'Baru - Sisa Proyek', 'open_box' => 'Open Box', 'display_unit' => 'Bekas Display', 'used' => 'Bekas Pakai'];
        $flagLabels = ['in_stock' => 'Stok tersedia', 'ready' => 'Siap kirim', 'promo' => 'Sedang promo', 'new' => 'Produk baru', 'clearance' => 'Clearance', 'quotation' => 'Minta penawaran'];
        $chips = [];
        if (! empty($qc['q'])) $chips[] = ['label' => '"'.$qc['q'].'"', 'url' => $mkUrl($clean(collect($qc)->except('q')))];
        if (! empty($qc['category']) && empty($category)) {
            $catName = \App\Models\Category::where('slug', $qc['category'])->value('name') ?? $qc['category'];
            $chips[] = ['label' => $catName, 'url' => $mkUrl($clean(collect($qc)->except('category')))];
        }
        foreach ((array) ($qc['brand'] ?? []) as $bs) {
            $rest = array_values(array_diff((array) $qc['brand'], [$bs]));
            $chips[] = ['label' => optional($brands->firstWhere('slug', $bs))->name ?? $bs, 'url' => $mkUrl($clean(collect($qc)->put('brand', $rest)))];
        }
        foreach ((array) ($qc['condition'] ?? []) as $cs) {
            $rest = array_values(array_diff((array) $qc['condition'], [$cs]));
            $chips[] = ['label' => $condMap[$cs] ?? $cs, 'url' => $mkUrl($clean(collect($qc)->put('condition', $rest)))];
        }
        if ((($qc['price_min'] ?? '') !== '') || (($qc['price_max'] ?? '') !== '')) {
            $chips[] = ['label' => 'Harga '.(($qc['price_min'] ?? '') !== '' ? rupiah($qc['price_min']) : '0').' – '.(($qc['price_max'] ?? '') !== '' ? rupiah($qc['price_max']) : 'maks'), 'url' => $mkUrl($clean(collect($qc)->except(['price_min', 'price_max'])))];
        }
        if (! empty($qc['rating_min'])) $chips[] = ['label' => $qc['rating_min'].'+ bintang', 'url' => $mkUrl($clean(collect($qc)->except('rating_min')))];
        foreach ($flagLabels as $fk => $fl) {
            if (! empty($qc[$fk])) $chips[] = ['label' => $fl, 'url' => $mkUrl($clean(collect($qc)->except($fk)))];
        }
    @endphp
    @if (count($chips))
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <span class="text-xs font-medium text-gray-500">Filter aktif:</span>
            @foreach ($chips as $chip)
                <a href="{{ $chip['url'] }}" class="inline-flex items-center gap-1 rounded-full border border-gray-300 bg-white px-3 py-1 text-xs text-gray-700 transition hover:border-red-300 hover:text-red-600">
                    {{ $chip['label'] }}
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </a>
            @endforeach
            <a href="{{ url()->current() }}" class="text-xs font-medium text-brand-600 hover:underline">Hapus semua</a>
        </div>
    @endif

    <div class="lg:grid lg:grid-cols-[260px_1fr] lg:gap-6" x-data="{ drawer: false }">
        {{-- Mobile filter button --}}
        <button @click="drawer = true" class="btn-outline mb-4 w-full lg:hidden">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z"/></svg>
            Filter
        </button>

        {{-- Filter sidebar / drawer --}}
        <aside class="fixed inset-0 z-50 lg:static lg:z-auto lg:block" :class="drawer ? 'block' : 'hidden lg:block'">
            <div class="absolute inset-0 bg-black/40 lg:hidden" @click="drawer = false"></div>
            <form method="GET" action="{{ url()->current() }}"
                  class="absolute left-0 top-0 h-full w-80 max-w-[85%] overflow-y-auto bg-white p-4 lg:static lg:h-auto lg:w-full lg:max-w-none lg:rounded-xl lg:border lg:border-gray-200">
                <div class="mb-3 flex items-center justify-between lg:hidden">
                    <span class="font-bold">Filter</span>
                    <button type="button" @click="drawer = false" aria-label="Tutup">&times;</button>
                </div>

                @if (!empty($filters['q']))<input type="hidden" name="q" value="{{ $filters['q'] }}">@endif
                <input type="hidden" name="sort" value="{{ $filters['sort'] ?? '' }}">

                {{-- Price --}}
                <div class="border-b border-gray-100 py-3">
                    <p class="mb-2 text-sm font-semibold text-gray-700">Rentang Harga</p>
                    <div class="flex items-center gap-2">
                        <input type="number" name="price_min" value="{{ $filters['price_min'] ?? '' }}" placeholder="Min" class="form-input text-sm" min="0">
                        <span class="text-gray-400">–</span>
                        <input type="number" name="price_max" value="{{ $filters['price_max'] ?? '' }}" placeholder="Maks" class="form-input text-sm" min="0">
                    </div>
                </div>

                {{-- Categories --}}
                @if (empty($category) && isset($rootCategories))
                    <div class="border-b border-gray-100 py-3">
                        <p class="mb-2 text-sm font-semibold text-gray-700">Kategori</p>
                        <div class="max-h-48 space-y-1 overflow-y-auto">
                            @foreach ($rootCategories as $cat)
                                <label class="flex items-center gap-2 text-sm text-gray-600">
                                    <input type="radio" name="category" value="{{ $cat->slug }}" @checked(($filters['category'] ?? '') === $cat->slug) class="text-brand-600 focus:ring-brand-500">
                                    {{ $cat->name }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Brands --}}
                <div class="border-b border-gray-100 py-3">
                    <p class="mb-2 text-sm font-semibold text-gray-700">Brand</p>
                    <div class="max-h-48 space-y-1 overflow-y-auto">
                        @foreach ($brands as $brand)
                            <label class="flex items-center gap-2 text-sm text-gray-600">
                                <input type="checkbox" name="brand[]" value="{{ $brand->slug }}" @checked(in_array($brand->slug, (array)($filters['brand'] ?? []))) class="rounded text-brand-600 focus:ring-brand-500">
                                {{ $brand->name }}
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Condition --}}
                <div class="border-b border-gray-100 py-3">
                    <p class="mb-2 text-sm font-semibold text-gray-700">Kondisi</p>
                    @foreach (['new' => 'Baru', 'new_minor_defect' => 'Baru - Minor Defect', 'new_project_surplus' => 'Baru - Sisa Proyek', 'open_box' => 'Open Box', 'display_unit' => 'Bekas Display', 'used' => 'Bekas Pakai'] as $val => $label)
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" name="condition[]" value="{{ $val }}" @checked(in_array($val, (array)($filters['condition'] ?? []))) class="rounded text-brand-600 focus:ring-brand-500">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>

                {{-- Rating --}}
                <div class="border-b border-gray-100 py-3">
                    <p class="mb-2 text-sm font-semibold text-gray-700">Rating Minimum</p>
                    @foreach ([4 => '4+ bintang', 3 => '3+ bintang'] as $val => $label)
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input type="radio" name="rating_min" value="{{ $val }}" @checked((string)($filters['rating_min'] ?? '') === (string)$val) class="text-brand-600 focus:ring-brand-500">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>

                {{-- Flags --}}
                <div class="py-3">
                    <p class="mb-2 text-sm font-semibold text-gray-700">Lainnya</p>
                    @foreach (['in_stock' => 'Stok tersedia', 'ready' => 'Siap kirim', 'promo' => 'Sedang promo', 'new' => 'Produk baru', 'clearance' => 'Clearance', 'quotation' => 'Perlu penawaran'] as $flag => $label)
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" name="{{ $flag }}" value="1" @checked(!empty($filters[$flag])) class="rounded text-brand-600 focus:ring-brand-500">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>

                <div class="sticky bottom-0 -mx-4 mt-2 flex gap-2 border-t border-gray-100 bg-white px-4 py-3 lg:static lg:mx-0 lg:border-0 lg:p-0 lg:pt-2">
                    <button type="submit" class="btn-primary flex-1">Tampilkan {{ $products->total() }} Produk</button>
                    <a href="{{ url()->current() }}" class="btn-outline">Reset</a>
                </div>
            </form>
        </aside>

        {{-- Product grid --}}
        <div>
            @if ($products->isEmpty())
                <div class="card grid place-items-center gap-2 p-12 text-center">
                    <p class="text-lg font-semibold text-gray-700">Produk tidak ditemukan</p>
                    <p class="text-sm text-gray-500">Coba ubah kata kunci atau filter Anda.</p>
                    <a href="{{ route('products.index') }}" class="btn-outline mt-2">Lihat semua produk</a>
                </div>
            @else
                <div class="-mx-4 grid grid-cols-2 gap-2 px-2 sm:mx-0 sm:grid-cols-3 sm:gap-3 sm:px-0 xl:grid-cols-4">
                    @foreach ($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>
                <div class="mt-6">{{ $products->links() }}</div>
            @endif
        </div>
    </div>
@endsection
