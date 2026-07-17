{{-- Mini-cart drawer. State lives in the global Alpine $store.cart; it opens when
     a "+ Keranjang" form adds via fetch, or when the cart icon is clicked. --}}
<div x-cloak x-show="$store.cart.open" class="fixed inset-0 z-[60]" @keydown.escape.window="$store.cart.open = false">
    {{-- Backdrop --}}
    <div x-show="$store.cart.open" x-transition.opacity class="absolute inset-0 bg-black/40" @click="$store.cart.open = false"></div>

    {{-- Panel --}}
    <div x-show="$store.cart.open"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
         class="absolute right-0 top-0 flex h-full w-96 max-w-[90%] flex-col bg-white shadow-2xl">

        {{-- Header --}}
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
            <h2 class="flex items-center gap-2 font-semibold text-gray-800">
                <svg class="h-5 w-5 text-brand-600" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/></svg>
                Keranjang
                <span x-show="$store.cart.count > 0" x-text="'(' + $store.cart.count + ')'" class="text-sm font-normal text-gray-400"></span>
            </h2>
            <button @click="$store.cart.open = false" aria-label="Tutup" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Error --}}
        <div x-show="$store.cart.error" x-cloak class="m-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="$store.cart.error"></div>

        {{-- Body --}}
        <div class="flex-1 overflow-y-auto px-2 py-2">
            <p x-show="$store.cart.loading" class="p-6 text-center text-sm text-gray-400">Memuat…</p>

            <template x-if="!$store.cart.loading && $store.cart.items.length === 0">
                <div class="grid place-items-center gap-2 p-8 text-center">
                    <p class="text-sm text-gray-500">Keranjang masih kosong.</p>
                    <a href="{{ route('products.index') }}" class="btn-outline text-xs">Mulai Belanja</a>
                </div>
            </template>

            <template x-for="(item, i) in $store.cart.items" :key="item.id">
                <div class="flex items-center gap-3 rounded-lg p-2 hover:bg-gray-50">
                    <a :href="item.url" class="flex min-w-0 flex-1 items-center gap-3">
                        <img :src="item.image" alt="" class="h-14 w-14 shrink-0 rounded-lg object-cover">
                        <div class="min-w-0 flex-1">
                            <p class="line-clamp-2 text-sm font-medium text-gray-800" x-text="item.name"></p>
                            <p class="text-xs text-gray-400"><span x-text="item.qty"></span> item<span x-show="item.variant" x-text="' • ' + item.variant"></span></p>
                        </div>
                    </a>
                    <div class="flex shrink-0 flex-col items-end gap-1.5">
                        <span class="text-sm font-semibold text-gray-800" x-text="item.line_formatted"></span>
                        <button type="button" @click="$store.cart.remove(item.id)" :disabled="$store.cart.busy"
                                :aria-label="'Hapus ' + item.name + ' dari keranjang'"
                                class="inline-flex items-center gap-1 text-xs text-gray-400 transition hover:text-red-600 disabled:opacity-50">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                            Hapus
                        </button>
                    </div>
                </div>
            </template>
        </div>

        {{-- Footer --}}
        <div x-show="$store.cart.items.length > 0" class="border-t border-gray-100 p-4">
            <div class="mb-3 flex items-center justify-between text-sm">
                <span class="text-gray-500">Subtotal</span>
                <span class="text-base font-bold text-brand-700" x-text="$store.cart.subtotal"></span>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <a href="{{ route('cart.index') }}" class="btn-outline text-center text-sm">Lihat Keranjang</a>
                <a href="{{ route('checkout.index') }}" class="btn-primary text-center text-sm">Checkout</a>
            </div>
            <p class="mt-2 text-center text-xs text-gray-400">Ongkir &amp; total dihitung di checkout.</p>
        </div>
    </div>
</div>
