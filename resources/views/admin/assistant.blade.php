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
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-sm font-semibold text-gray-700">Percakapan</h3>
        <div class="flex gap-2 text-xs">
            <a href="{{ route('admin.assistant.index') }}" class="badge {{ $mode === 'sessions' ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-600' }}">Per Sesi</a>
            <a href="{{ route('admin.assistant.index', ['view' => 'flat']) }}" class="badge {{ $mode === 'flat' && request('filter') !== 'fallback' ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-600' }}">Semua Pesan</a>
            <a href="{{ route('admin.assistant.index', ['view' => 'flat', 'filter' => 'fallback']) }}" class="badge {{ request('filter') === 'fallback' ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-600' }}">Fallback</a>
        </div>
    </div>

    @if ($mode === 'sessions')
        {{-- Grouped by session: each expands into the full conversation thread. --}}
        <div class="space-y-2">
            @forelse ($sessions as $s)
                @php($turns = $threads[$s->session_id] ?? collect())
                <div class="card overflow-hidden" x-data="{ open: false }">
                    <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left hover:bg-gray-50">
                        <div class="min-w-0">
                            <p class="flex items-center gap-2 text-sm font-medium text-gray-800">
                                <svg class="h-4 w-4 flex-none text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h8M8 14h5m-9 6 3.5-2.1A9 9 0 1 1 21 12a9 9 0 0 1-13 8.1L4 20Z"/></svg>
                                <span class="font-mono text-xs text-gray-500">{{ \Illuminate\Support\Str::limit($s->session_id, 12, '') }}</span>
                                <span class="badge bg-gray-100 text-gray-600">{{ $s->turns }} pesan</span>
                                @if ($s->fallbacks > 0)
                                    <span class="badge bg-amber-100 text-amber-700">{{ $s->fallbacks }} fallback</span>
                                @endif
                            </p>
                            <p class="mt-0.5 text-xs text-gray-400">
                                {{ \Illuminate\Support\Carbon::parse($s->started_at)->format('d/m/Y H:i') }}
                                – {{ \Illuminate\Support\Carbon::parse($s->last_at)->format('H:i') }}
                            </p>
                        </div>
                        <svg class="h-5 w-5 flex-none text-gray-400 transition" :class="open && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/></svg>
                    </button>

                    <div x-show="open" x-cloak class="space-y-3 border-t border-gray-100 bg-gray-50 px-4 py-4">
                        @foreach ($turns as $t)
                            {{-- Customer question --}}
                            <div class="flex justify-end">
                                <div class="max-w-[80%] rounded-2xl rounded-br-sm bg-brand-600 px-3 py-2 text-sm text-white">
                                    {{ $t->message }}
                                    <div class="mt-0.5 text-[10px] text-white/70">{{ $t->created_at?->format('H:i') }}</div>
                                </div>
                            </div>
                            {{-- Assistant reply --}}
                            <div class="flex justify-start">
                                <div class="max-w-[80%] space-y-1">
                                    <div class="whitespace-pre-line rounded-2xl rounded-bl-sm bg-white px-3 py-2 text-sm text-gray-700 shadow-sm">{{ $t->reply }}</div>
                                    <div class="flex items-center gap-2 text-[11px]">
                                        @if ($t->answered)
                                            <span class="badge bg-green-100 text-green-700">AI</span>
                                        @else
                                            <span class="badge bg-amber-100 text-amber-700">Fallback</span>
                                        @endif
                                        @if (! empty($t->product_slugs))
                                            <span class="text-brand-600">{{ count($t->product_slugs) }} produk direkomendasikan</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="card px-4 py-10 text-center text-gray-400">Belum ada percakapan.</div>
            @endforelse
        </div>
        <div class="mt-4">{{ $sessions->links() }}</div>
    @else
        {{-- Flat message list --}}
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
    @endif
@endsection
