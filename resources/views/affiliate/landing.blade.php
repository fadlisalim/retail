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

    {{-- Benefits --}}
    <section class="mt-10">
        <h2 class="mb-4 text-xl font-bold text-gray-900">Keuntungan Menjadi Afiliator</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['💰', 'Komisi hingga 10%', 'Dapatkan komisi 2,5%–10% dari harga produk setiap ada pembelian lewat link Anda.'],
                ['🆓', 'Gratis & Tanpa Modal', 'Bergabung tanpa biaya. Tidak perlu stok barang atau modal awal.'],
                ['📊', 'Dashboard Real-time', 'Pantau klik, pesanan, dan komisi Anda kapan saja lewat dashboard afiliasi.'],
                ['🏦', 'Pencairan ke Rekening', 'Komisi bisa dicairkan langsung ke rekening bank Anda setelah pesanan selesai.'],
                ['🔗', 'Link & Kode Referral', 'Punya link unik + kode referral yang mudah dibagikan ke mana saja.'],
                ['🤝', 'Cocok untuk Siapa Saja', 'Instalatir, konsultan, komunitas, hingga content creator — semua bisa ikut.'],
            ] as [$icon, $t, $d])
                <div class="card flex items-start gap-3 p-5">
                    <span class="text-2xl">{{ $icon }}</span>
                    <div>
                        <h3 class="font-semibold text-gray-900">{{ $t }}</h3>
                        <p class="mt-1 text-sm text-gray-500">{{ $d }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- How to register --}}
    <section class="mt-10">
        <h2 class="mb-4 text-xl font-bold text-gray-900">Cara Mendaftar</h2>
        <ol class="card space-y-3 p-6 text-sm text-gray-600">
            <li class="flex gap-3"><span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-600 text-xs font-bold text-white">1</span> <span><strong>Buat / masuk akun</strong> Energi.Click terlebih dahulu.</span></li>
            <li class="flex gap-3"><span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-600 text-xs font-bold text-white">2</span> <span>Buka menu <strong>Afiliator</strong>, klik <strong>Daftar Sekarang</strong>, lalu lengkapi data diri &amp; rekening bank.</span></li>
            <li class="flex gap-3"><span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-600 text-xs font-bold text-white">3</span> <span>Unggah <strong>NPWP, foto KTP, dan selfie</strong> untuk verifikasi identitas.</span></li>
            <li class="flex gap-3"><span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-600 text-xs font-bold text-white">4</span> <span>Tunggu <strong>verifikasi tim kami</strong>. Setelah disetujui, link referral Anda langsung aktif!</span></li>
        </ol>
    </section>

    {{-- Commission table: fee per produk, urut dari yang terbesar. --}}
    <section class="mt-10" id="tabel-komisi">
        @php
            $isActiveAffiliate = $affiliate?->isActive() ?? false;
            $fmtPct = fn ($r) => rtrim(rtrim(number_format((float) $r, 2, ',', '.'), '0'), ',').'%';
        @endphp
        <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 class="text-xl font-bold text-gray-900">Tabel Komisi per Produk</h2>
                <p class="text-sm text-gray-500">Diurutkan dari fee tertinggi.
                    @if ($isActiveAffiliate) Salin link produknya dan mulai bagikan.
                    @else Daftar jadi afiliator untuk mendapatkan link pribadi Anda. @endif
                </p>
            </div>
        </div>
        <div class="card overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <th class="px-4 py-3">Produk</th>
                        <th class="px-3 py-3 text-right">Harga</th>
                        <th class="px-3 py-3 text-center">Fee</th>
                        <th class="px-3 py-3 text-right">Komisi/Unit</th>
                        @if ($isActiveAffiliate)<th class="px-3 py-3 text-center">Link Anda</th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($products as $product)
                        @php
                            $rate = $product->affiliate_rate !== null ? (float) $product->affiliate_rate : $defaultRate;
                            $price = $product->effectivePrice();
                            $activeVariants = $product->variants;
                            $isVariable = $product->product_type === 'variable' && $activeVariants->isNotEmpty();
                        @endphp
                        <tr class="align-top hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="flex items-start gap-3">
                                    <img src="{{ $product->primaryImageUrl() }}" alt="{{ $product->name }}"
                                         class="h-12 w-12 flex-none rounded-lg object-cover">
                                    <div class="min-w-0">
                                        <a href="{{ route('products.show', $product->slug) }}" target="_blank"
                                           class="line-clamp-2 font-medium text-gray-800 hover:text-brand-700">{{ $product->name }}</a>
                                        <p class="text-xs text-gray-400">{{ $product->brand?->name ?? '—' }}</p>
                                        @if ($isVariable)
                                            <div class="mt-1 flex flex-wrap gap-1">
                                                @foreach ($activeVariants as $variant)
                                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] text-gray-600"
                                                          title="Komisi ≈ {{ rupiah(round((float) $variant->price * $rate / 100)) }}">
                                                        {{ $variant->name }} · {{ rupiah($variant->price) }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-right text-gray-700">{{ $isVariable ? 'mulai ' : '' }}{{ rupiah($price) }}</td>
                            <td class="px-3 py-3 text-center"><span class="badge bg-green-100 font-bold text-green-700">{{ $fmtPct($rate) }}</span></td>
                            <td class="whitespace-nowrap px-3 py-3 text-right font-semibold text-gray-800">≈ {{ rupiah(round($price * $rate / 100)) }}{{ $isVariable ? '+' : '' }}</td>
                            @if ($isActiveAffiliate)
                                <td class="px-3 py-3 text-center">
                                    <button type="button"
                                            x-data="{ copied: false }"
                                            @click="navigator.clipboard.writeText(@js($affiliate->productReferralUrl($product))); copied = true; setTimeout(() => copied = false, 1500)"
                                            class="rounded-md bg-brand-50 px-3 py-1.5 text-xs font-medium text-brand-700 transition hover:bg-brand-100">
                                        <span x-show="!copied">Salin Link</span>
                                        <span x-show="copied" x-cloak>Tersalin ✓</span>
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $products->links() }}</div>
        <p class="mt-2 text-xs text-gray-400">Perkiraan komisi = harga jual saat ini × fee. Komisi final mengikuti harga saat pesanan dan cair setelah pesanan selesai.</p>
    </section>

    {{-- Terms --}}
    <section class="mt-10">
        <h2 class="mb-4 text-xl font-bold text-gray-900">Ketentuan Komisi</h2>
        <ul class="card space-y-2 p-6 text-sm text-gray-600">
            <li>• Komisi berkisar <strong>2,5%–10%</strong> tergantung produk.</li>
            <li>• Komisi dihitung dari harga barang (di luar ongkir, packing, dan pajak).</li>
            <li>• Atribusi berbasis klik terakhir dengan masa berlaku 30 hari.</li>
            <li>• Komisi cair setelah pesanan berstatus <strong>Selesai</strong> (aman dari retur/pembatalan).</li>
            <li>• Pembelian melalui link sendiri tidak menghasilkan komisi.</li>
            <li>• Minimum penarikan dana <strong>{{ rupiah($minPayout) }}</strong>, dibayar via transfer bank.</li>
            <li>• Wajib memiliki <strong>NPWP</strong> serta melampirkan foto KTP & selfie untuk verifikasi identitas oleh admin.</li>
        </ul>
    </section>

    {{-- Final CTA --}}
    <section class="mt-10 flex flex-col items-center gap-4 rounded-2xl bg-gray-900 p-8 text-center text-white sm:p-10">
        <h2 class="text-2xl font-bold">Siap mulai menghasilkan komisi?</h2>
        <p class="max-w-xl text-sm text-gray-300">Bergabung gratis, bagikan link Anda, dan dapatkan komisi dari setiap penjualan energi surya.</p>
        <div class="flex flex-wrap justify-center gap-3">
            @auth
                @if ($affiliate)
                    <a href="{{ route('account.affiliate.dashboard') }}" class="btn-accent">Buka Dashboard Afiliasi</a>
                @else
                    <a href="{{ route('account.affiliate.register') }}" class="btn-accent">Daftar Jadi Afiliator</a>
                @endif
            @else
                <a href="{{ route('register') }}" class="btn-accent">Daftar Akun Dulu</a>
                <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-lg border border-white/70 bg-transparent px-5 py-2.5 font-semibold text-white transition hover:bg-white/10">Sudah punya akun? Masuk</a>
            @endif
        </div>
    </section>
@endsection
