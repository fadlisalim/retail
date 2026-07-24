@php($assistantEnabled = (bool) config('services.anthropic.enabled'))
{{-- CS chat assistant. Always rendered (works as a WhatsApp hand-off even when
     the AI key isn't configured), so it's safe to ship before ANTHROPIC_ENABLED. --}}
<div
    @php($csWelcomes = [
        'Halo Kak, aku Kirana dari '.brand().' ⚡ Lagi cari panel surya, inverter, atau baterai? Ceritakan saja kebutuhan Kakak, nanti aku bantu carikan yang paling pas 😊',
        'Hai Kak, Kirana di sini 🙌 Mau beli panel surya satuan, upgrade inverter, atau tambah baterai? Tanya-tanya dulu boleh banget, gratis kok 😊',
        'Selamat datang di '.brand().', Kak! Aku Kirana ☀️ Dari panel surya, inverter, baterai satuan sampai paket PLTS lengkap — semua bisa aku bantu. Kakak lagi butuh apa nih?',
        'Halo Kak 👋 Aku Kirana. Mau hemat tagihan PLN, siap-siap saat mati lampu, atau cari power station buat outdoor? Aku bantu pilihkan ya 😊',
        'Hai Kak, aku Kirana dari '.brand().' ⚡ Butuh panel surya, inverter, atau baterai? Kalau produk yang dicari belum ada di katalog, tim kami juga bisa bantu carikan lho. Cerita dulu yuk 😊',
    ])
    x-data="csChat({ endpoint: '{{ route('assistant.chat') }}', history: '{{ route('assistant.history') }}', contact: '{{ route('assistant.contact') }}', welcomes: @js($csWelcomes) })"
    @open-cs-chat.window="open = true; scrollSoon()"
    x-cloak
    class="print:hidden"
>
    {{-- Launcher --}}
    <button
        type="button"
        x-show="!open"
        @click="toggle()"
        class="fixed right-4 bottom-6 z-40 hidden h-14 w-14 items-center justify-center rounded-full bg-brand-600 text-white shadow-lg transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-400 focus:ring-offset-2 lg:flex"
        aria-label="Tanya CS"
    >
        <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h8M8 14h5m-9 6 3.5-2.1A9 9 0 1 1 21 12a9 9 0 0 1-13 8.1L4 20Z" />
        </svg>
        <span class="absolute -right-0.5 -top-0.5 flex h-3 w-3">
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-accent-400 opacity-75"></span>
            <span class="relative inline-flex h-3 w-3 rounded-full bg-accent-500"></span>
        </span>
    </button>

    {{-- Panel --}}
    <div
        x-show="open"
        x-transition.origin-bottom.right
        class="fixed right-4 bottom-20 z-50 flex w-[calc(100vw-2rem)] max-w-sm flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl lg:bottom-6"
        style="height: min(70vh, 32rem)"
    >
        {{-- Header --}}
        <div class="flex items-center justify-between bg-brand-600 px-4 py-3 text-white">
            <div class="flex items-center gap-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-white/20 text-sm font-bold">K</span>
                <div class="leading-tight">
                    <p class="text-sm font-semibold">Kirana · Asisten {{ brand() }}</p>
                    <p class="text-[11px] text-white/80">Biasanya balas cepat</p>
                </div>
            </div>
            <button type="button" @click="open = false" class="rounded p-1 hover:bg-white/20" aria-label="Tutup">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 6 12 12M18 6 6 18" /></svg>
            </button>
        </div>

        {{-- Messages --}}
        <div x-ref="log" class="flex-1 space-y-3 overflow-y-auto bg-gray-50 px-3 py-3">
            <template x-for="(m, i) in messages" :key="i">
                <div :class="m.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                    <div class="max-w-[85%] space-y-2">
                        <div
                            class="rounded-2xl px-3 py-2 text-sm leading-relaxed [&_strong]:font-semibold"
                            :class="m.role === 'user' ? 'rounded-br-sm bg-brand-600 text-white' : 'rounded-bl-sm bg-white text-gray-800 shadow-sm'"
                            x-html="render(m.content)"
                        ></div>

                        {{-- Related product cards (clickable) --}}
                        <template x-if="m.role === 'assistant' && m.products && m.products.length">
                            <div class="space-y-2">
                                <template x-for="p in m.products" :key="p.url">
                                    <a :href="p.url" class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white p-2 shadow-sm transition hover:border-brand-300">
                                        <img :src="p.image" :alt="p.name" class="h-12 w-12 flex-none rounded-lg object-cover" loading="lazy" />
                                        <div class="min-w-0">
                                            <p class="truncate text-xs font-semibold text-gray-800" x-text="p.name"></p>
                                            <p class="text-xs font-bold text-brand-700">
                                                <span x-text="p.price"></span>
                                                <span x-show="!p.in_stock" class="ml-1 font-normal text-red-500">(stok habis)</span>
                                            </p>
                                        </div>
                                    </a>
                                </template>
                            </div>
                        </template>

                        {{-- WhatsApp hand-off button — shown when the AI can't fully
                             help or the customer needs a human (escalate). --}}
                        <template x-if="m.whatsapp">
                            <a :href="m.whatsapp" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-2 rounded-full bg-green-500 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-green-600">
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163a11.867 11.867 0 0 1-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 0 1 8.413 3.488 11.824 11.824 0 0 1 3.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 0 1-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 0 0 1.51 5.26l-.999 3.648 3.978-1.207z"/></svg>
                                Konsultasi via WhatsApp
                            </a>
                        </template>

                        {{-- Pre-WhatsApp contact form: name + WA number + need are
                             required before the CS number is handed out (lead capture). --}}
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
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163a11.867 11.867 0 0 1-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 0 1 8.413 3.488 11.824 11.824 0 0 1 3.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 0 1-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 0 0 1.51 5.26l-.999 3.648 3.978-1.207z"/></svg>
                                    <span x-text="leadSending ? 'Menyimpan…' : 'Kirim & Lanjut ke WhatsApp'"></span>
                                </button>
                            </form>
                        </template>
                    </div>
                </div>
            </template>

            {{-- Typing indicator --}}
            <div x-show="loading" class="flex justify-start">
                <div class="rounded-2xl rounded-bl-sm bg-white px-3 py-2 shadow-sm">
                    <span class="flex gap-1">
                        <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400" style="animation-delay: 0ms"></span>
                        <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400" style="animation-delay: 150ms"></span>
                        <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400" style="animation-delay: 300ms"></span>
                    </span>
                </div>
            </div>
        </div>

        {{-- Input --}}
        <form @submit.prevent="send()" class="flex items-end gap-2 border-t border-gray-200 bg-white p-2">
            <textarea
                x-model="input"
                @keydown.enter.prevent="send()"
                rows="1"
                placeholder="Tulis pertanyaan…"
                class="max-h-24 min-h-[2.5rem] flex-1 resize-none rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
            ></textarea>
            <button
                type="submit"
                :disabled="loading || !input.trim()"
                class="flex h-10 w-10 flex-none items-center justify-center rounded-xl bg-brand-600 text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
                aria-label="Kirim"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.5 4.5a.5.5 0 0 1 .68-.62l16 7.66a.5.5 0 0 1 0 .9l-16 7.66a.5.5 0 0 1-.68-.62L6 12Zm0 0h6" /></svg>
            </button>
        </form>
        {{-- Privacy note — we may store name/phone the customer shares (leads). --}}
        <p class="border-t border-gray-100 bg-white px-3 pb-2 pt-1 text-center text-[10px] leading-snug text-gray-400">
            🔒 Percakapan tersimpan untuk peningkatan layanan. Nama/No. HP yang Kakak bagikan hanya dipakai tim {{ brand() }} untuk follow-up — tidak disebarluaskan.
        </p>
        @unless($assistantEnabled)
            <p class="bg-amber-50 px-3 py-1.5 text-center text-[11px] text-amber-700">Mode dasar aktif · aktifkan AI di pengaturan server</p>
        @endunless
    </div>
</div>
