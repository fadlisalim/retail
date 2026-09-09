@extends('layouts.storefront')

@section('title', 'Afiliasi Saya — '.config('rekasurya.company.brand_name'))
@section('noindex', 'noindex')

@section('content')
    <x-breadcrumbs :items="[['label' => 'Akun', 'url' => route('account.dashboard')], ['label' => 'Afiliasi']]" />

    <div class="grid gap-6 lg:grid-cols-[240px_1fr]">
        @include('partials.account-nav')

        <div class="min-w-0 space-y-6">
            <div class="flex items-center justify-between gap-3">
                <h1 class="text-xl font-bold text-gray-900 sm:text-2xl">Dashboard Afiliasi</h1>
                <x-status-pill :color="$affiliate->status->color()" :label="$affiliate->status->label()" />
            </div>

            @if ($affiliate->status->value === 'pending')
                <div class="card border-amber-200 bg-amber-50 p-5 text-sm text-amber-800">
                    Pendaftaran Anda sedang <strong>diverifikasi</strong>. Link referral aktif setelah disetujui admin.
                </div>
            @elseif ($affiliate->status->value === 'rejected')
                <div class="card border-red-200 bg-red-50 p-5 text-sm text-red-700">
                    <p class="font-semibold">Pendaftaran Anda ditolak.</p>
                    @if ($affiliate->note)
                        <p class="mt-1">Alasan: {{ $affiliate->note }}</p>
                    @endif
                    <a href="{{ route('account.affiliate.register') }}" class="btn-primary mt-3 inline-flex">Perbaiki Data &amp; Daftar Ulang</a>
                </div>
            @elseif ($affiliate->status->value === 'suspended')
                <div class="card border-red-200 bg-red-50 p-5 text-sm text-red-700">
                    Akun afiliasi Anda sedang ditangguhkan. Hubungi admin untuk mengaktifkan kembali.
                </div>
            @endif

            {{-- Referral link --}}
            @if ($affiliate->isActive())
                <div class="card p-5" x-data="{ copied: false, link: '{{ $affiliate->referralUrl() }}',
                    copy() { navigator.clipboard.writeText(this.link); this.copied = true; setTimeout(() => this.copied = false, 1500); } }">
                    <p class="text-sm font-semibold text-gray-900">Link Referral Anda</p>
                    <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                        <input type="text" readonly :value="link" class="form-input flex-1 bg-gray-50 text-sm">
                        <button type="button" @click="copy()" class="btn-primary whitespace-nowrap">
                            <span x-show="!copied">Salin Link</span>
                            <span x-show="copied" x-cloak>Tersalin ✓</span>
                        </button>
                    </div>
                    <p class="mt-2 text-xs text-gray-400">Kode: <span class="font-mono font-semibold text-gray-600">{{ $affiliate->code }}</span> • Berlaku 30 hari sejak diklik.</p>
                    <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1">
                        <a href="{{ route('account.affiliate.products') }}"
                           class="inline-flex items-center gap-1 text-sm font-semibold text-brand-700 hover:underline">
                            Lihat Produk &amp; Komisi — fee per produk + link siap salin →
                        </a>
                        <a href="{{ asset('panduan-afiliator.pdf').'?v='.@filemtime(public_path('panduan-afiliator.pdf')) }}" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-1 text-sm font-semibold text-brand-700 hover:underline">
                            📄 Unduh Panduan Afiliator (PDF)
                        </a>
                    </div>
                </div>
            @endif

            {{-- Stats --}}
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach ([
                    ['Klik', $stats['clicks'], false],
                    ['Pesanan', $stats['orders'], false],
                    ['Komisi Ditahan', $stats['pending'], true],
                    ['Komisi Disetujui', $stats['approved'], true],
                    ['Sudah Dibayar', $stats['paid'], true],
                    ['Saldo Tersedia', $stats['available'], true],
                ] as [$label, $value, $isMoney])
                    <div class="card p-4">
                        <p class="text-xs text-gray-500">{{ $label }}</p>
                        <p class="mt-1 text-lg font-bold text-gray-900">{{ $isMoney ? rupiah($value) : number_format($value, 0, ',', '.') }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Withdraw --}}
            @if ($affiliate->isActive())
                <div class="card p-5">
                    <h2 class="font-semibold text-gray-900">Tarik Dana</h2>
                    <p class="mt-1 text-sm text-gray-500">Saldo tersedia: <strong>{{ rupiah($stats['available']) }}</strong> • Minimum penarikan {{ rupiah($minPayout) }}.</p>
                    <form action="{{ route('account.affiliate.payout') }}" method="POST" class="mt-3 flex flex-col gap-2 sm:flex-row">
                        @csrf
                        <input type="number" name="amount" min="{{ (int) $minPayout }}" step="1000" placeholder="Jumlah (Rp)" class="form-input sm:max-w-xs" required>
                        <button type="submit" class="btn-primary whitespace-nowrap" @if ($stats['available'] < $minPayout) disabled @endif>Ajukan Penarikan</button>
                    </form>
                    @error('amount')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    <p class="mt-2 text-xs text-gray-400">🔒 Demi keamanan, penarikan diproses setelah Anda mengklik <strong>link konfirmasi di email</strong> (berlaku 24 jam).</p>

                    {{-- Rekening tujuan + ubah rekening (perubahan diberitahukan via email) --}}
                    <div class="mt-3 border-t border-gray-100 pt-3" x-data="{ editBank: {{ $errors->has('bank_name') || $errors->has('bank_account_number') || $errors->has('bank_account_holder') ? 'true' : 'false' }} }">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-xs text-gray-400">Transfer ke: {{ $affiliate->bank_name }} {{ $affiliate->bank_account_number }} a.n. {{ $affiliate->bank_account_holder }}</p>
                            <button type="button" @click="editBank = !editBank"
                                    class="text-xs font-semibold text-brand-700 hover:underline">
                                <span x-show="!editBank">Ubah Rekening</span><span x-show="editBank" x-cloak>Batal</span>
                            </button>
                        </div>
                        <form x-show="editBank" x-cloak action="{{ route('account.affiliate.bank') }}" method="POST" class="mt-2 grid gap-2 sm:grid-cols-3">
                            @csrf
                            @method('PUT')
                            <div>
                                <input type="text" name="bank_name" value="{{ old('bank_name', $affiliate->bank_name) }}" placeholder="Nama bank (mis. BCA)" class="form-input w-full text-sm" required>
                                @error('bank_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <input type="text" name="bank_account_number" value="{{ old('bank_account_number', $affiliate->bank_account_number) }}" placeholder="Nomor rekening" class="form-input w-full text-sm" required>
                                @error('bank_account_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <input type="text" name="bank_account_holder" value="{{ old('bank_account_holder', $affiliate->bank_account_holder) }}" placeholder="Atas nama" class="form-input w-full text-sm" required>
                                @error('bank_account_holder')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-3">
                                <button type="submit" class="btn-primary text-sm">Simpan Rekening</button>
                                <p class="mt-1 text-xs text-gray-400">Rekening wajib atas nama sendiri. Setiap perubahan diberitahukan ke email Anda.</p>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            {{-- Commissions --}}
            <div class="card overflow-hidden">
                <div class="border-b border-gray-100 px-5 py-3"><h2 class="font-semibold text-gray-900">Riwayat Komisi</h2></div>
                @if ($commissions->isEmpty())
                    <p class="px-5 py-8 text-center text-sm text-gray-400">Belum ada komisi. Mulai bagikan link referral Anda!</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                                <tr>
                                    <th class="px-5 py-2">Tanggal</th>
                                    <th class="px-5 py-2">Pesanan</th>
                                    <th class="px-5 py-2">Produk</th>
                                    <th class="px-5 py-2 text-right">Rate</th>
                                    <th class="px-5 py-2 text-right">Komisi</th>
                                    <th class="px-5 py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($commissions as $c)
                                    <tr>
                                        <td class="whitespace-nowrap px-5 py-3 text-gray-500">{{ $c->created_at->format('d M Y') }}</td>
                                        <td class="px-5 py-3 font-medium text-gray-700">{{ $c->order?->order_number ?? '—' }}</td>
                                        <td class="px-5 py-3 text-gray-600">{{ $c->product?->name ?? '—' }}</td>
                                        <td class="px-5 py-3 text-right text-gray-500">{{ rtrim(rtrim(number_format($c->rate, 2), '0'), '.') }}%</td>
                                        <td class="px-5 py-3 text-right font-semibold text-gray-800">{{ rupiah($c->amount) }}</td>
                                        <td class="px-5 py-3"><x-status-pill :color="$c->status->color()" :label="$c->status->label()" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="px-5 py-3">{{ $commissions->links() }}</div>
                @endif
            </div>

            {{-- Payout history --}}
            @if ($payouts->isNotEmpty())
                <div class="card overflow-hidden">
                    <div class="border-b border-gray-100 px-5 py-3"><h2 class="font-semibold text-gray-900">Riwayat Penarikan</h2></div>
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
    </div>
@endsection
