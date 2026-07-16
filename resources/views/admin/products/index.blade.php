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

    @php
        $defaultAffiliateRate = (float) setting('affiliate.default_rate', 2.5);
    @endphp
    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <th class="px-4 py-3">Produk</th>
                    <th class="px-4 py-3">Kategori</th>
                    <th class="px-4 py-3 text-right">Harga</th>
                    <th class="px-4 py-3 text-center">Stok</th>
                    <th class="px-4 py-3 text-center">Komisi</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            @forelse ($products as $product)
                @php
                    $statusBadge = [
                        'draft' => 'bg-gray-100 text-gray-600',
                        'published' => 'bg-green-100 text-green-700',
                        'archived' => 'bg-red-100 text-red-700',
                    ][$product->status] ?? 'bg-gray-100 text-gray-600';
                    $statusLabel = ['draft' => 'Draft', 'published' => 'Terbit', 'archived' => 'Arsip'][$product->status] ?? $product->status;
                    $stockClass = $product->stock <= 0
                        ? 'font-semibold text-red-600'
                        : ($product->isLowStock() ? 'font-semibold text-amber-600' : 'text-gray-700');
                    $sellPrice = $product->isOnSale() ? $product->sale_price : $product->price;
                    $comparePrice = $product->isOnSale() ? $product->price : null;
                    $isVariable = $product->product_type === 'variable';
                    $fmtRate = fn ($r) => rtrim(rtrim(number_format((float) $r, 2, ',', '.'), '0'), ',').'%';
                @endphp
                <tbody class="border-t border-gray-100" x-data="{ open: false }">
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
                            <span class="{{ $stockClass }}">{{ $isVariable ? $product->stock.'*' : $product->stock }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if ($product->affiliate_rate !== null)
                                <span class="font-semibold text-gray-800">{{ $fmtRate($product->affiliate_rate) }}</span>
                            @else
                                <span class="text-gray-400" title="Pakai komisi default">{{ $fmtRate($defaultAffiliateRate) }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button type="button" @click="open = !open" class="font-medium text-amber-600 hover:underline" :class="open && 'text-amber-700'">
                                <span x-show="!open">Edit cepat</span><span x-show="open" x-cloak>Tutup</span>
                            </button>
                            <a href="{{ route('admin.products.edit', $product) }}" class="ml-3 text-brand-700 hover:underline">Edit</a>
                            <form action="{{ route('admin.products.destroy', $product) }}" method="POST" class="ml-3 inline"
                                  onsubmit="return confirm('Hapus produk ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Hapus</button>
                            </form>
                        </td>
                    </tr>

                    {{-- Fast-edit row --}}
                    <tr x-show="open" x-cloak class="bg-amber-50/40">
                        <td colspan="7" class="px-4 py-4">
                            <form action="{{ route('admin.products.quick', $product) }}" method="POST"
                                  class="flex flex-wrap items-end gap-3">
                                @csrf
                                @method('PUT')
                                <div>
                                    <label class="input-label text-xs">Harga Jual (Rp)</label>
                                    <input type="number" step="0.01" min="0" name="price" value="{{ old('price', $sellPrice) }}" required class="form-input w-36">
                                </div>
                                <div>
                                    <label class="input-label text-xs">Harga Coret</label>
                                    <input type="number" step="0.01" min="0" name="compare_price" value="{{ old('compare_price', $comparePrice) }}" placeholder="—" class="form-input w-36">
                                </div>
                                <div>
                                    <label class="input-label text-xs">Stok</label>
                                    @if ($isVariable)
                                        <input type="number" value="{{ $product->stock }}" disabled class="form-input w-24 bg-gray-100" title="Stok varian diatur per varian di halaman Edit">
                                    @else
                                        <input type="number" min="0" name="stock" value="{{ $product->stock }}" class="form-input w-24">
                                    @endif
                                </div>
                                <div>
                                    <label class="input-label text-xs">Komisi Afiliasi (%)</label>
                                    <input type="number" step="0.01" min="0" max="100" name="affiliate_rate" value="{{ $product->affiliate_rate }}" placeholder="default {{ rtrim(rtrim(number_format((float) setting('affiliate.default_rate', 2.5), 1), '0'), '.') }}%" class="form-input w-32">
                                </div>
                                <div>
                                    <label class="input-label text-xs">Status</label>
                                    <select name="status" class="form-select w-32">
                                        @foreach (['draft' => 'Draft', 'published' => 'Terbit', 'archived' => 'Arsip'] as $val => $lbl)
                                            <option value="{{ $val }}" @selected($product->status === $val)>{{ $lbl }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="btn-primary">Simpan</button>
                                <button type="button" @click="open = false" class="btn-outline">Batal</button>
                                @if ($isVariable)
                                    <p class="w-full text-xs text-amber-600">*Stok produk varian diatur per varian di halaman Edit.</p>
                                @endif
                            </form>
                        </td>
                    </tr>
                </tbody>
            @empty
                <tbody>
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Belum ada produk.</td></tr>
                </tbody>
            @endforelse
        </table>
    </div>

    <div class="mt-4">{{ $products->links() }}</div>
@endsection
