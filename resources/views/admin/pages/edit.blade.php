@extends('layouts.admin')

@section('title', 'Ubah Halaman')

@section('content')
    <x-admin.page-header title="Ubah Halaman" :subtitle="$page->title">
        <x-slot:actions>
            <a href="{{ route('admin.pages.index') }}" class="btn-outline">Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form action="{{ route('admin.pages.update', $page) }}" method="POST">
        @csrf
        @method('PUT')
        @include('admin.pages._form')
    </form>
@endsection
