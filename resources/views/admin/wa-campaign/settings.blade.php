@extends('layouts.admin')

@section('title', 'Pengaturan WA Campaign')

@section('content')
    <x-admin.page-header title="Pengaturan WA Campaign" subtitle="Batas internal pengiriman — bisa diubah, bukan jaminan bebas blokir WhatsApp" />
    @include('admin.wa-campaign._nav')

    <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
        <form method="POST" action="{{ route('admin.wa-campaign.settings.update') }}" class="space-y-6">
            @csrf @method('PUT')
            <div class="card p-5">
                <h2 class="mb-3 font-semibold text-gray-900">Jam & hari kirim</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div><label class="input-label">Mulai</label><input type="time" name="window_start" value="{{ old('window_start', $values['window_start']) }}" class="form-input">@error('window_start')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label class="input-label">Selesai</label><input type="time" name="window_end" value="{{ old('window_end', $values['window_end']) }}" class="form-input">@error('window_end')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label class="input-label">Zona waktu</label><input type="text" name="timezone" value="{{ old('timezone', $values['timezone']) }}" class="form-input">@error('timezone')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
                </div>
                @php $days = array_map('intval', explode(',', (string) $values['send_days'])); @endphp
                <div class="mt-3 flex flex-wrap gap-2 text-sm">
                    @foreach ([1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'] as $i => $d)
                        <label class="flex items-center gap-1 rounded-md border border-gray-200 px-2 py-1"><input type="checkbox" name="send_days[]" value="{{ $i }}" @checked(in_array($i, old('send_days', $days))) class="rounded"> {{ $d }}</label>
                    @endforeach
                </div>
            </div>

            <div class="card p-5">
                <h2 class="mb-3 font-semibold text-gray-900">Batas pengiriman</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label class="input-label">Maks. pesan per menit</label><input type="number" name="per_minute" min="1" max="30" value="{{ old('per_minute', $values['per_minute']) }}" class="form-input"><p class="mt-1 text-xs text-gray-400">4/menit = jeda ±15 detik antar pesan.</p></div>
                    <div><label class="input-label">Maks. pesan per hari (semua campaign)</label><input type="number" name="per_day" min="1" max="5000" value="{{ old('per_day', $values['per_day']) }}" class="form-input"><p class="mt-1 text-xs text-gray-400">Sesuaikan dengan kuota & umur nomor Wablas. Nomor baru: mulai kecil (≤ 50/hari).</p></div>
                    <div><label class="input-label">Jeda promo per kontak (hari)</label><input type="number" name="min_gap_days" min="0" max="90" value="{{ old('min_gap_days', $values['min_gap_days']) }}" class="form-input"><p class="mt-1 text-xs text-gray-400">Maks. 1 promo per kontak dalam N hari, lintas campaign. 0 = tanpa jeda (tidak disarankan).</p></div>
                    <div><label class="input-label">Jeda otomatis setelah N kegagalan beruntun</label><input type="number" name="failure_pause_after" min="0" max="50" value="{{ old('failure_pause_after', $values['failure_pause_after']) }}" class="form-input"><p class="mt-1 text-xs text-gray-400">0 = tidak pernah jeda otomatis.</p></div>
                    <div><label class="input-label">Maks. percobaan per pesan</label><input type="number" name="max_attempts" min="1" max="5" value="{{ old('max_attempts', $values['max_attempts']) }}" class="form-input"><p class="mt-1 text-xs text-gray-400">Hanya untuk error sementara (5xx/429). Timeout = "tidak pasti", tidak diulang otomatis.</p></div>
                    <div><label class="input-label">Nomor tes</label><input type="text" name="test_phone" value="{{ old('test_phone', $values['test_phone']) }}" placeholder="0812xxxxxxx" class="form-input"><p class="mt-1 text-xs text-gray-400">Tombol "Kirim tes" hanya mengirim ke nomor ini.</p></div>
                </div>
            </div>

            <div class="card p-5">
                <h2 class="mb-3 font-semibold text-gray-900">Pesan & jenis koneksi</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2"><label class="input-label">Footer berhenti promo (wajib memuat STOP)</label><input type="text" name="footer" value="{{ old('footer', $values['footer']) }}" class="form-input">@error('footer')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div class="sm:col-span-2">
                        <label class="input-label">Jenis koneksi akun Wablas</label>
                        <select name="connection_type" class="form-select">
                            <option value="qr" @selected(old('connection_type', $values['connection_type']) === 'qr')>WhatsApp biasa via scan QR (bukan Cloud API Meta)</option>
                            <option value="cloud" @selected(old('connection_type', $values['connection_type']) === 'cloud')>WhatsApp Cloud API resmi Meta (template harus disetujui Meta)</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Koneksi QR (device scan) <strong>bukan</strong> Cloud API resmi — tidak ada approval template, tetapi nomor bisa diblokir WhatsApp bila banyak laporan spam. Meta Verified / centang hijau juga <strong>tidak</strong> menjamin bebas blokir. Untuk Cloud API, isi pesan harus sama dengan template yang disetujui Meta dan memuat opsi berhenti.</p>
                    </div>
                </div>
            </div>
            <button class="btn-primary">Simpan pengaturan</button>
        </form>

        <div class="space-y-4">
            <div class="card p-4 text-sm">
                <h2 class="mb-2 font-semibold text-gray-900">Status Wablas</h2>
                <dl class="space-y-1 text-xs text-gray-600">
                    <div class="flex justify-between"><dt>Mode</dt><dd class="font-semibold {{ $mock ? 'text-amber-700' : 'text-green-700' }}">{{ $mock ? 'MOCK (tidak mengirim)' : 'LIVE' }}</dd></div>
                    <div class="flex justify-between"><dt>WABLAS_ENABLED</dt><dd>{{ $enabled ? 'ya' : 'tidak' }}</dd></div>
                    <div class="flex justify-between"><dt>Server</dt><dd>{{ $baseUrl }}</dd></div>
                    <div class="flex justify-between"><dt>Token</dt><dd>{{ $enabled ? 'terisi (di .env)' : 'kosong' }}</dd></div>
                    <div class="flex justify-between"><dt>Secret key</dt><dd>{{ $hasSecret ? 'terisi' : 'tidak dipakai' }}</dd></div>
                    <div class="flex justify-between"><dt>Token webhook</dt><dd class="{{ $hasWebhookToken ? '' : 'text-red-600' }}">{{ $hasWebhookToken ? 'terisi' : 'KOSONG — webhook ditolak di produksi' }}</dd></div>
                    <div class="flex justify-between"><dt>Device</dt><dd>{{ $device['connected'] === true ? 'terhubung' : ($device['connected'] === false ? 'TIDAK terhubung' : 'tidak diketahui') }}{{ $device['status'] ? ' ('.$device['status'].')' : '' }}</dd></div>
                    @if ($device['quota'] !== null)<div class="flex justify-between"><dt>Kuota</dt><dd>{{ number_format($device['quota']) }}</dd></div>@endif
                    @if ($device['error'])<div class="text-red-600">{{ $device['error'] }}</div>@endif
                </dl>
                <p class="mt-3 text-[11px] text-gray-400">Kredensial hanya dibaca dari <code>.env</code> server (WABLAS_BASE_URL, WABLAS_TOKEN, WABLAS_SECRET, WABLAS_WEBHOOK_TOKEN) dan tidak pernah ditampilkan. Panduan lengkap: <code>docs/WA-CAMPAIGN.md</code>.</p>
            </div>
            <div class="card p-4 text-xs text-gray-600">
                <h2 class="mb-1 font-semibold text-gray-900">Webhook Wablas</h2>
                <p>Atur di Wablas → Device → Webhook URL (pesan masuk) dan Tracking URL (status pesan) ke:</p>
                <code class="mt-1 block break-all rounded bg-gray-100 p-2">{{ $webhookUrl }}?token=…</code>
                <p class="mt-1">Ganti … dengan WABLAS_WEBHOOK_TOKEN. Balasan STOP, balasan pelanggan, dan status terkirim/sampai/dibaca diproses lewat URL ini.</p>
            </div>
        </div>
    </div>
@endsection
