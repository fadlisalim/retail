@extends('layouts.admin')

@section('title', 'Tambah Atribut')

@section('content')
    <x-admin.page-header title="Tambah Atribut">
        <x-slot:actions>
            <a href="{{ route('admin.attributes.index') }}" class="btn-outline">Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form action="{{ route('admin.attributes.store') }}" method="POST">
        @csrf
        @include('admin.attributes._form')
    </form>
@endsection
