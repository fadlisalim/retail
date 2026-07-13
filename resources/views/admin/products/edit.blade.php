@extends('layouts.admin')

@section('title', 'Ubah Produk')

@section('content')
    <x-admin.page-header title="Ubah Produk" :subtitle="$product->name">
        <x-slot:actions>
            <a href="{{ route('admin.products.index') }}" class="btn-outline">Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form action="{{ route('admin.products.update', $product) }}" method="POST">
        @csrf
        @method('PUT')
        @include('admin.products._form')
    </form>
@endsection
