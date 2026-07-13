@extends('layouts.admin')

@section('title', 'Ubah Artikel')

@section('content')
    <x-admin.page-header title="Ubah Artikel" :subtitle="$article->title">
        <x-slot:actions>
            <a href="{{ route('admin.articles.index') }}" class="btn-outline">Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form action="{{ route('admin.articles.update', $article) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.articles._form')
    </form>
@endsection
