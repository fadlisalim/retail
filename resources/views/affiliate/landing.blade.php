@extends('layouts.storefront')

@section('title', 'Program Afiliasi — '.config('rekasurya.company.brand_name'))

@section('content')
    <x-breadcrumbs :items="[['label' => 'Program Afiliasi']]" />

    {{-- Hero --}}
    <section class="overflow-hidden rounded-2xl bg-gradient-to-r from-brand-700 to-brand-500 p-8 text-white sm:p-12">
        <div class="max-w-2xl">
            <span class="text-xs font-semibold uppercase tracking-wide text-accent-300">Program Afiliasi</span>
            <h1 class="mt-2 text-3xl font-extrabold sm:text-4xl">Hasilkan komisi dengan merekomendasikan produk energi surya</h1>
            <p class="mt-3 text-brand-50">Bagikan link referral Anda, dan dapatkan komisi hingga <strong>10%</strong> setiap ada pembelian dari link tersebut. Cocok untuk instalatir, konsultan, komunitas, dan content creator.</p>
            <div class="mt-6 flex flex-wrap gap-3">
                @auth
                    @if ($affiliate)
                        <a href="{{ route('account.affiliate.dashboard') }}" class="btn-accent">Buka Dashboard Afiliasi</a>
                    @else
                        <a href="{{ route('account.affiliate.register') }}" class="btn-accent">Daftar Sekarang</a>
                    @endif
                @else
                    <a href="{{ route('register') }}" class="btn-accent">Daftar Akun Dulu</a>
                    <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-lg border border-white/70 bg-transparent px-5 py-2.5 font-semibold text-white transition hover:bg-white/10">Masuk</a>
                @endauth
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section class="mt-10">
        <h2 class="mb-4 text-xl font-bold text-gray-900">Cara Kerjanya</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ([
                ['1', 'Daftar & Verifikasi', 'Lengkapi data diri dan rekening. Tim kami memverifikasi sebelum akun aktif.'],
                ['2', 'Bagikan Link', 'Sebarkan link referral unik Anda ke calon pembeli lewat channel apa pun.'],
                ['3', 'Terima Komisi', 'Komisi masuk otomatis saat pesanan selesai, lalu bisa dicairkan ke rekening.'],
            ] as [$n, $t, $d])
                <div class="card p-5">
                    <span class="grid h-10 w-10 place-items-center rounded-full bg-brand-50 text-lg font-bold text-brand-600">{{ $n }}</span>
                    <h3 class="mt-3 font-semibold text-gray-900">{{ $t }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ $d }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Terms --}}
    <section class="mt-10">
        <h2 class="mb-4 text-xl font-bold text-gray-900">Ketentuan Komisi</h2>
        <ul class="card space-y-2 p-6 text-sm text-gray-600">
            <li>• Komisi berkisar <strong>2,5%–10%</strong> tergantung produk (default {{ (int) $defaultRate }}%).</li>
            <li>• Komisi dihitung dari harga barang (di luar ongkir, packing, dan pajak).</li>
            <li>• Atribusi berbasis klik terakhir dengan masa berlaku 30 hari.</li>
            <li>• Komisi cair setelah pesanan berstatus <strong>Selesai</strong> (aman dari retur/pembatalan).</li>
            <li>• Pembelian melalui link sendiri tidak menghasilkan komisi.</li>
            <li>• Minimum penarikan dana <strong>{{ rupiah($minPayout) }}</strong>, dibayar via transfer bank.</li>
        </ul>
    </section>
@endsection
