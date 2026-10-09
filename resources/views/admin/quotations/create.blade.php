@extends('layouts.admin')

@section('title', 'Susun Penawaran')

@section('content')
    <x-admin.page-header title="Susun Penawaran" subtitle="Pilih produk dari katalog atau tulis produk/jasa manual, lalu simpan draf, cek PDF, dan kirim ke customer">
        <x-slot:actions><a href="{{ route('admin.quotations.index') }}" class="btn-outline">&larr; Kembali</a></x-slot:actions>
    </x-admin.page-header>

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"><ul class="list-disc pl-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('admin.quotations.store') }}" class="space-y-6">
        @csrf
        <div class="card p-5">
            <h2 class="mb-3 font-semibold text-gray-900">1. Customer</h2>
            @include('admin.quotations._header-fields', ['q' => null])
        </div>

        <div class="card p-5">
            <h2 class="mb-3 font-semibold text-gray-900">2. Produk / jasa & harga</h2>
            @include('admin.quotations._items-editor', ['quotation' => null])
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="submit" name="action" value="draft" class="btn-outline">Simpan draf</button>
            <button type="submit" name="action" value="send" class="btn-primary" onclick="return confirm('Kirim penawaran ke customer sekarang? Nomor penawaran akan diterbitkan.')">Simpan &amp; kirim ke customer</button>
        </div>
    </form>
@endsection
