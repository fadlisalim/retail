@extends('layouts.admin')

@section('title', 'CS Assistant')

@section('content')
    <x-admin.page-header title="CS Assistant" subtitle="Log percakapan & statistik chatbot toko" />

    {{-- Headline stats (30 hari terakhir) --}}
    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @php
            $cards = [
                ['Total Pesan', number_format($summary['messages'], 0, ',', '.'), 'text-gray-900'],
                ['Sesi (Pengunjung)', number_format($summary['sessions'], 0, ',', '.'), 'text-gray-900'],
                ['Terjawab AI', $summary['answered_rate'].'%', 'text-brand-700'],
                ['Fallback / Gagal', number_format($summary['fallbacks'], 0, ',', '.'), $summary['fallbacks'] > 0 ? 'text-amber-600' : 'text-gray-900'],
            ];
        @endphp
        @foreach ($cards as [$label, $value, $color])
            <div class="card p-4">
                <p class="text-xs text-gray-500">{{ $label }}</p>
                <p class="mt-1 text-2xl font-bold {{ $color }}">{{ $value }}</p>
            </div>
        @endforeach
    </div>
    <p class="mb-6 text-xs text-gray-400">Ringkasan 30 hari terakhir · transkrip disimpan {{ $logRetention }} hari, statistik {{ $statsRetention }} hari.</p>

    {{-- Tren harian --}}
    @if ($trend->isNotEmpty())
        <div class="card mb-6 p-4">
            <h3 class="mb-3 text-sm font-semibold text-gray-700">Tren Harian</h3>
            <div class="flex items-end gap-1 overflow-x-auto" style="height: 120px">
                @foreach ($trend as $d)
                    <div class="flex min-w-[14px] flex-1 flex-col items-center justify-end" title="{{ $d['day'] }}: {{ $d['messages'] }} pesan, {{ $d['fallbacks'] }} fallback">
                        <div class="flex w-full max-w-[28px] flex-col justify-end rounded-t bg-brand-500" style="height: {{ (int) round($d['messages'] / $trendMax * 100) }}px">
                            @if ($d['fallbacks'] > 0)
                                <div class="w-full rounded-t bg-amber-400" style="height: {{ (int) round($d['fallbacks'] / max(1, $d['messages']) * round($d['messages'] / $trendMax * 100)) }}px"></div>
                            @endif
                        </div>
                        <span class="mt-1 hidden text-[9px] text-gray-400 sm:block">{{ \Illuminate\Support\Str::before($d['day'], ' ') }}</span>
                    </div>
                @endforeach
            </div>
            <div class="mt-2 flex gap-4 text-xs text-gray-500">
                <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded bg-brand-500"></span> Pesan</span>
                <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded bg-amber-400"></span> Fallback</span>
            </div>
        </div>
    @endif

    {{-- Top keyword & produk (6 bulan) --}}
    <div class="mb-6 grid gap-4 lg:grid-cols-2">
        <div class="card p-4">
            <h3 class="mb-3 text-sm font-semibold text-gray-700">Kata Kunci Terpopuler</h3>
            @forelse ($topKeywords as $kw)
                <div class="flex items-center justify-between border-b border-gray-50 py-1.5 text-sm last:border-0">
                    <span class="text-gray-700">{{ $kw->term }}</span>
                    <span class="badge bg-brand-50 text-brand-700">{{ $kw->total }}×</span>
                </div>
            @empty
                <p class="py-4 text-center text-sm text-gray-400">Belum ada data.</p>
            @endforelse
        </div>
        <div class="card p-4">
            <h3 class="mb-3 text-sm font-semibold text-gray-700">Produk Paling Sering Ditanya</h3>
            @forelse ($topProducts as $p)
                <div class="flex items-center justify-between gap-2 border-b border-gray-50 py-1.5 text-sm last:border-0">
                    <span class="truncate text-gray-700">{{ $p->label ?? $p->term }}</span>
                    <span class="badge bg-gray-100 text-gray-600">{{ $p->total }}×</span>
                </div>
            @empty
                <p class="py-4 text-center text-sm text-gray-400">Belum ada data.</p>
            @endforelse
        </div>
    </div>

    {{-- Log transkrip --}}
    <div class="mb-3 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-700">Percakapan Terbaru</h3>
        <div class="flex gap-2 text-xs">
            <a href="{{ route('admin.assistant.index') }}" class="badge {{ request('filter') !== 'fallback' ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-600' }}">Semua</a>
            <a href="{{ route('admin.assistant.index', ['filter' => 'fallback']) }}" class="badge {{ request('filter') === 'fallback' ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-600' }}">Fallback</a>
        </div>
    </div>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Waktu</th>
                    <th class="px-4 py-3">Pertanyaan</th>
                    <th class="px-4 py-3">Jawaban</th>
                    <th class="px-4 py-3 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($conversations as $c)
                    <tr class="align-top hover:bg-gray-50">
                        <td class="whitespace-nowrap px-4 py-3 text-xs text-gray-400">
                            {{ $c->created_at?->format('d/m H:i') }}
                            @if ($c->session_id)
                                <span class="mt-1 block font-mono text-[10px] text-gray-300">{{ \Illuminate\Support\Str::limit($c->session_id, 8, '') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-800">{{ $c->message }}</td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ \Illuminate\Support\Str::limit($c->reply, 180) }}
                            @if (! empty($c->product_slugs))
                                <span class="mt-1 block text-[11px] text-brand-600">{{ count($c->product_slugs) }} produk direkomendasikan</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if ($c->answered)
                                <span class="badge bg-green-100 text-green-700">AI</span>
                            @else
                                <span class="badge bg-amber-100 text-amber-700">Fallback</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-gray-400">Belum ada percakapan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $conversations->links() }}</div>
@endsection
