@extends('layouts.storefront')

@section('title', 'Konsultasi Gratis dengan Kirana — '.brand())
@section('meta_description', 'Bingung pilih panel surya, inverter, baterai, atau power station? Chat langsung dengan Kirana, konsultan energi cerdas '.brand().' — gratis, 24 jam, bisa kirim foto.')
@section('chat_page', '1')
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
        'Kebutuhan usaha/proyek' => 'Saya butuh solusi listrik untuk usaha/proyek saya',
    ];
    $welcome = 'Halo 👋 Aku Kirana dari '.brand().'. Aku bantu carikan solusi energi yang paling cocok dengan kebutuhan dan budget Kakak — dari backup mati lampu, PLTS rumah, sampai power station buat perjalanan. Boleh juga kirim foto (nameplate perangkat, meteran, atau atap rumah) biar rekomendasiku makin pas. Saat ini lagi cari untuk kebutuhan rumah, usaha, perjalanan, atau proyek?';
@endphp

@section('content')
<div x-data="csChat({ endpoint: '{{ route('assistant.chat') }}', history: '{{ route('assistant.history') }}', contact: '{{ route('assistant.contact') }}', click: '{{ route('assistant.click') }}', upload: '{{ route('assistant.upload') }}', welcomes: @js([$welcome]) })"
     class="flex min-h-0 w-full flex-1 flex-col">

    {{-- Bar atas ala WhatsApp Web: layar penuh, tanpa header situs; tombol kembali ke website. --}}
    <div class="flex-none bg-brand-700 text-white shadow-md">
        <div class="mx-auto flex w-full max-w-4xl items-center gap-3 px-3 py-2.5">
            <a href="{{ route('home') }}" class="flex h-9 w-9 flex-none items-center justify-center rounded-lg bg-white text-brand-700" title="Beranda {{ brand() }}" aria-label="Beranda">
                <x-logo class="h-6 w-6" />
            </a>
            <span class="relative flex h-10 w-10 flex-none items-center justify-center rounded-full bg-white/20 text-base font-bold">
                K
                <span class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-brand-700 bg-green-400"></span>
            </span>
            <div class="min-w-0 flex-1 leading-tight">
                <p class="truncate text-sm font-semibold">Kirana · Konsultan Energi {{ brand() }}</p>
                <p class="truncate text-[11px] text-white/80">online — gratis 24 jam · bisa kirim foto 📷</p>
            </div>
            <a href="{{ route('products.index') }}" class="flex-none rounded-full bg-white/15 px-3 py-1.5 text-xs font-semibold transition hover:bg-white/25">Katalog</a>
            <a href="{{ route('home') }}" class="flex-none rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-brand-700 transition hover:bg-brand-50">
                Ke Website ↗
            </a>
        </div>
    </div>

    {{-- Percakapan (latar bermotif ala WA) --}}
    {{-- Padding samping mengunci kolom percakapan ±56rem di layar lebar (ala WA Web),
         tetap 0.75rem di ponsel — inline supaya tidak bergantung kelas Tailwind baru. --}}
    <div x-ref="log" class="min-h-0 flex-1 space-y-2 overflow-y-auto py-4"
         style="padding-left: max(0.75rem, calc((100% - 56rem) / 2)); padding-right: max(0.75rem, calc((100% - 56rem) / 2)); background-color: #e9efec; background-image: radial-gradient(circle at 1px 1px, rgba(15, 118, 110, 0.07) 1px, transparent 0); background-size: 22px 22px;">

        <template x-for="(m, i) in messages" :key="i">
            <div :class="m.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                <div class="max-w-[85%] space-y-1.5 sm:max-w-[75%]">
                    <div class="rounded-lg px-3 py-2 text-sm leading-relaxed shadow-sm [&_strong]:font-semibold"
                         :class="m.role === 'user' ? 'rounded-tr-none bg-brand-600 text-white' : 'rounded-tl-none bg-white text-gray-800'">
                        {{-- Foto yang dikirim pelanggan --}}
                        <template x-if="m.image">
                            <a :href="m.image" target="_blank" rel="noopener" class="mb-1.5 block">
                                <img :src="m.image" alt="Foto terlampir" class="max-h-64 w-full rounded-lg object-cover" loading="lazy" />
                            </a>
                        </template>
                        <span x-show="m.content" x-html="render(m.content)"></span>
                    </div>

                    {{-- Kartu produk yang direkomendasikan Kirana --}}
                    <template x-if="m.role === 'assistant' && m.products && m.products.length">
                        <div class="space-y-1.5">
                            <template x-for="p in m.products" :key="p.url">
                                <a :href="p.url" @click="track('product', p.slug)"
                                   class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-2.5 shadow-sm transition hover:border-brand-300 hover:shadow">
                                    <img :src="p.image" :alt="p.name" class="h-14 w-14 flex-none rounded-lg object-cover" loading="lazy" />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-gray-800" x-text="p.name"></p>
                                        <p class="text-sm font-bold text-brand-700">
                                            <span x-text="p.price"></span>
                                            <span x-show="!p.in_stock" class="ml-1 text-xs font-normal text-red-500">(stok habis)</span>
                                        </p>
                                    </div>
                                    <span class="flex-none rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700">Lihat</span>
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

        {{-- Chip kebutuhan — tampil selama percakapan masih di awal --}}
        <div x-show="messages.length <= 1" x-cloak class="flex flex-wrap gap-1.5 pt-1">
            @foreach ($starters as $label => $message)
                <button type="button" @click="sendChip(@js($message))"
                        class="rounded-full border border-brand-300 bg-white px-3 py-1.5 text-xs font-medium text-brand-700 shadow-sm transition hover:bg-brand-50">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Typing indicator --}}
        <div x-show="loading" class="flex justify-start">
            <div class="rounded-lg rounded-tl-none bg-white px-3.5 py-2.5 shadow-sm">
                <span class="flex gap-1">
                    <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400" style="animation-delay: 0ms"></span>
                    <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400" style="animation-delay: 150ms"></span>
                    <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400" style="animation-delay: 300ms"></span>
                </span>
            </div>
        </div>
    </div>

    {{-- Composer ala WA: lampiran foto + teks + kirim --}}
    <div class="flex-none border-t border-gray-200 bg-white" style="padding-left: max(0rem, calc((100% - 56rem) / 2)); padding-right: max(0rem, calc((100% - 56rem) / 2));">
        {{-- Preview foto yang menunggu dikirim --}}
        <div x-show="pendingImage" x-cloak class="flex items-center gap-3 border-b border-gray-100 px-3 py-2">
            <img :src="pendingImage?.url" alt="Foto terpilih" class="h-14 w-14 rounded-lg object-cover" />
            <p class="flex-1 text-xs text-gray-500" x-text="pendingImage?.uploading ? 'Mengunggah foto…' : 'Foto siap dikirim — tambahkan pesan atau langsung kirim.'"></p>
            <button type="button" @click="removeAttachment()" class="rounded-full p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600" aria-label="Hapus foto">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 6 12 12M18 6 6 18" /></svg>
            </button>
        </div>

        <form @submit.prevent="send()" class="flex items-end gap-1.5 p-2">
            <label class="flex h-11 w-11 flex-none cursor-pointer items-center justify-center rounded-full text-gray-500 transition hover:bg-gray-100 hover:text-brand-600" aria-label="Lampirkan foto">
                <input type="file" accept="image/jpeg,image/png,image/webp" capture="environment" class="hidden" @change="attachFile($event)">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" /></svg>
            </label>
            <textarea
                x-model="input"
                x-ref="composer"
                @keydown.enter.prevent="send()"
                rows="1"
                placeholder="Ketik pesan…"
                class="max-h-28 min-h-[2.75rem] flex-1 resize-none rounded-3xl border border-gray-300 px-4 py-2.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
            ></textarea>
            <button type="submit" :disabled="loading || (!input.trim() && !(pendingImage && !pendingImage.uploading))"
                    class="flex h-11 w-11 flex-none items-center justify-center rounded-full bg-brand-600 text-white shadow transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
                    aria-label="Kirim">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.5 4.5a.5.5 0 0 1 .68-.62l16 7.66a.5.5 0 0 1 0 .9l-16 7.66a.5.5 0 0 1-.68-.62L6 12Zm0 0h6" /></svg>
            </button>
        </form>
        <p class="px-4 pb-1.5 text-center text-[10px] leading-snug text-gray-400">
            🔒 Percakapan &amp; foto tersimpan untuk peningkatan layanan · Nama/No. HP hanya dipakai tim {{ brand() }} untuk follow-up
        </p>
    </div>
</div>
@endsection
