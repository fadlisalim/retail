@extends('layouts.admin')

@section('title', 'Banner')

@section('content')
    @php
        $positionLabels = ['hero' => 'Hero', 'promo' => 'Promo', 'quotation' => 'Quotation'];
    @endphp

    <x-admin.page-header title="Banner" subtitle="Banner promosi storefront">
        <x-slot:actions>
            <a href="{{ route('admin.banners.create') }}" class="btn-primary">Tambah Banner</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <th class="px-4 py-3">Banner</th>
                    <th class="px-4 py-3">Posisi</th>
                    <th class="px-4 py-3 text-center">Urutan</th>
                    <th class="px-4 py-3">Periode</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($banners as $banner)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                @if ($banner->image_desktop_path)
                                    <img src="{{ asset('storage/'.$banner->image_desktop_path) }}" alt="{{ $banner->title }}"
                                         class="h-10 w-16 flex-none rounded object-cover">
                                @else
                                    <div class="grid h-10 w-16 flex-none place-items-center rounded bg-gray-100 text-[10px] text-gray-400">No img</div>
                                @endif
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-gray-800">{{ $banner->title }}</p>
                                    @if ($banner->subtitle)<p class="truncate text-xs text-gray-400">{{ $banner->subtitle }}</p>@endif
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $positionLabels[$banner->position] ?? $banner->position }}</td>
                        <td class="px-4 py-3 text-center text-gray-500">{{ $banner->sort_order }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            {{ $banner->starts_at?->format('d/m/Y') ?? '—' }} &rarr; {{ $banner->ends_at?->format('d/m/Y') ?? '∞' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if ($banner->is_active)
                                <span class="badge bg-green-100 text-green-700">Aktif</span>
                            @else
                                <span class="badge bg-gray-100 text-gray-600">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.banners.edit', $banner) }}" class="text-brand-700 hover:underline">Edit</a>
                            <form action="{{ route('admin.banners.destroy', $banner) }}" method="POST" class="ml-3 inline"
                                  onsubmit="return confirm('Hapus banner ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Belum ada banner.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $banners->links() }}</div>
@endsection
