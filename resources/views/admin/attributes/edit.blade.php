@extends('layouts.admin')

@section('title', 'Ubah Atribut')

@section('content')
    <x-admin.page-header title="Ubah Atribut" :subtitle="$attribute->name">
        <x-slot:actions>
            <a href="{{ route('admin.attributes.index') }}" class="btn-outline">Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form action="{{ route('admin.attributes.update', $attribute) }}" method="POST">
        @csrf
        @method('PUT')
        @include('admin.attributes._form')
    </form>
@endsection
