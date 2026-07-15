@extends('layouts.admin')

@section('title', 'Afiliasi')

@section('content')
    <x-admin.page-header title="Afiliasi" subtitle="Kelola afiliator & verifikasi pendaftaran">
        <x-slot:actions>
            <a href="{{ route('admin.affiliates.payouts') }}" class="btn-outline">Penarikan Dana</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('admin.affiliates.index') }}" class="rounded-full px-3 py-1 {{ ! $status ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-600' }}">Semua</a>
        @foreach ($statuses as $st)
            <a href="{{ route('admin.affiliates.index', ['status' => $st['value']]) }}"
               class="rounded-full px-3 py-1 {{ $status === $st['value'] ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-600' }}">
                {{ $st['label'] }}
                @if ($st['value'] === 'pending' && $counts['pending'] > 0)<span class="ml-1 rounded-full bg-amber-500 px-1.5 text-xs text-white">{{ $counts['pending'] }}</span>@endif
            </a>
        @endforeach
    </div>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Afiliator</th>
                    <th class="px-4 py-3">Kode</th>
                    <th class="px-4 py-3 text-center">Klik</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Daftar</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($affiliates as $a)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.affiliates.show', $a) }}" class="font-medium text-brand-700 hover:underline">{{ $a->full_name }}</a>
                            <div class="text-xs text-gray-400">{{ $a->user?->email }}</div>
                        </td>
                        <td class="px-4 py-3 font-mono text-gray-600">{{ $a->code }}</td>
                        <td class="px-4 py-3 text-center text-gray-500">{{ $a->clicks_count }}</td>
                        <td class="px-4 py-3"><x-status-pill :color="$a->status->color()" :label="$a->status->label()" /></td>
                        <td class="px-4 py-3 text-right text-gray-400">{{ $a->created_at->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($a->status->value === 'pending')
                                <form action="{{ route('admin.affiliates.verify', $a) }}" method="POST" class="inline">
                                    @csrf
                                    <button class="btn-primary px-3 py-1 text-xs">Verifikasi</button>
                                </form>
                            @else
                                <a href="{{ route('admin.affiliates.show', $a) }}" class="text-brand-600 hover:underline">Detail</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">Belum ada afiliator.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $affiliates->links() }}</div>
@endsection
