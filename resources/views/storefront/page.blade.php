@extends('layouts.storefront')

@section('title', ($page->meta_title ?: $page->title).' — '.config('rekasurya.company.brand_name'))
@section('meta_description', $page->meta_description ?: \Illuminate\Support\Str::limit(strip_tags($page->content), 155))

@section('content')
    <x-breadcrumbs :items="[['label' => $page->title]]" />

    <article class="card p-6 sm:p-8">
        <h1 class="mb-6 text-2xl font-bold text-gray-900 sm:text-3xl">{{ $page->title }}</h1>
        <div class="prose max-w-none prose-headings:text-gray-900 prose-a:text-brand-600">
            {!! $page->content !!}
        </div>
    </article>
@endsection
