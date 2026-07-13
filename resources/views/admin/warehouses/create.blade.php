@extends('layouts.admin')

@section('title', 'Tambah Gudang')

@section('content')
    <x-admin.page-header title="Tambah Gudang">
        <x-slot:actions>
            <a href="{{ route('admin.warehouses.index') }}" class="btn-outline">Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form action="{{ route('admin.warehouses.store') }}" method="POST">
        @csrf
        @include('admin.warehouses._form')
    </form>
@endsection
