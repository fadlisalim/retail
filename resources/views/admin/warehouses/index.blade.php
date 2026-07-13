@extends('layouts.admin')

@section('title', 'Gudang')

@section('content')
    <x-admin.page-header title="Gudang" subtitle="Lokasi penyimpanan stok">
        <x-slot:actions>
            <a href="{{ route('admin.warehouses.create') }}" class="btn-primary">Tambah Gudang</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <th class="px-4 py-3">Kode</th>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Kota</th>
                    <th class="px-4 py-3 text-center">SKU Stok</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($warehouses as $warehouse)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-800">
                            {{ $warehouse->code }}
                            @if ($warehouse->is_default)
                                <span class="badge ml-1 bg-brand-100 text-brand-700">Default</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-700">{{ $warehouse->name }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $warehouse->city ?: '—' }}</td>
                        <td class="px-4 py-3 text-center text-gray-500">{{ $warehouse->stocks_count }}</td>
                        <td class="px-4 py-3 text-center">
                            @if ($warehouse->is_active)
                                <span class="badge bg-green-100 text-green-700">Aktif</span>
                            @else
                                <span class="badge bg-gray-100 text-gray-600">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.warehouses.edit', $warehouse) }}" class="text-brand-700 hover:underline">Edit</a>
                            <form action="{{ route('admin.warehouses.destroy', $warehouse) }}" method="POST" class="ml-3 inline"
                                  onsubmit="return confirm('Hapus gudang ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Belum ada gudang.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $warehouses->links() }}</div>
@endsection
