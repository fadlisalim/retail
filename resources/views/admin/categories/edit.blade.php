@extends('layouts.admin')

@section('title', 'Ubah Kategori')

@section('content')
    <x-admin.page-header title="Ubah Kategori" :subtitle="$category->name">
        <x-slot:actions>
            <a href="{{ route('admin.categories.index') }}" class="btn-outline">Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form action="{{ route('admin.categories.update', $category) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.categories._form')
    </form>
@endsection
