@extends('layouts.admin')

@section('title', 'Ubah Brand')

@section('content')
    <x-admin.page-header title="Ubah Brand" :subtitle="$brand->name">
        <x-slot:actions>
            <a href="{{ route('admin.brands.index') }}" class="btn-outline">Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form action="{{ route('admin.brands.update', $brand) }}" method="POST">
        @csrf
        @method('PUT')
        @include('admin.brands._form')
    </form>
@endsection
