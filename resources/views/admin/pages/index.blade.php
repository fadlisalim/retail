@extends('layouts.admin')

@section('title', 'Halaman')

@section('content')
    <x-admin.page-header title="Halaman" subtitle="Halaman statis (CMS)">
        <x-slot:actions>
            <a href="{{ route('admin.pages.create') }}" class="btn-primary">Tambah Halaman</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <th class="px-4 py-3">Judul</th>
                    <th class="px-4 py-3">Slug</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3">Diperbarui</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($pages as $page)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $page->title }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $page->slug }}</td>
                        <td class="px-4 py-3 text-center">
                            @if ($page->is_published)
                                <span class="badge bg-green-100 text-green-700">Terbit</span>
                            @else
                                <span class="badge bg-gray-100 text-gray-600">Draft</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $page->updated_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.pages.edit', $page) }}" class="text-brand-700 hover:underline">Edit</a>
                            <form action="{{ route('admin.pages.destroy', $page) }}" method="POST" class="ml-3 inline"
                                  onsubmit="return confirm('Hapus halaman ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Belum ada halaman.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $pages->links() }}</div>
@endsection
