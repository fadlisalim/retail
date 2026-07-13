@extends('layouts.admin')

@section('title', 'Brand')

@section('content')
    <x-admin.page-header title="Brand" subtitle="Merek / produsen">
        <x-slot:actions>
            <a href="{{ route('admin.brands.create') }}" class="btn-primary">Tambah Brand</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Slug</th>
                    <th class="px-4 py-3">Website</th>
                    <th class="px-4 py-3 text-center">Produk</th>
                    <th class="px-4 py-3 text-center">Urutan</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($brands as $brand)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-800">
                            {{ $brand->name }}
                            @if ($brand->is_featured)
                                <span class="badge ml-1 bg-amber-100 text-amber-700">Unggulan</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $brand->slug }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $brand->website ?: '—' }}</td>
                        <td class="px-4 py-3 text-center text-gray-500">{{ $brand->products_count }}</td>
                        <td class="px-4 py-3 text-center text-gray-500">{{ $brand->sort_order }}</td>
                        <td class="px-4 py-3 text-center">
                            @if ($brand->is_active)
                                <span class="badge bg-green-100 text-green-700">Aktif</span>
                            @else
                                <span class="badge bg-gray-100 text-gray-600">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.brands.edit', $brand) }}" class="text-brand-700 hover:underline">Edit</a>
                            <form action="{{ route('admin.brands.destroy', $brand) }}" method="POST" class="ml-3 inline"
                                  onsubmit="return confirm('Hapus brand ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Belum ada brand.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $brands->links() }}</div>
@endsection
