@extends('layouts.admin')

@section('title', 'Tambah Kategori')

@section('content')
    <x-admin.page-header title="Tambah Kategori">
        <x-slot:actions>
            <a href="{{ route('admin.categories.index') }}" class="btn-outline">Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form action="{{ route('admin.categories.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @include('admin.categories._form')
    </form>
@endsection
