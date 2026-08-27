@extends('layouts.storefront')

@section('title', 'Produk & Komisi Afiliasi — '.config('rekasurya.company.brand_name'))
@section('noindex', 'noindex')

@section('content')
    <x-breadcrumbs :items="[
        ['label' => 'Akun', 'url' => route('account.dashboard')],
        ['label' => 'Afiliasi', 'url' => route('account.affiliate.dashboard')],
        ['label' => 'Produk & Komisi'],
    ]" />

    <div class="grid gap-6 lg:grid-cols-[240px_1fr]">
        @include('partials.account-nav')

        <div class="min-w-0 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold text-gray-900 sm:text-2xl">Produk &amp; Komisi</h1>
                    <p class="text-sm text-gray-500">Diurutkan dari fee tertinggi. Salin link produknya, bagikan, dan komisi tercatat otomatis.</p>
                </div>
                <a href="{{ route('account.affiliate.dashboard') }}" class="btn-outline text-sm">&larr; Dashboard</a>
            </div>

            <form method="GET" class="flex gap-2">
                <input type="search" name="q" value="{{ $q }}" placeholder="Cari produk…" class="form-input flex-1">
                <button class="btn-primary">Cari</button>
                @if ($q !== '')<a href="{{ route('account.affiliate.products') }}" class="btn-outline">Reset</a>@endif
            </form>

            <div class="space-y-3">
                @forelse ($products as $product)
                    @php
                        $rate = $product->affiliate_rate !== null ? (float) $product->affiliate_rate : $defaultRate;
                        $fmtRate = rtrim(rtrim(number_format($rate, 2, ',', '.'), '0'), ',');
                        $price = $product->effectivePrice();
                        $activeVariants = $product->variants;
                        $isVariable = $product->product_type === 'variable' && $activeVariants->isNotEmpty();
                    @endphp
                    <div class="card p-4"
                         x-data="{ copied: false,
                                   link: @js($affiliate->productReferralUrl($product)),
                                   copy() { navigator.clipboard.writeText(this.link); this.copied = true; setTimeout(() => this.copied = false, 1500); } }">
                        <div class="flex items-start gap-3">
                            <img src="{{ $product->primaryImageUrl() }}" alt="{{ $product->name }}"
                                 class="h-16 w-16 flex-none rounded-lg object-cover">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <a href="{{ route('products.show', $product->slug) }}" target="_blank"
                                           class="line-clamp-2 font-semibold text-gray-800 hover:text-brand-700">{{ $product->name }}</a>
                                        <p class="text-xs text-gray-400">{{ $product->brand?->name ?? '—' }} · {{ $isVariable ? 'mulai ' : '' }}{{ rupiah($price) }}</p>
                                    </div>
                                    <div class="flex-none text-right">
                                        <span class="badge bg-green-100 text-base font-bold text-green-700">{{ $fmtRate }}%</span>
                                        <p class="mt-0.5 text-xs text-gray-500" title="Perkiraan komisi per unit terjual">
                                            ≈ {{ rupiah(round($price * $rate / 100)) }}{{ $isVariable ? '+' : '' }} /unit
                                        </p>
                                    </div>
                                </div>

                                @if ($isVariable)
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        @foreach ($activeVariants as $variant)
                                            <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] text-gray-600"
                                                  title="Komisi ≈ {{ rupiah(round((float) $variant->price * $rate / 100)) }}">
                                                {{ $variant->name }} · {{ rupiah($variant->price) }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                    <input type="text" readonly :value="link" @click="$event.target.select()"
                                           class="form-input flex-1 bg-gray-50 text-xs">
                                    <button type="button" @click="copy()" class="btn-primary whitespace-nowrap text-sm">
                                        <span x-show="!copied">Salin Link</span>
                                        <span x-show="copied" x-cloak>Tersalin ✓</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="card p-8 text-center text-gray-400">Tidak ada produk yang cocok.</div>
                @endforelse
            </div>

            <div>{{ $products->links() }}</div>

            <p class="text-xs text-gray-400">
                Perkiraan komisi dihitung dari harga jual saat ini × fee produk (fee default {{ rtrim(rtrim(number_format($defaultRate, 2, ',', '.'), '0'), ',') }}% bila produk tidak diberi fee khusus).
                Komisi final mengikuti harga saat pesanan terjadi dan status pesanan selesai. Atribusi berlaku 30 hari sejak link diklik.
            </p>
        </div>
    </div>
@endsection
