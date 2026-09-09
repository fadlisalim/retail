@extends('layouts.storefront')

@section('title', 'Konsultasi Gratis dengan Kirana — '.brand())
@section('meta_description', 'Bingung pilih panel surya, inverter, baterai, atau power station? Konsultasikan kebutuhan energi Anda dengan Kirana, konsultan energi cerdas '.brand().' — gratis, 24 jam.')
@section('hide_cs_widget', '1')

@php
    // Chip kebutuhan → pesan pembuka natural yang dikirim atas nama pelanggan.
    $starters = [
        'Backup listrik saat mati lampu' => 'Saya butuh backup listrik saat mati lampu',
        'PLTS rumah' => 'Saya mau pasang PLTS untuk rumah',
        'Portable power station' => 'Saya cari portable power station',
        'Panel surya' => 'Saya mau beli panel surya',
        'Inverter' => 'Saya butuh inverter',
        'Baterai' => 'Saya cari baterai lithium',
        'PJU tenaga surya' => 'Saya butuh lampu PJU tenaga surya',
        'Sistem off-grid' => 'Saya mau bikin sistem off-grid',
        'Kebutuhan usaha' => 'Saya butuh solusi listrik untuk usaha saya',
        'Kebutuhan proyek' => 'Saya ada kebutuhan untuk proyek',
    ];
    $welcome = 'Halo 👋 Aku Kirana dari '.brand().'. Aku bantu carikan solusi energi yang paling cocok dengan kebutuhan dan budget Kakak — dari backup mati lampu, PLTS rumah, sampai power station buat perjalanan. Saat ini lagi cari untuk kebutuhan rumah, usaha, perjalanan, atau proyek?';
@endphp

