@extends('layouts.admin')

@section('title', 'Tambah Voucher')

@section('content')
    <x-admin.page-header title="Tambah Voucher">
        <x-slot:actions>
            <a href="{{ route('admin.coupons.index') }}" class="btn-outline">Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form action="{{ route('admin.coupons.store') }}" method="POST">
        @csrf
        @include('admin.coupons._form')
    </form>
@endsection
