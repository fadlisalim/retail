@extends('layouts.admin')

@section('title', 'Laporan Pendapatan')

@section('content')
    @php
        $maxRevenue = max(1, collect($months)->max('pendapatan'));
        $fmtPct = fn ($v) => number_format($v, 1, ',', '.').'%';
        $channelLabels = \App\Models\Order::CHANNELS;
    @endphp

    <x-admin.page-header title="Laporan Pendapatan" subtitle="Rekap bulanan pesanan yang diverifikasi lunas Keuangan — dibukukan pada bulan verifikasi">
        <x-slot:actions>
            <form method="GET" class="flex items-center gap-2">
                <label class="text-sm text-gray-500" for="tahun">Tahun</label>
                <select name="tahun" id="tahun" class="form-select" onchange="this.form.submit()">
                    @foreach ($years as $y)
                        <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
                    @endforeach
                </select>
            </form>
            <a href="{{ route('admin.reports.monthly', ['tahun' => $year, 'export' => 'csv']) }}" class="btn-outline">Unduh CSV</a>
        </x-slot:actions>
    </x-admin.page-header>

    {{-- Ringkasan tahun --}}
    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5">
        <x-admin.stat-card label="Pendapatan {{ $year }}" :value="rupiah($total['pendapatan'])" color="green" :sub="$total['pesanan'].' pesanan lunas'" />
        <x-admin.stat-card label="Penjualan Produk" :value="rupiah($total['penjualan'])" color="brand" sub="Nilai barang setelah diskon" />
        <x-admin.stat-card label="Laba Kotor (estimasi)" :value="rupiah($total['laba_kotor'])" color="blue" :sub="$total['item_tanpa_modal'] > 0 ? 'HPP belum lengkap — '.$total['item_tanpa_modal'].' item tanpa modal' : 'Margin '.$fmtPct($total['margin']).' dari modal saat ini'" />
        <x-admin.stat-card label="Ongkir & Biaya" :value="rupiah($total['ongkir_biaya'])" color="amber" :sub="'PPN '.rupiah($total['ppn'])" />
        <x-admin.stat-card label="Komisi Afiliasi" :value="rupiah($total['komisi'])" color="red" :sub="'Rata-rata '.rupiah($total['rata_rata']).' / pesanan'" />
    </div>

    @if ($total['item_tanpa_modal'] > 0)
        <p class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">
            {{ $total['item_tanpa_modal'] }} baris item pesanan produknya belum punya harga modal, jadi HPP &amp; laba kotor masih kurang dari seharusnya. Lengkapi modal di <a href="{{ route('admin.prices.index', ['tampil' => 'tanpa-modal']) }}" class="underline">Edit Cepat Produk</a>.
        </p>
    @endif

    {{-- Grafik per bulan --}}
    <div class="card mt-6 p-5">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="font-semibold text-gray-900">Pendapatan per Bulan {{ $year }}</h2>
            <span class="text-xs text-gray-500">Klik bulan untuk rincian</span>
        </div>
        <div class="flex h-48 items-end gap-2">
            @foreach ($months as $m => $row)
                @php $pct = (int) round(($row['pendapatan'] / $maxRevenue) * 100); @endphp
                <a href="{{ route('admin.reports.monthly', ['tahun' => $year, 'bulan' => $m]) }}#rincian" class="group flex h-full flex-1 flex-col items-center justify-end" title="{{ $row['label'] }}: {{ rupiah($row['pendapatan']) }} ({{ $row['pesanan'] }} pesanan)">
                    <div class="relative w-full rounded-t {{ $m === $month ? 'bg-accent-500' : 'bg-brand-500/80' }} transition group-hover:bg-brand-600" style="height: {{ max($pct, 2) }}%">
                        <span class="pointer-events-none absolute -top-6 left-1/2 hidden -translate-x-1/2 whitespace-nowrap rounded bg-gray-900 px-1.5 py-0.5 text-[10px] text-white group-hover:block">{{ rupiah($row['pendapatan']) }}</span>
                    </div>
                    <span class="mt-1 text-[10px] text-gray-400">{{ \Illuminate\Support\Str::limit(explode(' ', $row['label'])[0], 3, '') }}</span>
                </a>
            @endforeach
        </div>
    </div>

    {{-- Tabel rekap --}}
    <div class="card mt-6 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <th class="px-4 py-3">Bulan</th>
                    <th class="px-4 py-3 text-center">Pesanan</th>
                    <th class="px-4 py-3 text-right">Pendapatan</th>
                    <th class="px-4 py-3 text-right">Penjualan Produk</th>
                    <th class="px-4 py-3 text-right">Ongkir &amp; Biaya</th>
                    <th class="px-4 py-3 text-right">PPN</th>
                    <th class="px-4 py-3 text-right">HPP (est.)</th>
                    <th class="px-4 py-3 text-right">Laba Kotor</th>
                    <th class="px-4 py-3 text-right">Komisi</th>
                    <th class="px-4 py-3 text-center" title="Pesanan dibatalkan / belum bayar pada bulan itu">Batal / Belum Bayar</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($months as $m => $row)
                    <tr class="border-t border-gray-100 {{ $m === $month ? 'bg-brand-50' : '' }} {{ $row['pesanan'] === 0 ? 'text-gray-400' : '' }}">
                        <td class="px-4 py-2 font-medium">
                            <a href="{{ route('admin.reports.monthly', ['tahun' => $year, 'bulan' => $m]) }}#rincian" class="hover:underline">{{ $row['label'] }}</a>
                        </td>
                        <td class="px-4 py-2 text-center">{{ $row['pesanan'] }}</td>
                        <td class="px-4 py-2 text-right font-semibold {{ $row['pesanan'] ? 'text-gray-900' : '' }}">{{ rupiah($row['pendapatan']) }}</td>
                        <td class="px-4 py-2 text-right">{{ rupiah($row['penjualan']) }}</td>
                        <td class="px-4 py-2 text-right">{{ rupiah($row['ongkir_biaya']) }}</td>
                        <td class="px-4 py-2 text-right">{{ rupiah($row['ppn']) }}</td>
                        <td class="px-4 py-2 text-right">
                            {{ rupiah($row['hpp']) }}
                            @if ($row['item_tanpa_modal'] > 0)
                                <span class="block text-xs text-amber-600" title="{{ $row['item_tanpa_modal'] }} baris item produknya belum punya harga modal — HPP belum lengkap">⚠ {{ $row['item_tanpa_modal'] }} item tanpa modal</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right {{ $row['laba_kotor'] < 0 ? 'text-red-600' : '' }}">
                            {{ rupiah($row['laba_kotor']) }}
                            @if ($row['penjualan'] > 0 && $row['item_tanpa_modal'] === 0)<span class="ml-1 text-xs text-gray-400">{{ $fmtPct($row['margin']) }}</span>@endif
                        </td>
                        <td class="px-4 py-2 text-right">{{ rupiah($row['komisi']) }}</td>
                        <td class="px-4 py-2 text-center text-xs">{{ $row['dibatalkan'] }} / {{ $row['belum_bayar'] }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-gray-200 bg-gray-50 font-bold text-gray-900">
                    <td class="px-4 py-3">Total {{ $year }}</td>
                    <td class="px-4 py-3 text-center">{{ $total['pesanan'] }}</td>
                    <td class="px-4 py-3 text-right text-brand-700">{{ rupiah($total['pendapatan']) }}</td>
                    <td class="px-4 py-3 text-right">{{ rupiah($total['penjualan']) }}</td>
                    <td class="px-4 py-3 text-right">{{ rupiah($total['ongkir_biaya']) }}</td>
                    <td class="px-4 py-3 text-right">{{ rupiah($total['ppn']) }}</td>
                    <td class="px-4 py-3 text-right">{{ rupiah($total['hpp']) }}</td>
                    <td class="px-4 py-3 text-right">{{ rupiah($total['laba_kotor']) }} @if ($total['item_tanpa_modal'] === 0)<span class="text-xs font-normal text-gray-400">{{ $fmtPct($total['margin']) }}</span>@endif</td>
                    <td class="px-4 py-3 text-right">{{ rupiah($total['komisi']) }}</td>
                    <td class="px-4 py-3 text-center text-xs">{{ $total['dibatalkan'] }} / {{ $total['belum_bayar'] }}</td>
                </tr>
            </tfoot>
        </table>
        <p class="px-4 py-3 text-xs text-gray-400">
            Pendapatan = pesanan yang <strong>diverifikasi lunas oleh Keuangan</strong> (kuitansi terbit) atau dibayar otomatis lewat payment gateway, dibukukan pada tanggal verifikasi; total sudah termasuk ongkir, biaya &amp; PPN, setelah diskon. Penjualan Produk = nilai barang setelah diskon.
            HPP = qty × harga modal produk/varian <strong>saat ini</strong> (bukan modal saat transaksi), jadi laba kotor adalah estimasi. Komisi afiliasi belum dikurangkan dari laba kotor.
        </p>
    </div>

    {{-- Per channel --}}
    @if ($total['channel'])
        <div class="card mt-6 p-5">
            <h2 class="mb-3 font-semibold text-gray-900">Pendapatan per Kanal {{ $year }}</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (collect($total['channel'])->sortByDesc('pendapatan') as $ch => $c)
                    <div class="rounded-lg border border-gray-100 px-4 py-3">
                        <p class="text-xs uppercase tracking-wide text-gray-500">{{ $channelLabels[$ch] ?? ucfirst($ch) }}</p>
                        <p class="text-lg font-bold text-gray-900">{{ rupiah($c['pendapatan']) }}</p>
                        <p class="text-xs text-gray-400">{{ $c['pesanan'] }} pesanan · {{ $fmtPct($total['pendapatan'] > 0 ? $c['pendapatan'] / $total['pendapatan'] * 100 : 0) }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Rincian bulan --}}
    @if ($detail)
        @php $row = $months[$month]; @endphp
        <div id="rincian" class="mt-6 grid gap-6 lg:grid-cols-2">
            <div class="card p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-900">Produk Terlaris — {{ $row['label'] }}</h2>
                    <span class="text-xs text-gray-500">{{ $row['pesanan'] }} pesanan · {{ rupiah($row['pendapatan']) }}</span>
                </div>
                @if ($detail['products'])
                    <table class="w-full text-sm">
                        <thead><tr class="text-left text-xs uppercase text-gray-500"><th class="py-2">Produk</th><th class="py-2 text-center">Qty</th><th class="py-2 text-right">Penjualan</th></tr></thead>
                        <tbody>
                            @foreach (array_slice($detail['products'], 0, 15) as $p)
                                <tr class="border-t border-gray-100">
                                    <td class="py-2"><span class="font-medium text-gray-800">{{ $p['name'] }}</span><span class="ml-1 text-xs text-gray-400">{{ $p['sku'] }}</span></td>
                                    <td class="py-2 text-center">{{ $p['qty'] }}</td>
                                    <td class="py-2 text-right">{{ rupiah($p['penjualan']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-sm text-gray-400">Belum ada pesanan lunas di bulan ini.</p>
                @endif
            </div>

            <div class="card p-5">
                <h2 class="mb-3 font-semibold text-gray-900">Pesanan Lunas — {{ $row['label'] }}</h2>
                @if ($detail['orders']->isNotEmpty())
                    <div class="max-h-[28rem] overflow-y-auto">
                        <table class="w-full text-sm">
                            <thead><tr class="text-left text-xs uppercase text-gray-500"><th class="py-2">Tanggal</th><th class="py-2">Pesanan</th><th class="py-2">Kanal</th><th class="py-2 text-right">Total</th></tr></thead>
                            <tbody>
                                @foreach ($detail['orders'] as $order)
                                    <tr class="border-t border-gray-100">
                                        <td class="py-2 text-gray-500">{{ $report->bookingDate($order)->format('d/m') }}</td>
                                        <td class="py-2">
                                            <a href="{{ route('admin.orders.show', $order) }}" class="font-medium text-brand-700 hover:underline">{{ $order->order_number }}</a>
                                            <span class="block text-xs text-gray-400">{{ $order->customer_name }}</span>
                                        </td>
                                        <td class="py-2 text-xs text-gray-500">{{ $order->channelLabel() }}</td>
                                        <td class="py-2 text-right font-medium">{{ rupiah($order->grand_total) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-gray-400">Belum ada pesanan lunas di bulan ini.</p>
                @endif
            </div>
        </div>
    @endif
@endsection