@section('content')
<div x-data="csChat({ endpoint: '{{ route('assistant.chat') }}', history: '{{ route('assistant.history') }}', contact: '{{ route('assistant.contact') }}', click: '{{ route('assistant.click') }}', welcomes: @js([$welcome]) })" class="mx-auto max-w-3xl">

    {{-- Hero --}}
    <section class="pb-6 pt-4 text-center sm:pt-8">
        <span class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-brand-600 text-2xl font-bold text-white shadow-lg">K</span>
        <p class="text-xs font-semibold uppercase tracking-widest text-brand-600">Kirana — Konsultan Energi Cerdas</p>
        <h1 class="mt-2 text-2xl font-extrabold text-gray-900 sm:text-3xl">Bingung Pilih Produk Energi yang Tepat?</h1>
        <p class="mx-auto mt-3 max-w-xl text-sm text-gray-600 sm:text-base">
            Konsultasikan dengan Kirana. Ia akan memahami kebutuhan Anda dulu — perangkat apa yang mau dinyalakan, berapa lama, berapa budget — baru merekomendasikan solusi yang paling sesuai. Gratis, langsung dijawab, 24 jam.
        </p>

        {{-- Contoh kebutuhan (klik = langsung mulai) --}}
        <div class="mx-auto mt-5 flex max-w-2xl flex-wrap justify-center gap-2">
            @foreach ($starters as $label => $message)
                <button type="button"
                        @click="sendChip(@js($message)); $refs.chatbox.scrollIntoView({ behavior: 'smooth' })"
                        class="rounded-full border border-brand-200 bg-white px-3.5 py-1.5 text-xs font-medium text-brand-700 shadow-sm transition hover:border-brand-400 hover:bg-brand-50">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <button type="button"
                @click="$refs.chatbox.scrollIntoView({ behavior: 'smooth' }); $refs.composer?.focus()"
                class="btn-primary mt-6 inline-flex px-6 py-3 text-base shadow-lg">
            Mulai Konsultasi dengan Kirana
        </button>
    </section>

    {{-- Full-page chat --}}
    <section x-ref="chatbox" class="card overflow-hidden">
        {{-- Header --}}
        <div class="flex items-center gap-3 border-b border-gray-100 bg-brand-600 px-4 py-3 text-white">
            <span class="flex h-10 w-10 flex-none items-center justify-center rounded-full bg-white/20 text-base font-bold">K</span>
            <div class="leading-tight">
                <p class="text-sm font-semibold">Kirana · Konsultan Energi {{ brand() }}</p>
                <p class="flex items-center gap-1.5 text-[11px] text-white/80">
                    <span class="inline-block h-2 w-2 rounded-full bg-green-400"></span> Online — biasanya balas dalam hitungan detik
                </p>
            </div>
        </div>

        {{-- Messages --}}
        <div x-ref="log" class="space-y-3 overflow-y-auto bg-gray-50 px-3 py-4 sm:px-5" style="height: min(58vh, 34rem)">
            <template x-for="(m, i) in messages" :key="i">
                <div :class="m.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                    <div class="max-w-[85%] space-y-2 sm:max-w-[75%]">
                        <div class="rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed [&_strong]:font-semibold"
                             :class="m.role === 'user' ? 'rounded-br-sm bg-brand-600 text-white' : 'rounded-bl-sm bg-white text-gray-800 shadow-sm'"
                             x-html="render(m.content)"></div>

                        {{-- Kartu produk yang direkomendasikan Kirana --}}
                        <template x-if="m.role === 'assistant' && m.products && m.products.length">
                            <div class="space-y-2">
                                <template x-for="p in m.products" :key="p.url">
                                    <a :href="p.url" @click="track('product', p.slug)"
                                       class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-2.5 shadow-sm transition hover:border-brand-300 hover:shadow">
                                        <img :src="p.image" :alt="p.name" class="h-14 w-14 flex-none rounded-lg object-cover" loading="lazy" />
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-gray-800" x-text="p.name"></p>
                                            <p class="text-sm font-bold text-brand-700">
                                                <span x-text="p.price"></span>
                                                <span x-show="!p.in_stock" class="ml-1 text-xs font-normal text-red-500">(stok habis)</span>
                                            </p>
                                        </div>
                                        <span class="flex-none rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700">Lihat Produk</span>
                                    </a>
                                </template>
                            </div>
                        </template>

                        {{-- Lanjut ke tim (WhatsApp) --}}
                        <template x-if="m.whatsapp">
                            <a :href="m.whatsapp" @click="track('whatsapp', null)" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-2 rounded-full bg-green-500 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-green-600">
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163a11.867 11.867 0 0 1-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 0 1 8.413 3.488 11.824 11.824 0 0 1 3.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 0 1-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 0 0 1.51 5.26l-.999 3.648 3.978-1.207z"/></svg>
                                Lanjut Konsultasi via WhatsApp
                            </a>
                        </template>

                        {{-- Form kontak sebelum nomor WA dibagikan (lead capture) --}}
                        <template x-if="m.leadForm">
                            <form @submit.prevent="submitLead(m)" class="space-y-2 rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
                                <p class="text-xs font-semibold text-gray-700">Isi data singkat dulu ya Kak, biar tim kami langsung siap bantu 👇</p>
                                <input x-model="leadName" type="text" maxlength="120" placeholder="Nama Kakak"
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500" />
                                <input x-model="leadPhone" type="tel" maxlength="32" placeholder="Nomor WhatsApp (08…)"
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500" />
                                <textarea x-model="leadNeed" rows="2" maxlength="500" placeholder="Kebutuhan Kakak (mis. paket PLTS rumah 2200 VA, tanya stok, dll.)"
                                          class="w-full resize-none rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"></textarea>
                                <p x-show="leadError" x-cloak class="text-[11px] text-red-500" x-text="leadError"></p>
                                <button type="submit" :disabled="leadSending"
                                        class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-green-500 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-green-600 disabled:cursor-not-allowed disabled:opacity-60">
                                    <span x-text="leadSending ? 'Menyimpan…' : 'Kirim & Lanjut ke WhatsApp'"></span>
                                </button>
                            </form>
                        </template>
                    </div>
                </div>
            </template>

            {{-- Typing indicator --}}
            <div x-show="loading" class="flex justify-start">
                <div class="rounded-2xl rounded-bl-sm bg-white px-3.5 py-2.5 shadow-sm">
                    <span class="flex gap-1">
                        <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400" style="animation-delay: 0ms"></span>
                        <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400" style="animation-delay: 150ms"></span>
                        <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400" style="animation-delay: 300ms"></span>
                    </span>
                </div>
            </div>
        </div>

        {{-- Quick reply chips (muncul selama percakapan masih di awal) --}}
        <div x-show="messages.length <= 1" x-cloak class="flex gap-1.5 overflow-x-auto border-t border-gray-100 bg-white px-3 pt-2">
            @foreach (array_slice($starters, 0, 4) as $label => $message)
                <button type="button" @click="sendChip(@js($message))"
                        class="flex-none rounded-full border border-brand-300 px-3 py-1.5 text-xs font-medium text-brand-700 transition hover:bg-brand-50">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Composer --}}
        <form @submit.prevent="send()" class="flex items-end gap-2 border-t border-gray-200 bg-white p-3">
            <textarea
                x-model="input"
                x-ref="composer"
                @keydown.enter.prevent="send()"
                rows="1"
                placeholder="Ceritakan kebutuhan Kakak… (mis. backup kulkas & lampu saat mati listrik)"
                class="max-h-28 min-h-[2.75rem] flex-1 resize-none rounded-xl border border-gray-300 px-3.5 py-2.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
            ></textarea>
            <button type="submit" :disabled="loading || !input.trim()"
                    class="flex h-11 w-11 flex-none items-center justify-center rounded-xl bg-brand-600 text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
                    aria-label="Kirim">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.5 4.5a.5.5 0 0 1 .68-.62l16 7.66a.5.5 0 0 1 0 .9l-16 7.66a.5.5 0 0 1-.68-.62L6 12Zm0 0h6" /></svg>
            </button>
        </form>
        <p class="border-t border-gray-100 bg-white px-4 pb-2.5 pt-1.5 text-center text-[10px] leading-snug text-gray-400">
            🔒 Percakapan tersimpan untuk peningkatan layanan. Nama/No. HP yang Kakak bagikan hanya dipakai tim {{ brand() }} untuk follow-up — tidak disebarluaskan.
        </p>
    </section>

    {{-- Reassurance singkat --}}
    <section class="grid gap-3 py-8 sm:grid-cols-3">
        @foreach ([
            ['🎯', 'Paham kebutuhan dulu', 'Kirana bertanya secukupnya, lalu merekomendasikan yang paling pas — bukan yang paling mahal.'],
            ['🗄️', 'Data produk asli', 'Harga, stok, dan spesifikasi diambil langsung dari katalog '.brand().' — tidak mengarang.'],
            ['🤝', 'Tim siap lanjutkan', 'Butuh penawaran proyek atau instalasi? Kirana mengarahkan ke tim kami via WhatsApp.'],
        ] as [$icon, $title, $desc])
            <div class="card p-4 text-center">
                <p class="text-2xl">{{ $icon }}</p>
                <p class="mt-1 text-sm font-semibold text-gray-900">{{ $title }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ $desc }}</p>
            </div>
        @endforeach
    </section>
</div>
@endsection
