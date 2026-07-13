@extends('layouts.admin')

@section('title', 'Ubah Banner')

@section('content')
    <x-admin.page-header title="Ubah Banner" :subtitle="$banner->title">
        <x-slot:actions>
            <a href="{{ route('admin.banners.index') }}" class="btn-outline">Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form action="{{ route('admin.banners.update', $banner) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.banners._form')
    </form>
@endsection
