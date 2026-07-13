@extends('layouts.admin')

@section('title', 'Atribut')

@section('content')
    @php
        $typeLabels = ['text' => 'Teks', 'number' => 'Angka', 'select' => 'Pilihan', 'boolean' => 'Ya/Tidak'];
    @endphp

    <x-admin.page-header title="Atribut" subtitle="Atribut & spesifikasi produk">
        <x-slot:actions>
            <a href="{{ route('admin.attributes.create') }}" class="btn-primary">Tambah Atribut</a>
        </x-slot:actions>
    </x-admin.page-header>

    @forelse ($attributes as $groupName => $items)
        <div class="mb-6">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">{{ $groupName }}</h2>
            <div class="card overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Slug</th>
                            <th class="px-4 py-3">Tipe</th>
                            <th class="px-4 py-3">Satuan</th>
                            <th class="px-4 py-3 text-center">Filter</th>
                            <th class="px-4 py-3 text-center">Banding</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($items as $attribute)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $attribute->name }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $attribute->slug }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $typeLabels[$attribute->type] ?? $attribute->type }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $attribute->unit ?: '—' }}</td>
                                <td class="px-4 py-3 text-center">
                                    @if ($attribute->is_filterable)
                                        <span class="badge bg-green-100 text-green-700">Ya</span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if ($attribute->is_comparable)
                                        <span class="badge bg-green-100 text-green-700">Ya</span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('admin.attributes.edit', $attribute) }}" class="text-brand-700 hover:underline">Edit</a>
                                    <form action="{{ route('admin.attributes.destroy', $attribute) }}" method="POST" class="ml-3 inline"
                                          onsubmit="return confirm('Hapus atribut ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="card p-8 text-center text-gray-400">Belum ada atribut.</div>
    @endforelse
@endsection
