@extends('layouts.admin')

@section('title', 'Kontak WA Campaign')

@section('content')
    <x-admin.page-header title="Kontak & Izin Promo" subtitle="Hanya kontak dengan izin promo (ada bukti) yang bisa dikirimi. STOP tidak pernah ditimpa oleh import/sinkron.">
        <x-slot:actions>
            <form method="POST" action="{{ route('admin.wa-campaign.contacts.sync') }}" onsubmit="return confirm('Tarik kontak dari customer, pesanan lunas, lead Kirana, dan WA chat? Izin promo TIDAK diubah.')">@csrf<button class="btn-outline">Sinkron dari data toko</button></form>
        </x-slot:actions>
    </x-admin.page-header>
    @include('admin.wa-campaign._nav')

    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        <x-admin.stat-card label="Semua kontak" :value="number_format($counts['all'])" color="gray" />
        <x-admin.stat-card label="Izin promo" :value="number_format($counts['in'])" color="green" sub="boleh dikirimi" />
        <x-admin.stat-card label="Belum ada izin" :value="number_format($counts['unknown'])" color="amber" sub="tidak dikirimi" />
        <x-admin.stat-card label="STOP" :value="number_format($counts['out'])" color="red" sub="daftar pengecualian" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_340px]">
        <div>
            <form method="GET" class="card mb-4 flex flex-wrap items-end gap-3 p-4">
                <div class="min-w-48 flex-1"><label class="input-label">Cari</label><input type="text" name="q" value="{{ request('q') }}" placeholder="Nama / nomor" class="form-input"></div>
                <div><label class="input-label">Izin</label><select name="consent" class="form-select"><option value="">Semua</option><option value="opted_in" @selected(request('consent') === 'opted_in')>Izin promo</option><option value="unknown" @selected(request('consent') === 'unknown')>Belum ada izin</option><option value="opted_out" @selected(request('consent') === 'opted_out')>STOP</option></select></div>
                <div><label class="input-label">Tag</label><select name="tag" class="form-select"><option value="">Semua</option>@foreach ($tags as $t)<option value="{{ $t }}" @selected(request('tag') === $t)>{{ $t }}</option>@endforeach</select></div>
                <div><label class="input-label">Sumber</label><select name="source" class="form-select"><option value="">Semua</option>@foreach ($sources as $s)<option value="{{ $s }}" @selected(request('source') === $s)>{{ $s }}</option>@endforeach</select></div>
                <button class="btn-primary">Filter</button>
            </form>

            <div class="card overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="bg-gray-50 text-left text-xs uppercase text-gray-500"><th class="px-4 py-2">Kontak</th><th class="px-4 py-2">Izin</th><th class="px-4 py-2">Tag / minat</th><th class="px-4 py-2 text-center">Pesanan</th><th class="px-4 py-2">Promo terakhir</th><th class="px-4 py-2"></th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($contacts as $c)
                            @php $cc = ['opted_in' => 'bg-green-100 text-green-700', 'opted_out' => 'bg-red-100 text-red-700', 'unknown' => 'bg-amber-100 text-amber-700'][$c->consent_status]; @endphp
                            <tr x-data="{ open: false }">
                                <td class="px-4 py-2"><p class="font-medium text-gray-800">{{ $c->name ?: '—' }}</p><p class="text-xs text-gray-400">{{ $c->phone }} · {{ $c->source }}</p></td>
                                <td class="px-4 py-2"><span class="badge {{ $cc }}">{{ $c->consentLabel() }}</span>@if ($c->consent_proof)<p class="mt-1 max-w-56 text-[11px] text-gray-400" title="{{ $c->consent_proof }}">{{ \Illuminate\Support\Str::limit($c->consent_proof, 60) }}</p>@endif</td>
                                <td class="px-4 py-2 text-xs text-gray-500">{{ implode(', ', $c->tags ?? []) ?: '—' }}@if ($c->interests)<p class="text-gray-400">{{ implode(', ', $c->interests) }}</p>@endif</td>
                                <td class="px-4 py-2 text-center text-xs">{{ $c->orders_count }}@if ($c->total_spent > 0)<p class="text-gray-400">{{ rupiah($c->total_spent) }}</p>@endif</td>
                                <td class="px-4 py-2 text-xs text-gray-500">{{ $c->last_promo_at?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-4 py-2 text-right"><button type="button" class="text-xs text-brand-700 hover:underline" @click="open = !open">Ubah</button></td>
                            </tr>
                            <tr x-show="open" x-cloak class="bg-gray-50">
                                <td colspan="6" class="px-4 py-3">
                                    <form method="POST" action="{{ route('admin.wa-campaign.contacts.update', $c) }}" class="grid gap-3 sm:grid-cols-4">
                                        @csrf @method('PUT')
                                        <div><label class="input-label">Nama</label><input type="text" name="name" value="{{ $c->name }}" class="form-input text-sm"></div>
                                        <div><label class="input-label">Tag (pisah koma)</label><input type="text" name="tags" value="{{ implode(', ', $c->tags ?? []) }}" class="form-input text-sm"></div>
                                        <div>
                                            <label class="input-label">Izin promo</label>
                                            <select name="consent_action" class="form-select text-sm">
                                                <option value="">— tidak diubah —</option>
                                                @if (! $c->isOptedIn() && ! $c->isOptedOut())<option value="opt_in">Catat izin (wajib bukti)</option>@endif
                                                @if ($c->isOptedOut())<option value="reopt_in">Izin lagi — customer minta sendiri (wajib bukti)</option>@endif
                                                @if (! $c->isOptedOut())<option value="opt_out">Keluarkan dari promo (STOP)</option>@endif
                                            </select>
                                        </div>
                                        <div><label class="input-label">Bukti izin</label><input type="text" name="proof" placeholder="mis. chat WA 03/10 minta info promo" class="form-input text-sm"></div>
                                        <div class="sm:col-span-3"><label class="input-label">Catatan</label><input type="text" name="notes" value="{{ $c->notes }}" class="form-input text-sm"></div>
                                        <div class="flex items-end"><button class="btn-primary w-full">Simpan</button></div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Belum ada kontak. Klik "Sinkron dari data toko" atau import CSV.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-3">{{ $contacts->links() }}</div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="card p-4">
                <h2 class="mb-2 font-semibold text-gray-900">Tambah kontak</h2>
                <form method="POST" action="{{ route('admin.wa-campaign.contacts.store') }}" class="space-y-2" x-data="{ consent: false }">
                    @csrf
                    <input type="text" name="phone" placeholder="0812xxxxxxx" required class="form-input text-sm">
                    <input type="text" name="name" placeholder="Nama" class="form-input text-sm">
                    <input type="text" name="tags" placeholder="Tag (pisah koma)" class="form-input text-sm">
                    <label class="flex items-center gap-2 text-xs text-gray-600"><input type="checkbox" name="consent" value="1" x-model="consent" class="rounded"> Sudah memberi izin promo</label>
                    <input type="text" name="proof" x-show="consent" x-cloak placeholder="Bukti izin (wajib): mis. form pameran 02/10" class="form-input text-sm">
                    <button class="btn-primary w-full">Simpan</button>
                </form>
            </div>
            <div class="card p-4">
                <h2 class="mb-2 font-semibold text-gray-900">Import CSV</h2>
                <form method="POST" action="{{ route('admin.wa-campaign.contacts.import') }}" enctype="multipart/form-data" class="space-y-2">
                    @csrf
                    <input type="file" name="file" accept=".csv,text/csv" required class="form-input text-sm">
                    <input type="text" name="proof" placeholder="Bukti izin default (bila kolom bukti kosong)" class="form-input text-sm">
                    <button class="btn-outline w-full">Import</button>
                </form>
                <p class="mt-2 text-[11px] text-gray-500">Kolom: <code>telepon, nama, tag, minat, izin, bukti</code> (urutan bebas, pemisah koma/titik koma). Nomor dinormalisasi ke 62…, duplikat digabung. Hanya baris <code>izin = ya</code> yang jadi penerima. Kontak yang sudah STOP tidak dihidupkan oleh import.</p>
            </div>
            <div class="card p-4 text-xs text-gray-500">
                <h2 class="mb-1 font-semibold text-gray-900">Dari mana izin datang?</h2>
                <ul class="list-disc space-y-1 pl-4">
                    <li>Centang "bersedia menerima promo via WhatsApp" saat checkout atau daftar akun (otomatis, bukti tersimpan).</li>
                    <li>Import CSV dengan kolom izin + bukti (form event, grup, dsb.).</li>
                    <li>Manual oleh admin dengan bukti (mis. pelanggan minta via chat).</li>
                </ul>
                <p class="mt-2">Customer lama yang disinkron dari data toko <strong>tidak otomatis</strong> punya izin.</p>
            </div>
        </div>
    </div>
@endsection
