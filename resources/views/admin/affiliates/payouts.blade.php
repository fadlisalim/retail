@extends('layouts.admin')

@section('title', 'Penarikan Afiliasi')

@section('content')
    <x-admin.page-header title="Penarikan Dana Afiliasi" subtitle="Proses permintaan pencairan komisi">
        <x-slot:actions>
            <a href="{{ route('admin.affiliates.index') }}" class="btn-outline">Daftar Afiliator</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('admin.affiliates.payouts') }}" class="rounded-full px-3 py-1 {{ ! $status ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-600' }}">Semua</a>
        @foreach (['requested' => 'Diajukan', 'approved' => 'Disetujui', 'paid' => 'Dibayar', 'rejected' => 'Ditolak'] as $val => $label)
            <a href="{{ route('admin.affiliates.payouts', ['status' => $val]) }}" class="rounded-full px-3 py-1 {{ $status === $val ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-600' }}">
                {{ $label }}
                @if ($val === 'requested' && $pendingCount > 0)<span class="ml-1 rounded-full bg-amber-500 px-1.5 text-xs text-white">{{ $pendingCount }}</span>@endif
            </a>
        @endforeach
    </div>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Afiliator</th>
                    <th class="px-4 py-3">Rekening</th>
                    <th class="px-4 py-3 text-right">Jumlah</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Diajukan</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($payouts as $p)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.affiliates.show', $p->affiliate) }}" class="font-medium text-brand-700 hover:underline">{{ $p->affiliate->full_name }}</a>
                            <div class="text-xs text-gray-400">{{ $p->affiliate->code }}</div>
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $p->bank_name }} {{ $p->bank_account_number }}
                            <div class="text-xs text-gray-400">a.n. {{ $p->bank_account_holder }}</div>
                        </td>
                        <td class="px-4 py-3 text-right font-semibold text-gray-800">{{ rupiah($p->amount) }}</td>
                        <td class="px-4 py-3"><x-status-pill :color="$p->status->color()" :label="$p->status->label()" /></td>
                        <td class="px-4 py-3 text-right text-gray-400">{{ $p->requested_at?->format('d M Y') ?? $p->created_at->format('d M Y') }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-1">
                                @if ($p->status->value === 'requested')
                                    <form action="{{ route('admin.affiliates.payouts.approve', $p) }}" method="POST">@csrf<button class="btn-outline px-2 py-1 text-xs">Setujui</button></form>
                                    <form action="{{ route('admin.affiliates.payouts.reject', $p) }}" method="POST">@csrf<button class="btn-outline px-2 py-1 text-xs text-red-600">Tolak</button></form>
                                @endif
                                @if (in_array($p->status->value, ['requested', 'approved']))
                                    <form action="{{ route('admin.affiliates.payouts.paid', $p) }}" method="POST"
                                          onsubmit="this.reference.value = prompt('No. referensi transfer (opsional):') || '';">
                                        @csrf
                                        <input type="hidden" name="reference">
                                        <button class="btn-primary px-2 py-1 text-xs">Tandai Lunas</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">Belum ada permintaan penarikan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $payouts->links() }}</div>
@endsection
