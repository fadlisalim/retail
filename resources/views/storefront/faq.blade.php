@extends('layouts.storefront')

@section('title', 'FAQ — Pertanyaan yang Sering Diajukan — '.config('rekasurya.company.brand_name'))
@section('meta_description', 'Jawaban atas pertanyaan yang sering diajukan seputar pemesanan, pembayaran, pengiriman, dan penawaran proyek di '.config('rekasurya.company.brand_name').'.')

@push('head')
    @php
        $faqEntities = [];
        foreach ($faqs as $items) {
            foreach ($items as $faq) {
                $faqEntities[] = [
                    '@type' => 'Question',
                    'name' => $faq->question,
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($faq->answer)],
                ];
            }
        }
    @endphp
    @if (! empty($faqEntities))
        <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqEntities], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif
@endpush

@section('content')
    <x-breadcrumbs :items="[['label' => 'FAQ']]" />

    <header class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">Pertanyaan yang Sering Diajukan</h1>
        <p class="mt-1 text-sm text-gray-500">Tidak menemukan jawaban? <a href="{{ route('quotations.create') }}" class="font-medium text-brand-600 hover:underline">Hubungi tim kami</a>.</p>
    </header>

    @if ($faqs->isEmpty())
        <div class="card px-4 py-12 text-center text-sm text-gray-500">Belum ada FAQ tersedia.</div>
    @else
        <div class="mx-auto max-w-3xl space-y-8">
            @foreach ($faqs as $category => $items)
                <section>
                    <h2 class="mb-3 text-lg font-semibold text-brand-700">{{ $category ?: 'Umum' }}</h2>
                    <div class="card divide-y divide-gray-100" x-data="{ open: null }">
                        @foreach ($items as $faq)
                            <div>
                                <h3>
                                    <button type="button"
                                            @click="open === {{ $loop->index }} ? open = null : open = {{ $loop->index }}"
                                            :aria-expanded="open === {{ $loop->index }} ? 'true' : 'false'"
                                            class="flex w-full items-center justify-between gap-4 px-4 py-4 text-left">
                                        <span class="font-medium text-gray-800">{{ $faq->question }}</span>
                                        <svg class="h-5 w-5 shrink-0 text-gray-400 transition-transform" :class="open === {{ $loop->index }} ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </button>
                                </h3>
                                <div x-show="open === {{ $loop->index }}" x-cloak x-transition class="px-4 pb-4 text-sm leading-relaxed text-gray-600">
                                    {!! nl2br(e($faq->answer)) !!}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    @endif
@endsection
