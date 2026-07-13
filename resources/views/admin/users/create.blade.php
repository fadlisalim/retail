@extends('layouts.admin')

@section('title', 'Tambah User Admin')

@section('content')
    <x-admin.page-header title="Tambah User Admin" subtitle="Buat akun staf baru">
        <x-slot:actions>
            <a href="{{ route('admin.users.index') }}" class="btn-outline">&larr; Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    @include('admin.users._form', ['action' => route('admin.users.store'), 'isEdit' => false])
@endsection
