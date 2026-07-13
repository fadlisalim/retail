@extends('layouts.admin')

@section('title', 'Ubah Gudang')

@section('content')
    <x-admin.page-header title="Ubah Gudang" :subtitle="$warehouse->name">
        <x-slot:actions>
            <a href="{{ route('admin.warehouses.index') }}" class="btn-outline">Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form action="{{ route('admin.warehouses.update', $warehouse) }}" method="POST">
        @csrf
        @method('PUT')
        @include('admin.warehouses._form')
    </form>
@endsection
