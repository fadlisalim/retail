{{-- Chat Toko: Tokopedia-style chat with a HUMAN admin (not the AI). Opened
     from the "Tanya Produk Ini" button on product pages; replies are written
     by admins in Admin → Chat Toko and picked up here by polling. --}}
<div
    x-data="siteChat({ send: '{{ route('sitechat.send') }}', poll: '{{ route('sitechat.messages') }}', auth: @js(auth()->check()) })"
    @open-site-chat.window="openWith($event.detail)"
    @open-cs-chat.window="open = false"
    x-cloak
    class="print:hidden"
>
    <div
        x-show="open"
        x-transition.origin-bottom.right
        class="fixed right-4 bottom-20 z-50 flex w-[calc(100vw-2rem)] max-w-sm flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl lg:bottom-6"
        style="height: min(70vh, 32rem)"
    >
        {{-- Header --}}
        <div class="flex items-center justify-between bg-gray-900 px-4 py-3 text-white">
            <div class="flex items-center gap-2">
                <span class="relative flex h-8 w-8 items-center justify-center rounded-full bg-white/15 text-sm font-bold">A
                    <span class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full border-2 border-gray-900 bg-green-400"></span>
                </span>
                <div class="leading-tight">
                    <p class="text-sm font-semibold">Chat Admin · {{ brand() }}</p>
                    <p class="text-[11px] text-white/70">Dibalas langsung oleh tim kami (bukan bot)</p>
                </div>
            </div>
            <button type="button" @click="close()" class="rounded p-1 hover:bg-white/20" aria-label="Tutup">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 6 12 12M18 6 6 18" /></svg>
            </button>
        </div>

        {{-- Messages --}}
        <div x-ref="log" class="flex-1 space-y-2 overflow-y-auto bg-gray-50 px-3 py-3">
            <div class="rounded-2xl rounded-bl-sm bg-white px-3 py-2 text-sm leading-relaxed text-gray-700 shadow-sm">
                Halo Kak! 👋 Ini chat langsung ke <strong>admin {{ brand() }}</strong>. Tulis pertanyaannya, nanti tim kami balas di sini ya 😊
            </div>
            <template x-for="(m, i) in messages" :key="m.id || 'l' + i">
                <div :class="m.direction === 'in' ? 'flex justify-end' : 'flex justify-start'">
                    <div class="max-w-[85%] space-y-1">
                        <template x-if="m.product">
                            <a :href="m.product.url" class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white p-2 shadow-sm transition hover:border-brand-300">
                                <img :src="m.product.image" :alt="m.product.name" class="h-11 w-11 flex-none rounded-lg object-cover" loading="lazy" />
                                <span class="min-w-0">
                                    <span class="block truncate text-xs font-semibold text-gray-800" x-text="m.product.name"></span>
                                    <span class="block text-xs font-bold text-brand-700" x-text="m.product.price"></span>
                                </span>
                            </a>
                        </template>
                        <div class="whitespace-pre-wrap rounded-2xl px-3 py-2 text-sm leading-relaxed"
                             :class="m.direction === 'in' ? 'rounded-br-sm bg-brand-600 text-white' : 'rounded-bl-sm bg-white text-gray-800 shadow-sm'">
                            <span x-text="m.message"></span>
                            <span class="ml-1.5 align-bottom text-[10px]" :class="m.direction === 'in' ? 'text-white/70' : 'text-gray-400'" x-text="m.time"></span>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- Product attachment preview (next message will carry this product) --}}
        <div x-show="attached" x-cloak class="flex items-center gap-2 border-t border-gray-100 bg-brand-50/60 px-3 py-2">
            <img :src="attached?.image" class="h-9 w-9 flex-none rounded-lg object-cover" alt="" />
            <div class="min-w-0 flex-1">
                <p class="truncate text-xs font-semibold text-gray-800" x-text="attached?.name"></p>
                <p class="text-[11px] font-bold text-brand-700" x-text="attached?.price"></p>
            </div>
            <button type="button" @click="attached = null" class="rounded p-1 text-gray-400 hover:bg-white" aria-label="Hapus lampiran">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 6 12 12M18 6 6 18" /></svg>
            </button>
        </div>

        {{-- Guest contact mini-form (required once, so admin can follow up) --}}
        <div x-show="needContact" x-cloak class="space-y-1.5 border-t border-gray-100 bg-white px-3 pt-2">
            <p class="text-[11px] font-medium text-gray-600">Sebelum mulai, kenalan dulu ya Kak 👇</p>
            <div class="flex gap-1.5">
                <input x-model="name" type="text" maxlength="120" placeholder="Nama"
                       class="w-2/5 rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500" />
                <input x-model="phone" type="tel" maxlength="32" placeholder="Nomor WhatsApp (08…)"
                       class="flex-1 rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500" />
            </div>
        </div>

        {{-- Input --}}
        <form @submit.prevent="send()" class="flex items-end gap-2 border-t border-gray-100 bg-white p-2">
            <textarea
                x-model="input"
                @keydown.enter.prevent="send()"
                rows="1"
                placeholder="Tulis pesan untuk admin…"
                class="max-h-24 min-h-[2.5rem] flex-1 resize-none rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
            ></textarea>
            <button
                type="submit"
                :disabled="sending || !input.trim()"
                class="flex h-10 w-10 flex-none items-center justify-center rounded-xl bg-brand-600 text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
                aria-label="Kirim"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.5 4.5a.5.5 0 0 1 .68-.62l16 7.66a.5.5 0 0 1 0 .9l-16 7.66a.5.5 0 0 1-.68-.62L6 12Zm0 0h6" /></svg>
            </button>
        </form>
        <p x-show="error" x-cloak class="bg-white px-3 pb-1 text-[11px] text-red-500" x-text="error"></p>
        <p class="border-t border-gray-100 bg-white px-3 pb-2 pt-1 text-center text-[10px] leading-snug text-gray-400">
            🔒 Chat & kontak Kakak hanya dipakai tim {{ brand() }} untuk membalas — tidak disebarluaskan.
        </p>
    </div>
</div>
