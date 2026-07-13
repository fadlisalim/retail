@extends('layouts.admin')

@section('title', 'Tambah FAQ')

@section('content')
    <x-admin.page-header title="Tambah FAQ">
        <x-slot:actions>
            <a href="{{ route('admin.faqs.index') }}" class="btn-outline">Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form action="{{ route('admin.faqs.store') }}" method="POST">
        @csrf
        @include('admin.faqs._form')
    </form>
@endsection
