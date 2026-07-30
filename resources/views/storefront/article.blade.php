@extends('layouts.storefront')

@section('title', ($article->meta_title ?: $article->title).' — '.config('rekasurya.company.brand_name'))
@section('meta_description', $article->meta_description ?: ($article->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($article->content), 155)))
@section('og_type', 'article')
@if ($article->cover_path)
    @section('og_image', asset('storage/'.($article->og_image_path ?: $article->cover_path)))
@endif

@push('head')
    @php
        $ld = [
            '@type' => 'Article',
            'headline' => $article->title,
            'description' => $article->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($article->content), 155),
            'datePublished' => $article->published_at?->toAtomString(),
            'dateModified' => $article->updated_at?->toAtomString(),
            'image' => $article->cover_path ? asset('storage/'.$article->cover_path) : null,
            'author' => ['@type' => 'Organization', 'name' => config('rekasurya.company.brand_name')],
            'publisher' => ['@type' => 'Organization', 'name' => config('rekasurya.company.legal_name')],
            'mainEntityOfPage' => url()->current(),
        ];
    @endphp
    <script type="application/ld+json">{!! schema_ld($ld) !!}</script>
@endpush

@section('content')
    <x-breadcrumbs :items="[
        ['label' => 'Artikel', 'url' => route('articles.index')],
        ['label' => $article->title],
    ]" />

    <article class="mx-auto max-w-3xl">
        <header class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">{{ $article->title }}</h1>
            @if ($article->published_at)
                <time datetime="{{ $article->published_at->toDateString() }}" class="mt-2 block text-sm text-gray-400">
                    {{ $article->published_at->translatedFormat('d F Y') }}
                </time>
            @endif
        </header>

        @if ($article->cover_path)
            <img src="{{ asset('storage/'.$article->cover_path) }}" alt="{{ $article->title }}" class="mb-6 w-full rounded-xl object-cover">
        @endif

        <div class="prose max-w-none prose-headings:text-gray-900 prose-a:text-brand-600">
            {!! $article->content !!}
        </div>
    </article>

    @if ($related->isNotEmpty())
        <section class="mt-12 border-t border-gray-200 pt-8">
            <h2 class="mb-4 text-lg font-bold text-gray-900">Artikel Terkait</h2>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($related as $r)
                    <article class="card group flex flex-col overflow-hidden transition hover:shadow-md">
                        <a href="{{ route('articles.show', $r->slug) }}" class="block aspect-video overflow-hidden bg-brand-50">
                            @if ($r->cover_path)
                                <img src="{{ asset('storage/'.$r->cover_path) }}" alt="{{ $r->title }}" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                            @else
                                <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-brand-500 to-brand-700 p-4 text-center text-sm font-semibold text-white">{{ config('rekasurya.company.brand_name') }}</div>
                            @endif
                        </a>
                        <div class="flex flex-1 flex-col p-4">
                            @if ($r->published_at)
                                <time datetime="{{ $r->published_at->toDateString() }}" class="text-xs text-gray-400">{{ $r->published_at->translatedFormat('d M Y') }}</time>
                            @endif
                            <h3 class="mt-1 line-clamp-2 font-semibold text-gray-800 group-hover:text-brand-700">
                                <a href="{{ route('articles.show', $r->slug) }}">{{ $r->title }}</a>
                            </h3>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
@endsection
