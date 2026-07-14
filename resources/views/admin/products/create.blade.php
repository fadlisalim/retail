@extends('layouts.admin')

@section('title', 'Tambah Produk')

@section('content')
    <x-admin.page-header title="Tambah Produk">
        <x-slot:actions>
            <a href="{{ route('admin.products.index') }}" class="btn-outline">Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mb-4 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800">
        Simpan produk dulu, lalu buka lagi untuk menambah <strong>gambar, dokumen/datasheet PDF, dan video YouTube</strong>.
    </div>

    <form action="{{ route('admin.products.store') }}" method="POST">
        @csrf
        @include('admin.products._form')
    </form>
@endsection
