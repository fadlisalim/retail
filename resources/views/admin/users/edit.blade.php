@extends('layouts.admin')

@section('title', 'Edit User Admin')

@section('content')
    <x-admin.page-header :title="'Edit: ' . $user->name" subtitle="Perbarui akun staf">
        <x-slot:actions>
            <a href="{{ route('admin.users.index') }}" class="btn-outline">&larr; Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    @include('admin.users._form', ['action' => route('admin.users.update', $user), 'isEdit' => true])
@endsection
