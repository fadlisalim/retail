@extends('layouts.admin')

@section('title', 'WA Campaign')

@section('content')
    <x-admin.page-header title="WhatsApp Campaign" subtitle="Kirim promo ke pelanggan yang mengizinkan, bertahap & terukur via Wablas">
        <x-slot:actions>
            <form method="POST" action="{{ route('admin.wa-campaign.emergency') }}" onsubmit="return confirm('{{ $overview['emergency'] ? 'Matikan emergency stop?' : 'EMERGENCY STOP: hentikan SEMUA pengiriman dan jeda semua campaign aktif?' }}')">
                @csrf
                <input type="hidden" name="on" value="{{ $overview['emergency'] ? 0 : 1 }}">
                <button type="submit" class="{{ $overview['emergency'] ? 'btn-outline' : 'rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700' }}">{{ $overview['emergency'] ? 'Matikan Emergency Stop' : '⛔ Emergency Stop' }}</button>
            </form>
            <a href="{{ route('admin.wa-campaign.create') }}" class="btn-primary">Buat Campaign</a>
        </x-slot:actions>
    </x-admin.page-header>

    @include('admin.wa-campaign._nav')

    @if ($overview['emergency'])
        <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800"><strong>EMERGENCY STOP aktif.</strong> Tidak ada pesan promo yang dikirim sampai dimatikan.</div>
    @endif
    @if ($overview['mock'])
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800"><strong>MODE MOCK.</strong> Wablas belum aktif / <code>WABLAS_CAMPAIGN_MOCK=true</code> — pesan hanya dicatat, tidak ada yang benar-benar dikirim ke pelanggan. Cocok untuk uji alur.</div>
    @endif

    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
        <x-admin.stat-card label="Kontak ber-izin" :value="number_format($overview['eligible'])" color="green" :sub="number_format($overview['contacts']).' kontak total · '.number_format($overview['opted_out']).' STOP'" />
        <x-admin.stat-card label="Antrean" :value="number_format($overview['queued'])" color="brand" sub="pesan menunggu dikirim" />
        <x-admin.stat-card label="Terkirim hari ini" :value="number_format($overview['sent_today']).' / '.number_format($overview['per_day'])" color="blue" sub="kuota harian (Pengaturan)" />
        <x-admin.stat-card label="Jam kirim" :value="$overview['window']" :color="$overview['within_window'] ? 'green' : 'amber'" :sub="$overview['within_window'] ? 'Sedang dalam jam kirim' : 'Di luar jam kirim — antrean menunggu'" />
        <x-admin.stat-card label="Koneksi" :value="$overview['connection'] === 'cloud' ? 'Cloud API' : 'WhatsApp via QR'" color="gray" :sub="$overview['connection'] === 'cloud' ? 'Template harus disetujui Meta' : 'Bukan Cloud API Meta — risiko blokir nomor ada'" />
        <x-admin.stat-card label="Mode" :value="$overview['mock'] ? 'MOCK' : 'LIVE'" :color="$overview['mock'] ? 'amber' : 'green'" :sub="$overview['mock'] ? 'tidak mengirim nyata' : 'mengirim ke pelanggan'" />
    </div>

    <div class="card mt-6 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <th class="px-4 py-3">Campaign</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-center">Penerima</th>
                    <th class="px-4 py-3">Jadwal / Mulai</th>
                    <th class="px-4 py-3">Dibuat</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($campaigns as $c)
                    @php $color = ['draft' => 'bg-gray-100 text-gray-600', 'scheduled' => 'bg-blue-100 text-blue-700', 'running' => 'bg-green-100 text-green-700', 'paused' => 'bg-amber-100 text-amber-700', 'completed' => 'bg-brand-100 text-brand-700', 'cancelled' => 'bg-red-100 text-red-700'][$c->status] ?? 'bg-gray-100'; @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.wa-campaign.show', $c) }}" class="font-medium text-brand-700 hover:underline">{{ $c->name }}</a>
                            <p class="line-clamp-1 text-xs text-gray-400">{{ \Illuminate\Support\Str::limit($c->message, 90) }}</p>
                        </td>
                        <td class="px-4 py-3 text-center"><span class="badge {{ $color }}">{{ $c->statusLabel() }}</span>@if ($c->pause_reason)<p class="mt-1 text-[11px] text-amber-700">{{ \Illuminate\Support\Str::limit($c->pause_reason, 60) }}</p>@endif</td>
                        <td class="px-4 py-3 text-center">{{ $c->messages_count ?: $c->audience_count }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ ($c->started_at ?? $c->scheduled_at)?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ $c->created_at->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap"><a href="{{ route('admin.wa-campaign.show', $c) }}" class="text-brand-700 hover:underline">Detail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Belum ada campaign. Pastikan dulu ada kontak ber-izin di tab <a href="{{ route('admin.wa-campaign.contacts') }}" class="underline">Kontak &amp; Izin</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $campaigns->links() }}</div>

    @if ($logs->isNotEmpty())
        <div class="card mt-6 p-5">
            <h2 class="mb-2 font-semibold text-gray-900">Aktivitas terakhir</h2>
            <ul class="space-y-1 text-xs text-gray-600">
                @foreach ($logs as $log)
                    <li><span class="text-gray-400">{{ $log->created_at->format('d/m H:i') }}</span> <span class="{{ $log->level === 'error' ? 'text-red-700' : ($log->level === 'warning' ? 'text-amber-700' : '') }}">{{ $log->message }}</span></li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection
