@extends('layouts.admin')

@section('title', 'Campaign: '.$campaign->name)

@section('content')
    @php
        $color = ['draft' => 'bg-gray-100 text-gray-600', 'scheduled' => 'bg-blue-100 text-blue-700', 'running' => 'bg-green-100 text-green-700', 'paused' => 'bg-amber-100 text-amber-700', 'completed' => 'bg-brand-100 text-brand-700', 'cancelled' => 'bg-red-100 text-red-700'][$campaign->status] ?? 'bg-gray-100';
        $msgColor = ['queued' => 'bg-gray-100 text-gray-600', 'sending' => 'bg-blue-50 text-blue-700', 'accepted' => 'bg-blue-100 text-blue-700', 'sent' => 'bg-green-50 text-green-700', 'delivered' => 'bg-green-100 text-green-700', 'read' => 'bg-brand-100 text-brand-700', 'failed' => 'bg-red-100 text-red-700', 'uncertain' => 'bg-amber-100 text-amber-800', 'skipped' => 'bg-gray-100 text-gray-500', 'cancelled' => 'bg-gray-200 text-gray-600'];
    @endphp

    <x-admin.page-header :title="$campaign->name" :subtitle="'Dibuat '.$campaign->created_at->format('d/m/Y H:i').($campaign->creator ? ' oleh '.$campaign->creator->name : '')">
        <x-slot:actions>
            <span class="badge {{ $color }}">{{ $campaign->statusLabel() }}</span>
            @if ($campaign->canEdit())
                <a href="{{ route('admin.wa-campaign.edit', $campaign) }}" class="btn-outline">Edit</a>
            @endif
            @if ($campaign->status === 'draft')
                <form method="POST" action="{{ route('admin.wa-campaign.start', $campaign) }}" class="flex items-center gap-2" onsubmit="return confirm('Mulai campaign ke {{ $campaign->audience_count }} penerima?')">@csrf
                    <input type="datetime-local" name="scheduled_at" class="form-input text-xs" title="Kosongkan = mulai sekarang">
                    <button class="btn-primary">Mulai / Jadwalkan</button>
                </form>
            @endif
            @if (in_array($campaign->status, ['running', 'scheduled']))
                <form method="POST" action="{{ route('admin.wa-campaign.pause', $campaign) }}">@csrf<button class="btn-outline">Jeda</button></form>
            @endif
            @if ($campaign->status === 'paused')
                <form method="POST" action="{{ route('admin.wa-campaign.resume', $campaign) }}">@csrf<button class="btn-primary" @disabled($emergency)>Lanjutkan</button></form>
            @endif
            @if ($campaign->isActive())
                <form method="POST" action="{{ route('admin.wa-campaign.cancel', $campaign) }}" onsubmit="return confirm('Batalkan campaign? Semua antrean dibatalkan.')">@csrf<button class="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">Batalkan</button></form>
            @endif
            @if (in_array($campaign->status, ['draft', 'cancelled', 'completed']))
                <form method="POST" action="{{ route('admin.wa-campaign.destroy', $campaign) }}" onsubmit="return confirm('Hapus campaign beserta riwayatnya?')">@csrf @method('DELETE')<button class="text-sm text-red-600 hover:underline">Hapus</button></form>
            @endif
        </x-slot:actions>
    </x-admin.page-header>
    @include('admin.wa-campaign._nav')

    @if ($emergency)
        <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800"><strong>EMERGENCY STOP aktif</strong> — tidak ada pengiriman.</div>
    @endif
    @if ($campaign->pause_reason)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">Dijeda: {{ $campaign->pause_reason }}</div>
    @endif
    @if ($mock)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">MODE MOCK: status "diterima gateway" di bawah hanya simulasi — tidak ada pesan nyata.</div>
    @endif

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-8">
        <x-admin.stat-card label="Antre" :value="$stats['queued']" color="gray" />
        <x-admin.stat-card label="Diterima gateway" :value="$stats['accepted']" color="blue" sub="bukan = sampai" />
        <x-admin.stat-card label="Terkirim" :value="$stats['sent']" color="green" sub="laporan Wablas" />
        <x-admin.stat-card label="Sampai" :value="$stats['delivered']" color="green" sub="delivered" />
        <x-admin.stat-card label="Dibaca" :value="$stats['read']" color="brand" />
        <x-admin.stat-card label="Gagal" :value="$stats['failed']" color="red" :sub="$stats['uncertain'].' tidak pasti'" />
        <x-admin.stat-card label="Balasan" :value="$stats['replied']" color="brand" sub="dalam 7 hari" />
        <x-admin.stat-card label="STOP" :value="$stats['opted_out']" color="amber" :sub="$stats['skipped'].' dilewati · '.$stats['cancelled'].' batal'" />
    </div>
    <p class="mt-2 text-[11px] text-gray-400">"Terkirim/Sampai/Dibaca" hanya terisi bila Wablas mengirim webhook status (tracking) ke situs ini. Tanpa itu, status berhenti di "Diterima gateway".</p>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_340px]">
        <div class="space-y-6">
            <div class="card overflow-x-auto">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-4 py-3">
                    <h2 class="font-semibold text-gray-900">Penerima ({{ $messages->total() }})</h2>
                    <div class="flex flex-wrap gap-1 text-xs">
                        <a href="{{ route('admin.wa-campaign.show', $campaign) }}" class="rounded px-2 py-1 {{ ! $filter ? 'bg-brand-600 text-white' : 'bg-gray-100' }}">Semua</a>
                        @foreach (\App\Models\WaCampaignMessage::STATUS_LABELS as $k => $label)
                            <a href="{{ route('admin.wa-campaign.show', [$campaign, 'status' => $k]) }}" class="rounded px-2 py-1 {{ $filter === $k ? 'bg-brand-600 text-white' : 'bg-gray-100' }}">{{ $label }}</a>
                        @endforeach
                    </div>
                </div>
                <table class="w-full text-sm">
                    <thead><tr class="bg-gray-50 text-left text-xs uppercase text-gray-500"><th class="px-4 py-2">Kontak</th><th class="px-4 py-2">Status</th><th class="px-4 py-2">Waktu</th><th class="px-4 py-2">Keterangan</th><th class="px-4 py-2"></th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($messages as $m)
                            <tr>
                                <td class="px-4 py-2"><p class="font-medium text-gray-800">{{ $m->contact?->name ?: '—' }}</p><p class="text-xs text-gray-400">{{ $m->phone }}</p></td>
                                <td class="px-4 py-2"><span class="badge {{ $msgColor[$m->status] ?? '' }}">{{ $m->statusLabel() }}</span>@if ($m->reply_count)<span class="ml-1 text-xs text-brand-700" title="Balasan">💬 {{ $m->reply_count }}</span>@endif</td>
                                <td class="px-4 py-2 text-xs text-gray-500">{{ ($m->read_at ?? $m->delivered_at ?? $m->sent_at ?? $m->failed_at)?->format('d/m H:i') ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs text-gray-500">{{ $m->error }}@if ($m->attempts > 1) <span class="text-gray-400">(percobaan {{ $m->attempts }})</span>@endif</td>
                                <td class="px-4 py-2 text-right">
                                    @if (in_array($m->status, ['uncertain', 'failed', 'skipped']) && $campaign->status !== 'cancelled')
                                        <form method="POST" action="{{ route('admin.wa-campaign.requeue', [$campaign, $m]) }}" onsubmit="return confirm('{{ $m->status === 'uncertain' ? 'Pesan ini MUNGKIN sudah terkirim (timeout). Sudah cek di Wablas? Antrekan ulang?' : 'Antrekan ulang pesan ini?' }}')">@csrf<button class="text-xs text-brand-700 hover:underline">Antre ulang</button></form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">{{ $campaign->status === 'draft' ? 'Penerima dibekukan saat campaign dimulai ('.$campaign->audience_count.' kontak sesuai filter saat ini).' : 'Tidak ada.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-3">{{ $messages->links() }}</div>
            </div>

            <div class="card p-5">
                <h2 class="mb-2 font-semibold text-gray-900">Log</h2>
                <ul class="space-y-1 text-xs text-gray-600">
                    @forelse ($logs as $log)
                        <li><span class="text-gray-400">{{ $log->created_at->format('d/m H:i') }}</span> <span class="{{ $log->level === 'error' ? 'text-red-700' : ($log->level === 'warning' ? 'text-amber-700' : '') }}">{{ $log->message }}</span></li>
                    @empty
                        <li class="text-gray-400">Belum ada aktivitas.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="space-y-4">
            <div class="card p-4">
                <h2 class="mb-2 font-semibold text-gray-900">Preview pesan</h2>
                <div class="rounded-xl bg-[#e5ddd5] p-3">
                    <div class="max-w-[300px] rounded-lg bg-white p-2 text-[13px] leading-snug text-gray-800 shadow">
                        @if ($imageUrl)<img src="{{ $imageUrl }}" class="mb-2 w-full rounded object-cover" alt="">@endif
                        <p class="whitespace-pre-wrap">{{ $preview }}</p>
                    </div>
                </div>
                @if ($link)<p class="mt-2 break-all text-[11px] text-gray-400">Link: {{ $link }}</p>@endif
            </div>
            <div class="card p-4 text-sm">
                <h2 class="mb-2 font-semibold text-gray-900">Ringkasan</h2>
                <dl class="space-y-1 text-xs text-gray-600">
                    <div class="flex justify-between"><dt>Penerima dibekukan</dt><dd>{{ $stats['total'] ?: $campaign->audience_count }}</dd></div>
                    <div class="flex justify-between"><dt>Jadwal</dt><dd>{{ $campaign->scheduled_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'segera' }}</dd></div>
                    <div class="flex justify-between"><dt>Mulai</dt><dd>{{ $campaign->started_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt>Selesai</dt><dd>{{ $campaign->finished_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt>Produk</dt><dd>{{ $campaign->product?->name ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt>Filter</dt><dd class="text-right">{{ $campaign->segment ? json_encode($campaign->segment, JSON_UNESCAPED_UNICODE) : 'semua kontak ber-izin' }}</dd></div>
                </dl>
                @if ($testPhone && $campaign->status === 'draft')
                    <form method="POST" action="{{ route('admin.wa-campaign.test') }}" class="mt-3">@csrf<input type="hidden" name="campaign_id" value="{{ $campaign->id }}"><button class="btn-outline w-full">Kirim tes ke {{ $testPhone }}</button></form>
                @endif
            </div>
        </div>
    </div>
@endsection
