@extends('layouts.admin')

@section('title', 'Tambah Artikel')

@section('content')
    <x-admin.page-header title="Tambah Artikel">
        <x-slot:actions>
            <a href="{{ route('admin.articles.index') }}" class="btn-outline">Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form action="{{ route('admin.articles.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @include('admin.articles._form')
    </form>
@endsection
