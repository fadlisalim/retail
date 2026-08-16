@extends('layouts.admin')

@section('title', 'Produk')

@section('content')
    <x-admin.page-header title="Produk" subtitle="Katalog produk">
        <x-slot:actions>
            <a href="{{ route('admin.products.create') }}" class="btn-primary">Tambah Produk</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <div class="min-w-[200px] flex-1">
            <label class="input-label" for="q">Cari</label>
            <input type="search" name="q" id="q" value="{{ $q }}" placeholder="Nama atau SKU" class="form-input">
        </div>
        <div>
            <label class="input-label" for="category">Kategori</label>
            <select name="category" id="category" class="form-select">
                <option value="">Semua kategori</option>
                @foreach ($categoryOptions as $cid => $clabel)
                    <option value="{{ $cid }}" @selected((string) $categoryId === (string) $cid)>{{ $clabel }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="input-label" for="brand">Brand</label>
            <select name="brand" id="brand" class="form-select">
                <option value="">Semua brand</option>
                @foreach ($brandOptions as $bid => $blabel)
                    <option value="{{ $bid }}" @selected((string) $brandId === (string) $bid)>{{ $blabel }}</option>
                @endforeach
            </select>
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
        @if ($q !== '' || $status || $categoryId || $brandId)
            <a href="{{ route('admin.products.index') }}" class="btn-outline">Reset</a>
        @endif
    </form>

    @php
        $defaultAffiliateRate = (float) setting('affiliate.default_rate', 2.5);
        $statusOptions = ['draft' => 'Draft', 'published' => 'Terbit', 'archived' => 'Arsip'];
    @endphp

    {{-- Bulk status. Kotak centangnya ada DI DALAM tabel (yang sudah punya form
         hapus & edit cepat), jadi form-nya diletakkan di luar dan dirujuk lewat
         atribut form="..." — form bersarang tidak valid di HTML. --}}
    <div x-data="{ selected: [], pageIds: {{ $products->pluck('id')->toJson() }} }">
        <form id="bulk-status-form" method="POST" action="{{ route('admin.products.bulk-status') }}" x-ref="bulkForm">
            @csrf
            <input type="hidden" name="status" x-ref="bulkStatus">
        </form>

        <div x-show="selected.length" x-cloak
             class="mb-3 flex flex-wrap items-center gap-2 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3">
            <span class="text-sm font-medium text-brand-800">
                <span x-text="selected.length"></span> produk dipilih
            </span>
            <span class="text-sm text-brand-700">— ubah status ke:</span>
            <button type="button"
                    x-on:click="$refs.bulkStatus.value = 'published'; $refs.bulkForm.submit()"
                    class="rounded-md bg-green-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-green-700">Terbit</button>
            <button type="button"
                    x-on:click="$refs.bulkStatus.value = 'draft'; $refs.bulkForm.submit()"
                    class="rounded-md bg-gray-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-gray-700">Draft</button>
            <button type="button"
                    x-on:click="if (confirm('Arsipkan ' + selected.length + ' produk? Produk arsip tidak tampil di toko.')) { $refs.bulkStatus.value = 'archived'; $refs.bulkForm.submit() }"
                    class="rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-red-700">Arsip</button>
            <button type="button" x-on:click="selected = []"
                    class="ml-auto text-xs font-medium text-brand-700 underline">Batalkan pilihan</button>
        </div>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <th class="px-4 py-3">
                        <input type="checkbox" title="Pilih semua di halaman ini"
                               class="h-4 w-4 rounded border-gray-300 text-brand-600"
                               x-on:change="selected = $event.target.checked ? [...pageIds] : []"
                               :checked="selected.length === pageIds.length && pageIds.length > 0">
                    </th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3">Aksi</th>
                    <th class="px-4 py-3">Produk</th>
                    <th class="px-4 py-3">Kategori</th>
                    <th class="px-4 py-3 text-right">Harga</th>
                    <th class="px-4 py-3 text-center">Stok</th>
                    <th class="px-4 py-3 text-center">Komisi</th>
                    <th class="px-4 py-3 text-center">Dilihat</th>
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
                    <tr class="hover:bg-gray-50" :class="selected.includes({{ $product->id }}) && 'bg-brand-50/60'">
                        <td class="px-4 py-3 align-top">
                            <input type="checkbox" form="bulk-status-form" name="ids[]" value="{{ $product->id }}"
                                   x-model.number="selected" aria-label="Pilih {{ $product->name }}"
                                   class="h-4 w-4 rounded border-gray-300 text-brand-600">
                        </td>
                        <td class="px-4 py-3 text-center align-top">
                            <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                        </td>
                        <td class="px-4 py-3 align-top">
                            <div class="flex items-center gap-1.5 whitespace-nowrap">
                                <button type="button" @click="open = !open"
                                        class="rounded-md bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700 transition hover:bg-amber-100"
                                        :class="open && 'bg-amber-100'">
                                    <span x-show="!open">Edit cepat</span><span x-show="open" x-cloak>Tutup</span>
                                </button>
                                <a href="{{ route('admin.products.edit', $product) }}"
                                   class="rounded-md bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700 transition hover:bg-brand-100">Edit</a>
                                <form action="{{ route('admin.products.destroy', $product) }}" method="POST" class="inline"
                                      onsubmit="return confirm('Hapus produk ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-md bg-red-50 px-2.5 py-1 text-xs font-medium text-red-600 transition hover:bg-red-100">Hapus</button>
                                </form>
                            </div>
                        </td>
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
                            <span class="font-semibold text-gray-800" title="Pengunjung unik">{{ number_format((int) $product->unique_views, 0, ',', '.') }}</span>
                            <div class="text-[11px] text-gray-400" title="Total kunjungan (termasuk berulang)">{{ number_format((int) $product->view_count, 0, ',', '.') }} total</div>
                        </td>
                    </tr>

                    {{-- Fast-edit row --}}
                    <tr x-show="open" x-cloak class="bg-amber-50/40">
                        <td colspan="9" class="px-4 py-4">
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
                    <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">Belum ada produk.</td></tr>
                </tbody>
            @endforelse
        </table>
    </div>
    </div>

    <div class="mt-4">{{ $products->links() }}</div>
@endsection
