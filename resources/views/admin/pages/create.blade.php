@extends('layouts.admin')

@section('title', 'Tambah Halaman')

@section('content')
    <x-admin.page-header title="Tambah Halaman">
        <x-slot:actions>
            <a href="{{ route('admin.pages.index') }}" class="btn-outline">Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form action="{{ route('admin.pages.store') }}" method="POST">
        @csrf
        @include('admin.pages._form')
    </form>
@endsection
