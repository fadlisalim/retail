@extends('layouts.admin')

@section('title', 'Statistik Trafik')

@section('content')
    <x-admin.page-header title="Statistik Trafik"
                         subtitle="Dari mana pengunjung website datang — iklan Meta, Instagram, Google, WhatsApp, atau langsung" />

    {{-- Range switcher --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
        @foreach ([7 => '7 hari', 30 => '30 hari', 90 => '90 hari'] as $value => $label)
            <a href="{{ route('admin.traffic.index', ['hari' => $value]) }}"
               class="rounded-full px-3 py-1.5 text-sm font-medium transition {{ $days === $value ? 'bg-brand-600 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">{{ $label }}</a>
        @endforeach
        <span class="ml-auto text-xs text-gray-400">Log disimpan {{ $retentionDays }} hari terakhir, selebihnya dihapus otomatis.</span>
    </div>

    {{-- Summary --}}
    <div class="mb-5 grid gap-3 sm:grid-cols-3">
        <div class="card p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Kunjungan (sesi)</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($sessions, 0, ',', '.') }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Halaman dibuka</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($pageViews, 0, ',', '.') }}</p>
            <p class="text-xs text-gray-400">{{ $sessions > 0 ? number_format($pageViews / $sessions, 1, ',', '.') : '0' }} halaman/sesi</p>
        </div>
        <div class="card p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Dari HP</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ $mobileShare }}%</p>
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        {{-- Sources --}}
        <div class="card p-5">
            <h3 class="mb-3 text-sm font-semibold text-gray-700">Sumber Pengunjung</h3>
            @forelse ($bySource as $row)
                @php($share = $sessions > 0 ? round($row->sessions / $sessions * 100) : 0)
                <div class="mb-3">
                    <div class="mb-1 flex items-baseline justify-between gap-2 text-sm">
                        <span class="font-medium text-gray-700">{{ \App\Services\TrafficAttribution::label($row->source) }}</span>
                        <span class="text-gray-500">{{ number_format($row->sessions, 0, ',', '.') }} <span class="text-xs text-gray-400">({{ $share }}%)</span></span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full rounded-full {{ \App\Services\TrafficAttribution::color($row->source) }}" style="width: {{ max($share, 1) }}%"></div>
                    </div>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-gray-400">Belum ada kunjungan tercatat pada rentang ini.</p>
            @endforelse
        </div>

        {{-- Daily trend --}}
        <div class="card p-5">
            <h3 class="mb-3 text-sm font-semibold text-gray-700">Tren Harian</h3>
            @php($max = max(1, $trend->max('sessions')))
            <div class="flex h-40 items-end gap-0.5">
                @foreach ($trend as $point)
                    <div class="group relative flex-1" title="{{ $point['day'] }}: {{ $point['sessions'] }} sesi">
                        <div class="rounded-t bg-brand-500/80 transition group-hover:bg-brand-600"
                             style="height: {{ max(2, round($point['sessions'] / $max * 150)) }}px"></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-2 flex justify-between text-[11px] text-gray-400">
                <span>{{ \Illuminate\Support\Carbon::parse($trend->first()['day'])->format('d/m') }}</span>
                <span>{{ \Illuminate\Support\Carbon::parse($trend->last()['day'])->format('d/m') }}</span>
            </div>
        </div>

        {{-- Campaigns (UTM) --}}
        <div class="card p-5">
            <h3 class="mb-1 text-sm font-semibold text-gray-700">Kampanye (UTM)</h3>
            <p class="mb-3 text-xs text-gray-400">Terisi bila link iklan memakai parameter utm_campaign.</p>
            @forelse ($byCampaign as $row)
                <div class="flex items-start justify-between gap-3 border-b border-gray-50 py-2 text-sm last:border-0">
                    <div class="min-w-0">
                        <p class="truncate font-medium text-gray-700">{{ $row->campaign }}</p>
                        <p class="truncate text-xs text-gray-400">
                            {{ \App\Services\TrafficAttribution::label($row->source) }}@if ($row->content) · {{ $row->content }}@endif
                        </p>
                    </div>
                    <span class="shrink-0 font-semibold text-gray-700">{{ number_format($row->sessions, 0, ',', '.') }}</span>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-gray-400">Belum ada kunjungan dengan UTM. Pastikan link iklan memakai <code>?utm_source=…&utm_campaign=…</code></p>
            @endforelse
        </div>

        {{-- Landing pages --}}
        <div class="card p-5">
            <h3 class="mb-3 text-sm font-semibold text-gray-700">Halaman Pendaratan Teratas</h3>
            @forelse ($byLanding as $row)
                <div class="flex items-center justify-between gap-3 border-b border-gray-50 py-2 text-sm last:border-0">
                    <a href="{{ url($row->landing_path) }}" target="_blank" rel="noopener" class="truncate text-brand-700 hover:underline">{{ $row->landing_path }}</a>
                    <span class="shrink-0 font-semibold text-gray-700">{{ number_format($row->sessions, 0, ',', '.') }}</span>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-gray-400">Belum ada data.</p>
            @endforelse
        </div>

        {{-- Referrers --}}
        <div class="card p-5 lg:col-span-2">
            <h3 class="mb-3 text-sm font-semibold text-gray-700">Situs Perujuk</h3>
            @forelse ($byReferrer as $row)
                <div class="flex items-center justify-between gap-3 border-b border-gray-50 py-2 text-sm last:border-0">
                    <span class="truncate text-gray-700">{{ $row->referrer_host }}</span>
                    <span class="shrink-0 font-semibold text-gray-700">{{ number_format($row->sessions, 0, ',', '.') }}</span>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-gray-400">Belum ada kunjungan dari situs lain.</p>
            @endforelse
        </div>
    </div>
@endsection
