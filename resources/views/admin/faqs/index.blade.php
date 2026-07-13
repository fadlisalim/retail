@extends('layouts.admin')

@section('title', 'FAQ')

@section('content')
    <x-admin.page-header title="FAQ" subtitle="Pertanyaan yang sering diajukan">
        <x-slot:actions>
            <a href="{{ route('admin.faqs.create') }}" class="btn-primary">Tambah FAQ</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <th class="px-4 py-3">Kategori</th>
                    <th class="px-4 py-3">Pertanyaan</th>
                    <th class="px-4 py-3 text-center">Urutan</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($faqs as $faq)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-500">{{ $faq->category }}</td>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $faq->question }}</td>
                        <td class="px-4 py-3 text-center text-gray-500">{{ $faq->sort_order }}</td>
                        <td class="px-4 py-3 text-center">
                            @if ($faq->is_active)
                                <span class="badge bg-green-100 text-green-700">Aktif</span>
                            @else
                                <span class="badge bg-gray-100 text-gray-600">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.faqs.edit', $faq) }}" class="text-brand-700 hover:underline">Edit</a>
                            <form action="{{ route('admin.faqs.destroy', $faq) }}" method="POST" class="ml-3 inline"
                                  onsubmit="return confirm('Hapus FAQ ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Belum ada FAQ.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $faqs->links() }}</div>
@endsection
