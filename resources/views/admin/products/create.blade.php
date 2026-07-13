@extends('layouts.admin')

@section('title', 'Tambah Produk')

@section('content')
    <x-admin.page-header title="Tambah Produk">
        <x-slot:actions>
            <a href="{{ route('admin.products.index') }}" class="btn-outline">Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form action="{{ route('admin.products.store') }}" method="POST">
        @csrf
        @include('admin.products._form')
    </form>
@endsection
