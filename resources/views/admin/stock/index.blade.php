@extends('layouts.admin')

@section('title', 'Stok')

@section('content')
    <x-admin.page-header title="Stok" subtitle="Penyesuaian stok produk" />

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <div class="min-w-[220px] flex-1">
            <label class="input-label" for="q">Cari</label>
            <input type="search" name="q" id="q" value="{{ $q }}" placeholder="Nama atau SKU" class="form-input">
        </div>
        <button type="submit" class="btn-primary">Filter</button>
        @if ($q !== '')
            <a href="{{ route('admin.stock.index') }}" class="btn-outline">Reset</a>
        @endif
    </form>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <th class="px-4 py-3">Produk</th>
                    <th class="px-4 py-3 text-center">Stok</th>
                    <th class="px-4 py-3 text-center">Min</th>
                    <th class="px-4 py-3">Penyesuaian</th>
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
                                    <p class="text-xs text-gray-400">{{ $product->sku }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $stockClass = $product->stock <= 0
                                    ? 'font-semibold text-red-600'
                                    : ($product->isLowStock() ? 'font-semibold text-amber-600' : 'font-semibold text-gray-700');
                            @endphp
                            <span class="{{ $stockClass }}">{{ $product->stock }}</span>
                            @if ($product->isLowStock())
                                <div class="text-[10px] uppercase text-amber-600">Menipis</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center text-gray-500">{{ $product->min_stock }}</td>
                        <td class="px-4 py-3">
                            <form action="{{ route('admin.stock.adjust', $product) }}" method="POST"
                                  class="flex flex-wrap items-center gap-2">
                                @csrf
                                <input type="number" name="delta" required placeholder="±Qty" class="form-input w-24"
                                       aria-label="Jumlah penyesuaian">
                                <select name="type" class="form-select w-44" aria-label="Jenis pergerakan">
                                    @foreach ($types as $type)
                                        <option value="{{ $type->value }}" @selected($type->value === 'adjustment')>{{ $type->label() }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="note" placeholder="Catatan (opsional)" class="form-input w-44"
                                       aria-label="Catatan">
                                <button type="submit" class="btn-primary">Simpan</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">Tidak ada produk.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $products->links() }}</div>
@endsection
