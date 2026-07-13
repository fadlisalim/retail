@extends('layouts.admin')

@section('title', 'Produk')

@section('content')
    <x-admin.page-header title="Produk" subtitle="Katalog produk">
        <x-slot:actions>
            <a href="{{ route('admin.products.create') }}" class="btn-primary">Tambah Produk</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <div class="min-w-[220px] flex-1">
            <label class="input-label" for="q">Cari</label>
            <input type="search" name="q" id="q" value="{{ $q }}" placeholder="Nama atau SKU" class="form-input">
        </div>
        <div>
            <label class="input-label" for="status">Status</label>
            <select name="status" id="status" class="form-select">
                <option value="">Semua status</option>
                @foreach (['draft' => 'Draft', 'published' => 'Terbit', 'archived' => 'Arsip'] as $val => $lbl)
                    <option value="{{ $val }}" @selected($status === $val)>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-primary">Filter</button>
        @if ($q !== '' || $status)
            <a href="{{ route('admin.products.index') }}" class="btn-outline">Reset</a>
        @endif
    </form>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <th class="px-4 py-3">Produk</th>
                    <th class="px-4 py-3">Kategori</th>
                    <th class="px-4 py-3 text-right">Harga</th>
                    <th class="px-4 py-3 text-center">Stok</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($products as $product)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <img src="{{ $product->primaryImageUrl() }}" alt="{{ $product->name }}"
                                     class="h-10 w-10 flex-none rounded object-cover">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-gray-800">{{ $product->name }}</p>
                                    <p class="text-xs text-gray-400">{{ $product->sku }} &middot; {{ $product->brand?->name ?? '—' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $product->category?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <span class="font-semibold text-gray-800">{{ rupiah($product->effectivePrice()) }}</span>
                            @if ($product->isOnSale())
                                <div class="text-xs text-gray-400 line-through">{{ rupiah($product->price) }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $stockClass = $product->stock <= 0
                                    ? 'font-semibold text-red-600'
                                    : ($product->isLowStock() ? 'font-semibold text-amber-600' : 'text-gray-700');
                            @endphp
                            <span class="{{ $stockClass }}">{{ $product->stock }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $statusBadge = [
                                    'draft' => 'bg-gray-100 text-gray-600',
                                    'published' => 'bg-green-100 text-green-700',
                                    'archived' => 'bg-red-100 text-red-700',
                                ][$product->status] ?? 'bg-gray-100 text-gray-600';
                                $statusLabel = ['draft' => 'Draft', 'published' => 'Terbit', 'archived' => 'Arsip'][$product->status] ?? $product->status;
                            @endphp
                            <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.products.edit', $product) }}" class="text-brand-700 hover:underline">Edit</a>
                            <form action="{{ route('admin.products.destroy', $product) }}" method="POST" class="ml-3 inline"
                                  onsubmit="return confirm('Hapus produk ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Belum ada produk.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $products->links() }}</div>
@endsection
