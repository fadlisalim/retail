@extends('layouts.admin')

@section('title', 'Review Komisi Afiliator')

@section('content')
    <x-admin.page-header title="Review Komisi Afiliator"
        subtitle="Komisi dari atribusi manual (admin memilih afiliator di input pesanan) — hanya cair setelah disetujui Super Admin">
        <x-slot:actions>
            <a href="{{ route('admin.affiliates.index') }}" class="btn-outline">&larr; Afiliasi</a>
        </x-slot:actions>
    </x-admin.page-header>

    <p class="mb-4 text-xs text-gray-500">
        Komisi dari link referral (cookie) tidak lewat sini — tercatat otomatis. Yang tampil di bawah adalah pesanan yang afiliatornya
        <strong>dipilih manual oleh admin</strong>. Setujui bila memang penjualan afiliator tersebut; tolak bila salah/asal input.
        Disetujui = komisi langsung cair kalau pesanan sudah Selesai, atau ditahan sampai Selesai.
    </p>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Pesanan</th>
                    <th class="px-4 py-3">Afiliator</th>
                    <th class="px-4 py-3">Produk</th>
                    <th class="px-4 py-3 text-right">Dasar</th>
                    <th class="px-4 py-3 text-center">Fee</th>
                    <th class="px-4 py-3 text-right">Komisi</th>
                    <th class="px-4 py-3">Diinput oleh</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($commissions as $c)
                    <tr class="align-top hover:bg-gray-50">
                        <td class="px-4 py-3">
                            @if ($c->order)
                                <a href="{{ route('admin.orders.show', $c->order) }}" class="font-medium text-brand-700 hover:underline">{{ $c->order->order_number }}</a>
                                <div class="text-xs text-gray-400">{{ $c->order->customer_name }} · {{ $c->order->status->label() }} / {{ $c->order->payment_status->label() }}</div>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.affiliates.show', $c->affiliate) }}" class="text-brand-700 hover:underline">{{ $c->affiliate->full_name ?? $c->affiliate->user?->name }}</a>
                            <div class="text-xs text-gray-400">{{ $c->affiliate->code }}</div>
                        </td>
                        <td class="max-w-[220px] px-4 py-3"><span class="line-clamp-2">{{ $c->product?->name ?? '—' }}</span></td>
                        <td class="px-4 py-3 text-right">{{ rupiah($c->base_amount) }}</td>
                        <td class="px-4 py-3 text-center">{{ (float) $c->rate }}%</td>
                        <td class="px-4 py-3 text-right font-semibold">{{ rupiah($c->amount) }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            {{ $c->attributedBy?->name ?? 'Server / command' }}
                            <div class="text-gray-400">{{ $c->created_at?->format('d/m/Y H:i') }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-col gap-2" x-data="{ tolak: false }">
                                <form method="POST" action="{{ route('admin.affiliates.commissions.approve', $c) }}">
                                    @csrf
                                    <button type="submit" class="btn-primary w-full text-xs">✓ Setujui</button>
                                </form>
                                <button type="button" @click="tolak = !tolak" class="text-xs text-red-600 hover:underline">Tolak…</button>
                                <form method="POST" action="{{ route('admin.affiliates.commissions.reject', $c) }}" x-show="tolak" x-cloak class="flex gap-1">
                                    @csrf
                                    <input type="text" name="note" required placeholder="Alasan" class="form-input w-36 text-xs" maxlength="255">
                                    <button type="submit" class="rounded-md bg-red-600 px-2 text-xs font-semibold text-white hover:bg-red-700">Tolak</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-10 text-center text-gray-400">Tidak ada komisi yang menunggu review.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $commissions->links() }}</div>

    @if ($recent->isNotEmpty())
        <h2 class="mb-2 mt-8 font-semibold text-gray-900">Riwayat Review Terakhir</h2>
        <div class="card overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-100 bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr><th class="px-4 py-2">Waktu</th><th class="px-4 py-2">Pesanan</th><th class="px-4 py-2">Afiliator</th><th class="px-4 py-2 text-right">Komisi</th><th class="px-4 py-2">Hasil</th><th class="px-4 py-2">Oleh</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($recent as $c)
                        <tr>
                            <td class="px-4 py-2 text-xs text-gray-400">{{ $c->reviewed_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2">{{ $c->order?->order_number ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $c->affiliate->full_name ?? $c->affiliate->user?->name }}</td>
                            <td class="px-4 py-2 text-right">{{ rupiah($c->amount) }}</td>
                            <td class="px-4 py-2"><x-status-pill :color="$c->status->color()" :label="$c->status->label()" />@if ($c->review_note) <span class="text-xs text-gray-400">— {{ $c->review_note }}</span>@endif</td>
                            <td class="px-4 py-2 text-xs text-gray-500">{{ $c->reviewedBy?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
