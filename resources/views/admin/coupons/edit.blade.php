@extends('layouts.admin')

@section('title', 'Ubah Voucher')

@section('content')
    <x-admin.page-header title="Ubah Voucher" :subtitle="$coupon->code">
        <x-slot:actions>
            <a href="{{ route('admin.coupons.index') }}" class="btn-outline">Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form action="{{ route('admin.coupons.update', $coupon) }}" method="POST">
        @csrf
        @method('PUT')
        @include('admin.coupons._form')
    </form>
@endsection
