@extends('layouts.admin')

@section('title', 'Tambah Brand')

@section('content')
    <x-admin.page-header title="Tambah Brand">
        <x-slot:actions>
            <a href="{{ route('admin.brands.index') }}" class="btn-outline">Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form action="{{ route('admin.brands.store') }}" method="POST">
        @csrf
        @include('admin.brands._form')
    </form>
@endsection
