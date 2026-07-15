@extends('layouts.admin')

@section('title', 'Afiliator: '.$affiliate->full_name)

@section('content')
    <x-admin.page-header :title="$affiliate->full_name" :subtitle="'Kode '.$affiliate->code">
        <x-slot:actions>
            <a href="{{ route('admin.affiliates.index') }}" class="btn-outline">Kembali</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            {{-- Balance --}}
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ([['Ditahan', $stats['pending']], ['Disetujui', $stats['approved']], ['Dibayar', $stats['paid']], ['Saldo', $stats['available']]] as [$l, $v])
                    <div class="card p-4"><p class="text-xs text-gray-500">{{ $l }}</p><p class="mt-1 font-bold text-gray-900">{{ rupiah($v) }}</p></div>
                @endforeach
            </div>

            {{-- Commissions --}}
            <div class="card overflow-hidden">
                <div class="border-b border-gray-100 px-5 py-3"><h2 class="font-semibold text-gray-900">Komisi Terbaru</h2></div>
                @if ($commissions->isEmpty())
                    <p class="px-5 py-8 text-center text-sm text-gray-400">Belum ada komisi.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr><th class="px-5 py-2">Tanggal</th><th class="px-5 py-2">Pesanan</th><th class="px-5 py-2">Produk</th><th class="px-5 py-2 text-right">Komisi</th><th class="px-5 py-2">Status</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($commissions as $c)
                                    <tr>
                                        <td class="whitespace-nowrap px-5 py-3 text-gray-500">{{ $c->created_at->format('d M Y') }}</td>
                                        <td class="px-5 py-3 text-gray-700">{{ $c->order?->order_number ?? '—' }}</td>
                                        <td class="px-5 py-3 text-gray-600">{{ $c->product?->name ?? '—' }}</td>
                                        <td class="px-5 py-3 text-right font-semibold text-gray-800">{{ rupiah($c->amount) }}</td>
                                        <td class="px-5 py-3"><x-status-pill :color="$c->status->color()" :label="$c->status->label()" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Payouts --}}
            @if ($payouts->isNotEmpty())
                <div class="card overflow-hidden">
                    <div class="border-b border-gray-100 px-5 py-3"><h2 class="font-semibold text-gray-900">Penarikan</h2></div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr><th class="px-5 py-2">Tanggal</th><th class="px-5 py-2 text-right">Jumlah</th><th class="px-5 py-2">Status</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($payouts as $p)
                                    <tr>
                                        <td class="whitespace-nowrap px-5 py-3 text-gray-500">{{ $p->requested_at?->format('d M Y') ?? $p->created_at->format('d M Y') }}</td>
                                        <td class="px-5 py-3 text-right font-semibold text-gray-800">{{ rupiah($p->amount) }}</td>
                                        <td class="px-5 py-3"><x-status-pill :color="$p->status->color()" :label="$p->status->label()" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <div class="space-y-5">
            {{-- Status actions --}}
            <div class="card space-y-3 p-5">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold text-gray-900">Status</h2>
                    <x-status-pill :color="$affiliate->status->color()" :label="$affiliate->status->label()" />
                </div>
                @if ($affiliate->verified_at)
                    <p class="text-xs text-gray-400">Diverifikasi {{ $affiliate->verified_at->format('d M Y') }} @if ($affiliate->verifier) oleh {{ $affiliate->verifier->name }} @endif</p>
                @endif
                <div class="flex flex-col gap-2">
                    @if (in_array($affiliate->status->value, ['pending', 'rejected', 'suspended']))
                        <form action="{{ route('admin.affiliates.verify', $affiliate) }}" method="POST">@csrf<button class="btn-primary w-full">Verifikasi & Aktifkan</button></form>
                    @endif
                    @if ($affiliate->status->value === 'active')
                        <form action="{{ route('admin.affiliates.suspend', $affiliate) }}" method="POST">@csrf<button class="btn-outline w-full">Tangguhkan</button></form>
                    @endif
                    @if ($affiliate->status->value === 'pending')
                        <form action="{{ route('admin.affiliates.reject', $affiliate) }}" method="POST">@csrf<button class="btn-outline w-full text-red-600">Tolak</button></form>
                    @endif
                </div>
            </div>

            {{-- KYC data --}}
            <div class="card space-y-2 p-5 text-sm">
                <h2 class="font-semibold text-gray-900">Data Diri</h2>
                <dl class="space-y-1.5 text-gray-600">
                    <div class="flex justify-between gap-3"><dt class="text-gray-400">User</dt><dd class="text-right">{{ $affiliate->user?->name }}<br><span class="text-xs text-gray-400">{{ $affiliate->user?->email }}</span></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-400">NIK</dt><dd class="text-right font-mono">{{ $affiliate->id_number ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-400">HP</dt><dd class="text-right">{{ $affiliate->phone ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-400">NPWP</dt><dd class="text-right font-mono">{{ $affiliate->npwp ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-400">Channel</dt><dd class="text-right">{{ $affiliate->channel ?? '—' }}</dd></div>
                    <div><dt class="text-gray-400">Alamat</dt><dd class="mt-0.5">{{ $affiliate->address ?? '—' }}</dd></div>
                </dl>
            </div>

            {{-- KYC documents (private) --}}
            <div class="card space-y-3 p-5 text-sm">
                <h2 class="font-semibold text-gray-900">Dokumen Verifikasi</h2>
                @if ($affiliate->status->value === 'pending')
                    <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">Periksa keaslian KTP, selfie, & NPWP sebelum menyetujui.</p>
                @endif
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <p class="mb-1 text-xs text-gray-400">Foto KTP</p>
                        @if ($affiliate->ktp_photo_path)
                            <a href="{{ route('admin.affiliates.document', [$affiliate, 'ktp']) }}" target="_blank" class="block overflow-hidden rounded-lg border border-gray-200">
                                <img src="{{ route('admin.affiliates.document', [$affiliate, 'ktp']) }}" alt="KTP" class="h-28 w-full object-cover transition hover:opacity-90">
                            </a>
                        @else
                            <p class="text-gray-400">—</p>
                        @endif
                    </div>
                    <div>
                        <p class="mb-1 text-xs text-gray-400">Foto Selfie</p>
                        @if ($affiliate->selfie_photo_path)
                            <a href="{{ route('admin.affiliates.document', [$affiliate, 'selfie']) }}" target="_blank" class="block overflow-hidden rounded-lg border border-gray-200">
                                <img src="{{ route('admin.affiliates.document', [$affiliate, 'selfie']) }}" alt="Selfie" class="h-28 w-full object-cover transition hover:opacity-90">
                            </a>
                        @else
                            <p class="text-gray-400">—</p>
                        @endif
                    </div>
                </div>
                <p class="text-xs text-gray-400">Klik gambar untuk memperbesar. Dokumen bersifat rahasia.</p>
            </div>

            {{-- Bank --}}
            <div class="card space-y-2 p-5 text-sm">
                <h2 class="font-semibold text-gray-900">Rekening</h2>
                <p class="text-gray-600">{{ $affiliate->bank_name ?? '—' }}<br>{{ $affiliate->bank_account_number ?? '—' }}<br><span class="text-gray-400">a.n. {{ $affiliate->bank_account_holder ?? '—' }}</span></p>
            </div>
        </div>
    </div>
@endsection
