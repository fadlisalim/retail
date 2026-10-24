<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="no-referrer">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $proposal->title }} — Laporan audit {{ brand() }}</title>
    @include('partials.favicons')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $badge = [
        'pending' => 'bg-amber-100 text-amber-800',
        'approved' => 'bg-green-100 text-green-800',
        'rejected' => 'bg-gray-200 text-gray-700',
        'superseded' => 'bg-gray-200 text-gray-700',
    ][$proposal->status] ?? 'bg-gray-200 text-gray-700';
@endphp
<body class="min-h-full bg-gray-100 text-gray-800">
<div class="mx-auto max-w-3xl space-y-4 px-4 py-6">
    <div class="flex items-center gap-2">
        <x-logo class="h-8 w-8 shrink-0 text-brand-700" />
        <div class="min-w-0 leading-tight">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Laporan audit otomatis 🔒 · {{ $proposal->created_at->translatedFormat('d M Y H:i') }}</p>
            <h1 class="text-lg font-bold text-gray-900">{{ $proposal->title }}</h1>
        </div>
    </div>

    <x-flash />
    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    <div class="flex flex-wrap items-center gap-2 text-sm">
        <span class="badge {{ $badge }}">{{ $proposal->statusLabel() }}</span>
        @if ($proposal->decided_at)
            <span class="text-gray-500">{{ $proposal->decided_at->translatedFormat('d M Y H:i') }}</span>
        @endif
        <a href="{{ $proposal->pr_url }}" target="_blank" rel="noopener" class="ml-auto text-brand-700 hover:underline">Lihat perubahan kode (GitHub) ↗</a>
    </div>

    <div class="card p-5">
        <div class="prose prose-sm max-w-none prose-headings:text-gray-900 prose-a:text-brand-700">{!! $html !!}</div>
    </div>

    @if ($proposal->owner_note)
        <div class="card p-4 text-sm">
            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Saran bro</p>
            <p class="whitespace-pre-line">{{ $proposal->owner_note }}</p>
        </div>
    @endif

    @if ($proposal->isPending())
        <form method="POST" action="{{ route('proposals.decide', $proposal->token) }}" class="card space-y-4 p-5" x-data="{ action: '{{ old('action', 'execute') }}', otpMsg: '', otpOk: false, otpBusy: false,
                      async sendOtp() {
                          this.otpBusy = true;
                          const r = await fetch('{{ route('proposals.otp', $proposal->token) }}', { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } });
                          const j = await r.json().catch(() => ({}));
                          this.otpOk = r.ok;
                          this.otpMsg = j.message || (r.status === 429 ? 'Terlalu sering — tunggu 10 menit lalu coba lagi.' : r.status === 419 ? 'Sesi kedaluwarsa — muat ulang halaman lalu coba lagi.' : 'Gagal mengirim OTP (error ' + r.status + ').');
                          this.otpBusy = false;
                      } }">
            @csrf
            <div>
                <label for="note" class="input-label">Saran / catatan untuk agent <span class="font-normal text-gray-500">(opsional saat eksekusi, wajib saat revisi)</span></label>
                <textarea id="note" name="note" rows="4" class="form-textarea" placeholder="Mis.: tombol WA di halaman produk dibuat lebih besar; besok fokus ke kecepatan halaman katalog.">{{ old('note') }}</textarea>
            </div>

            <div class="grid gap-2 sm:grid-cols-2">
                <label class="flex cursor-pointer items-start gap-2 rounded-lg border p-3" :class="action === 'execute' ? 'border-green-500 bg-green-50' : 'border-gray-200'">
                    <input type="radio" name="action" value="execute" x-model="action" class="mt-1">
                    <span><strong>✅ Eksekusi</strong><br><span class="text-xs text-gray-500">Tayangkan di energi.click (±2 menit).</span></span>
                </label>
                <label class="flex cursor-pointer items-start gap-2 rounded-lg border p-3" :class="action === 'reject' ? 'border-amber-500 bg-amber-50' : 'border-gray-200'">
                    <input type="radio" name="action" value="reject" x-model="action" class="mt-1">
                    <span><strong>✏️ Revisi / tolak</strong><br><span class="text-xs text-gray-500">Tidak tayang; agent memperbaiki sesuai saran.</span></span>
                </label>
            </div>

            @unless ($ready)
                <div class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    ⚠️ Tombol Eksekusi/Revisi belum aktif: <strong>token GitHub belum dipasang</strong>. Pasang dulu di Admin → Pengaturan → Status deploy otomatis, lalu muat ulang halaman ini.
                </div>
            @endunless
            <div class="rounded-lg bg-gray-50 p-3 {{ $ready ? '' : 'pointer-events-none opacity-50' }}">
                <p class="text-sm">Konfirmasi dengan kode OTP yang dikirim ke WhatsApp <strong>{{ $phone }}</strong>.</p>
                <div class="mt-2 flex flex-wrap items-end gap-2">
                    <button type="button" class="btn-outline" @click="sendOtp()" :disabled="otpBusy" x-text="otpMsg ? 'Kirim ulang OTP' : 'Kirim OTP ke WA'">Kirim OTP ke WA</button>
                    <div>
                        <label for="otp" class="sr-only">Kode OTP</label>
                        <input id="otp" name="otp" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="\d{6}" required
                               class="form-input w-36 text-center font-mono text-lg tracking-widest" placeholder="••••••">
                    </div>
                    <button type="submit" class="btn-primary" x-text="action === 'execute' ? 'Eksekusi sekarang' : 'Kirim revisi'">Eksekusi sekarang</button>
                </div>
                <p x-show="otpMsg" x-text="otpMsg" class="mt-2 rounded-md px-3 py-2 text-sm font-medium" :class="otpOk ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-700'" role="status"></p>
            </div>
        </form>
    @endif

    <p class="text-center text-xs text-gray-400">Link ini khusus pemilik — jangan diteruskan. Keputusan selalu butuh OTP WhatsApp.</p>
</div>
</body>
</html>
