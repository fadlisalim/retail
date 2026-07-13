@extends('layouts.admin')

@section('title', 'Kategori')

@section('content')
    <x-admin.page-header title="Kategori" subtitle="Struktur kategori produk">
        <x-slot:actions>
            <a href="{{ route('admin.categories.create') }}" class="btn-primary">Tambah Kategori</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Induk</th>
                    <th class="px-4 py-3">Slug</th>
                    <th class="px-4 py-3 text-center">Urutan</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($categories as $category)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <span class="font-medium text-gray-800" style="padding-left: {{ (int) $category->depth * 18 }}px">
                                @if ($category->depth > 0)<span class="text-gray-300">└</span> @endif{{ $category->name }}
                            </span>
                            @if ($category->is_featured)
                                <span class="badge ml-1 bg-amber-100 text-amber-700">Unggulan</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $category->parent?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $category->slug }}</td>
                        <td class="px-4 py-3 text-center text-gray-500">{{ $category->sort_order }}</td>
                        <td class="px-4 py-3 text-center">
                            @if ($category->is_active)
                                <span class="badge bg-green-100 text-green-700">Aktif</span>
                            @else
                                <span class="badge bg-gray-100 text-gray-600">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.categories.edit', $category) }}" class="text-brand-700 hover:underline">Edit</a>
                            <form action="{{ route('admin.categories.destroy', $category) }}" method="POST" class="ml-3 inline"
                                  onsubmit="return confirm('Hapus kategori ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Belum ada kategori.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
