@extends('layouts.storefront')

@section('title', 'Dokumentasi Pengerjaan — '.brand())
@section('meta_description', 'Foto asli proses penyiapan, testing, packing, dan pengiriman pesanan pelanggan '.brand().'. Lihat sendiri bagaimana kami menangani setiap pesanan.')

@section('content')
    <x-breadcrumbs :items="[['label' => 'Dokumentasi']]" />

    <header class="mb-5">
        <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">Dokumentasi Pengerjaan</h1>
        <p class="mt-1 max-w-2xl text-sm text-gray-600">
            Setiap pesanan kami dokumentasikan — mulai penyiapan barang, testing unit, packing, sampai pengiriman.
            Ini foto aslinya, bukan stok foto. Nama &amp; data pelanggan tidak kami tampilkan.
        </p>
        @if ($total > 0)
            <p class="mt-2 text-sm font-medium text-brand-700">{{ number_format($total, 0, ',', '.') }} foto dokumentasi</p>
        @endif
    </header>

    {{-- Stage filter --}}
    <div class="mb-5 flex gap-2 overflow-x-auto pb-1 text-sm [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <a href="{{ route('documentation') }}"
           class="flex-none rounded-full px-3 py-1.5 font-medium transition {{ $stage === null ? 'bg-brand-600 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">Semua</a>
        @foreach (\App\Models\OrderDocumentation::STAGES as $key => $label)
            @continue (! isset($counts[$key]))
            <a href="{{ route('documentation', ['tahap' => $key]) }}"
               class="flex-none rounded-full px-3 py-1.5 font-medium transition {{ $stage === $key ? 'bg-brand-600 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">
                {{ \App\Models\OrderDocumentation::STAGE_ICONS[$key] }} {{ $label }}
                <span class="text-xs opacity-70">({{ $counts[$key] }})</span>
            </a>
        @endforeach
    </div>

    @if ($photos->isEmpty())
        <div class="card grid place-items-center gap-2 p-12 text-center">
            <p class="text-lg font-semibold text-gray-700">Belum ada dokumentasi</p>
            <p class="max-w-md text-sm text-gray-500">Foto proses pengerjaan pesanan akan tampil di sini.</p>
            <a href="{{ route('products.index') }}" class="btn-outline mt-2">Lihat Produk</a>
        </div>
    @else
        <div x-data="{ zoom: null, caption: '' }">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($photos as $photo)
                    <figure class="group overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                        <button type="button" @click="zoom = @js($photo->url()); caption = @js(trim(($photo->caption ? $photo->caption.' · ' : '').$photo->stageLabel()))"
                                class="block w-full">
                            <img src="{{ $photo->url() }}" alt="{{ $photo->caption ?? $photo->stageLabel() }}"
                                 class="aspect-square w-full object-cover transition duration-300 group-hover:scale-105" loading="lazy">
                        </button>
                        <figcaption class="space-y-0.5 p-2">
                            <p class="text-xs font-semibold text-brand-700">{{ $photo->stageIcon() }} {{ $photo->stageLabel() }}</p>
                            @if ($photo->caption)
                                <p class="line-clamp-2 text-xs text-gray-600">{{ $photo->caption }}</p>
                            @endif
                            <p class="text-[11px] text-gray-400">{{ $photo->created_at->translatedFormat('F Y') }}</p>
                        </figcaption>
                    </figure>
                @endforeach
            </div>

            <div class="mt-6">{{ $photos->links() }}</div>

            {{-- Lightbox (tap anywhere to close; works on mobile & desktop) --}}
            <div x-show="zoom" x-cloak @click="zoom = null" @keydown.escape.window="zoom = null"
                 class="fixed inset-0 z-[80] flex flex-col items-center justify-center gap-3 bg-black/90 p-4">
                <img :src="zoom" alt="" class="max-h-[80vh] max-w-full object-contain">
                <p class="text-center text-sm text-white/80" x-text="caption"></p>
                <button type="button" class="absolute right-4 top-4 grid h-10 w-10 place-items-center rounded-full bg-white/15 text-2xl text-white" aria-label="Tutup">&times;</button>
            </div>
        </div>
    @endif

    {{-- Trust close: documentation is a buying argument, so offer the next step. --}}
    <section class="mt-10 rounded-2xl border border-brand-100 bg-gradient-to-br from-brand-50 to-white p-5 text-center">
        <h2 class="text-lg font-bold text-gray-900">Pesanan Anda juga kami dokumentasikan</h2>
        <p class="mx-auto mt-1 max-w-xl text-sm text-gray-600">
            Setiap unit dites sebelum dikirim, dan fotonya bisa Anda lihat di halaman lacak pesanan.
            Ada pertanyaan sebelum pesan? Tim kami siap bantu.
        </p>
        <div class="mt-3 flex flex-wrap justify-center gap-2">
            <a href="{{ route('products.index') }}" class="btn-primary">Lihat Produk</a>
            <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-cs-chat'))" class="btn-outline">💬 Tanya Dulu</button>
        </div>
    </section>
@endsection
