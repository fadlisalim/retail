@extends('layouts.admin')

@section('title', 'Tambah Banner')

@section('content')
    <x-admin.page-header title="Tambah Banner">
        <x-slot:actions>
            <a href="{{ route('admin.banners.index') }}" class="btn-outline">Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form action="{{ route('admin.banners.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @include('admin.banners._form')
    </form>
@endsection
