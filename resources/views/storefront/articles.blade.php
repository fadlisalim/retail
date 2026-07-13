@extends('layouts.storefront')

@section('title', 'Artikel & Wawasan — '.config('rekasurya.company.brand_name'))
@section('meta_description', 'Artikel, panduan, dan wawasan seputar energi terbarukan, panel surya, dan kebutuhan proyek dari '.config('rekasurya.company.brand_name').'.')

@section('content')
    <x-breadcrumbs :items="[['label' => 'Artikel']]" />

    <header class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">Artikel &amp; Wawasan</h1>
        <p class="mt-1 text-sm text-gray-500">Panduan dan berita terbaru seputar energi terbarukan.</p>
    </header>

    @if ($articles->isEmpty())
        <div class="card px-4 py-12 text-center text-sm text-gray-500">Belum ada artikel yang dipublikasikan.</div>
    @else
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($articles as $a)
                <article class="card group flex flex-col overflow-hidden transition hover:shadow-md">
                    <a href="{{ route('articles.show', $a->slug) }}" class="block aspect-video overflow-hidden bg-brand-50">
                        @if ($a->cover_path)
                            <img src="{{ asset('storage/'.$a->cover_path) }}" alt="{{ $a->title }}" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                        @else
                            <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-brand-500 to-brand-700 p-4 text-center text-sm font-semibold text-white">
                                {{ config('rekasurya.company.brand_name') }}
                            </div>
                        @endif
                    </a>
                    <div class="flex flex-1 flex-col p-4">
                        @if ($a->published_at)
                            <time datetime="{{ $a->published_at->toDateString() }}" class="text-xs text-gray-400">{{ $a->published_at->translatedFormat('d F Y') }}</time>
                        @endif
                        <h2 class="mt-1 line-clamp-2 font-semibold text-gray-800 group-hover:text-brand-700">
                            <a href="{{ route('articles.show', $a->slug) }}">{{ $a->title }}</a>
                        </h2>
                        @if ($a->excerpt)
                            <p class="mt-2 line-clamp-3 flex-1 text-sm text-gray-500">{{ $a->excerpt }}</p>
                        @endif
                        <a href="{{ route('articles.show', $a->slug) }}" class="mt-3 text-sm font-medium text-brand-600 hover:underline">Baca selengkapnya →</a>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-8">{{ $articles->links() }}</div>
    @endif
@endsection
