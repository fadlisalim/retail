@extends('layouts.storefront')

@section('title', 'Review Saya — '.config('rekasurya.company.brand_name'))
@section('noindex', 'noindex')

@section('content')
    <x-breadcrumbs :items="[['label' => 'Akun', 'url' => route('account.dashboard')], ['label' => 'Review']]" />

    <div class="grid gap-6 lg:grid-cols-[240px_1fr]">
        @include('partials.account-nav')

        <div class="min-w-0 space-y-8">
            <h1 class="text-xl font-bold text-gray-800">Review Saya</h1>

            {{-- Awaiting review --}}
            <section>
                <h2 class="mb-3 text-base font-semibold text-gray-800">Menunggu Ulasan</h2>
                @if ($reviewable->isEmpty())
                    <div class="card px-4 py-8 text-center text-sm text-gray-500">Tidak ada produk yang menunggu ulasan.</div>
                @else
                    <div class="space-y-4">
                        @foreach ($reviewable as $item)
                            <article class="card p-4">
                                <div class="flex gap-3">
                                    @if ($item->product)
                                        <img src="{{ $item->product->primaryImageUrl() }}" alt="{{ $item->name }}" loading="lazy" class="h-14 w-14 shrink-0 rounded-lg border border-gray-100 object-cover">
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium text-gray-800">{{ $item->name }}</p>
                                        <p class="text-xs text-gray-400">SKU: {{ $item->sku }}</p>
                                    </div>
                                </div>

                                <form action="{{ route('account.reviews.store') }}" method="POST" class="mt-4 grid gap-3 sm:grid-cols-2">
                                    @csrf
                                    <input type="hidden" name="order_item_id" value="{{ $item->id }}">

                                    <x-form.select name="rating" label="Rating" required
                                        :options="[5 => '5 — Sangat Baik', 4 => '4 — Baik', 3 => '3 — Cukup', 2 => '2 — Kurang', 1 => '1 — Buruk']" :selected="5" />
                                    <x-form.input name="title" label="Judul Ulasan" />

                                    <div class="sm:col-span-2">
                                        <x-form.textarea name="comment" label="Komentar" rows="3" hint="Ceritakan pengalaman Anda dengan produk ini." />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <button type="submit" class="btn-primary">Kirim Ulasan</button>
                                    </div>
                                </form>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- Existing reviews --}}
            <section>
                <h2 class="mb-3 text-base font-semibold text-gray-800">Ulasan Saya</h2>
                @if ($reviews->isEmpty())
                    <div class="card px-4 py-8 text-center text-sm text-gray-500">Anda belum menulis ulasan.</div>
                @else
                    <div class="space-y-4">
                        @foreach ($reviews as $review)
                            <article class="card p-4">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="font-medium text-gray-800">{{ $review->product?->name ?? 'Produk' }}</p>
                                    <span class="text-xs text-gray-400">{{ $review->created_at?->translatedFormat('d M Y') }}</span>
                                </div>
                                <div class="mt-1"><x-stars :rating="$review->rating" /></div>
                                @if ($review->title)
                                    <p class="mt-2 font-semibold text-gray-800">{{ $review->title }}</p>
                                @endif
                                @if ($review->comment)
                                    <p class="mt-1 text-sm text-gray-600">{{ $review->comment }}</p>
                                @endif
                                @if (! $review->is_visible)
                                    <p class="mt-2 text-xs text-amber-600">Menunggu moderasi admin.</p>
                                @endif
                                @if ($review->admin_reply)
                                    <div class="mt-3 rounded-lg bg-brand-50 px-3 py-2 text-sm">
                                        <p class="text-xs font-semibold text-brand-700">Balasan Penjual</p>
                                        <p class="text-gray-700">{{ $review->admin_reply }}</p>
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
@endsection
