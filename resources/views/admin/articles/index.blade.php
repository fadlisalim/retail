@extends('layouts.admin')

@section('title', 'Artikel')

@section('content')
    <x-admin.page-header title="Artikel" subtitle="Blog & artikel">
        <x-slot:actions>
            <a href="{{ route('admin.articles.create') }}" class="btn-primary">Tambah Artikel</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <th class="px-4 py-3">Judul</th>
                    <th class="px-4 py-3">Penulis</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3">Publikasi</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($articles as $article)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                @if ($article->cover_path)
                                    <img src="{{ asset('storage/'.$article->cover_path) }}" alt="{{ $article->title }}"
                                         class="h-10 w-16 flex-none rounded object-cover">
                                @endif
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-gray-800">{{ $article->title }}</p>
                                    <p class="text-xs text-gray-400">{{ $article->slug }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $article->author?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            @if ($article->is_published)
                                <span class="badge bg-green-100 text-green-700">Terbit</span>
                            @else
                                <span class="badge bg-gray-100 text-gray-600">Draft</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $article->published_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.articles.edit', $article) }}" class="text-brand-700 hover:underline">Edit</a>
                            <form action="{{ route('admin.articles.destroy', $article) }}" method="POST" class="ml-3 inline"
                                  onsubmit="return confirm('Hapus artikel ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Belum ada artikel.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $articles->links() }}</div>
@endsection
