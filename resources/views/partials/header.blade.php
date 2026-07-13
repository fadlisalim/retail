@php($waConsult = whatsapp_link($siteSettings->get('whatsapp.greeting', 'Halo Rekasurya, saya ingin berkonsultasi.')))
<header class="sticky top-0 z-40 border-b border-gray-200 bg-white/95 backdrop-blur" x-data="{ mobileMenu: false, mega: false }">
    {{-- Top utility strip (desktop) --}}
    <div class="hidden border-b border-gray-100 bg-brand-700 text-white lg:block">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-1.5 text-xs">
            <p>{{ config('rekasurya.company.tagline') }}</p>
            <nav class="flex items-center gap-4" aria-label="Menu utilitas">
                <a href="{{ route('promo') }}" class="hover:text-accent-400">Promo</a>
                <a href="{{ route('surplus') }}" class="hover:text-accent-400">Barang Sisa Proyek</a>
                <a href="{{ route('quotations.create') }}" class="hover:text-accent-400">Permintaan Penawaran</a>
                <a href="{{ route('faq') }}" class="hover:text-accent-400">Bantuan</a>
            </nav>
        </div>
    </div>

    <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3 sm:px-6">
        {{-- Mobile menu toggle --}}
        <button type="button" class="lg:hidden" @click="mobileMenu = true" aria-label="Buka menu">
            <svg class="h-7 w-7 text-gray-700" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/></svg>
        </button>

        {{-- Logo --}}
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2" aria-label="Beranda {{ brand() }}">
            <span class="grid h-9 w-9 place-items-center rounded-lg bg-brand-600 text-white font-bold">{{ mb_substr(brand(), 0, 1) }}</span>
            <x-wordmark class="hidden text-lg font-extrabold tracking-tight text-brand-700 sm:block" />
        </a>

        {{-- All categories (desktop mega trigger) --}}
        <button type="button" class="btn-outline hidden shrink-0 lg:inline-flex" @click="mega = !mega" @click.away="mega = false" aria-haspopup="true" :aria-expanded="mega">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6h16.5M3.75 12h16.5m-16.5 6h16.5"/></svg>
            Semua Kategori
        </button>

        {{-- Search --}}
        <form action="{{ route('search') }}" method="GET" class="relative flex-1" role="search" x-data="search()" @click.away="open=false">
            <label for="q" class="sr-only">Cari produk</label>
            <input id="q" name="q" type="search" x-model="query" @input="onInput" @focus="open = results.length>0" autocomplete="off"
                   value="{{ request('q') }}" placeholder="Cari panel surya, inverter, baterai…"
                   class="w-full rounded-full border-gray-300 py-2.5 pl-4 pr-11 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
            <button type="submit" class="absolute right-1 top-1/2 -translate-y-1/2 rounded-full bg-brand-600 p-2 text-white hover:bg-brand-700" aria-label="Cari">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m1.35-5.4a6.75 6.75 0 1 1-13.5 0 6.75 6.75 0 0 1 13.5 0Z"/></svg>
            </button>

            {{-- Autocomplete dropdown --}}
            <div x-show="open" x-cloak x-transition class="absolute z-50 mt-2 w-full overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg">
                <template x-for="item in results" :key="item.slug">
                    <a :href="item.url" class="flex items-center gap-3 px-3 py-2 hover:bg-brand-50">
                        <img :src="item.image" alt="" class="h-10 w-10 rounded object-cover" loading="lazy">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-gray-800" x-text="item.name"></span>
                            <span class="block text-xs text-gray-500" x-text="item.brand + ' • ' + item.category"></span>
                        </span>
                        <span class="text-sm font-semibold text-brand-700" x-text="item.price"></span>
                    </a>
                </template>
            </div>
        </form>

        {{-- Action icons --}}
        <div class="flex items-center gap-1 sm:gap-2">
            <a href="{{ route('compare.index') }}" class="relative hidden rounded-lg p-2 text-gray-600 hover:bg-gray-100 sm:block" aria-label="Perbandingan">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5"/></svg>
                @if($compareCount)<span class="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-accent-500 px-1 text-[10px] font-bold text-white">{{ $compareCount }}</span>@endif
            </a>
            <a href="{{ route('wishlist.index') }}" class="relative rounded-lg p-2 text-gray-600 hover:bg-gray-100" aria-label="Wishlist">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"/></svg>
                @if($wishlistCount)<span class="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-accent-500 px-1 text-[10px] font-bold text-white">{{ $wishlistCount }}</span>@endif
            </a>
            <a href="{{ route('cart.index') }}" class="relative rounded-lg p-2 text-gray-600 hover:bg-gray-100" aria-label="Keranjang">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/></svg>
                @if($cartCount)<span class="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-accent-500 px-1 text-[10px] font-bold text-white">{{ $cartCount }}</span>@endif
            </a>
            <a href="{{ auth()->check() ? route('account.dashboard') : route('login') }}" class="rounded-lg p-2 text-gray-600 hover:bg-gray-100" aria-label="Akun">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
            </a>
            @if($whatsappEnabled)
            <a href="{{ $waConsult }}" target="_blank" rel="noopener" class="btn-accent hidden xl:inline-flex">
                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163a11.867 11.867 0 0 1-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 0 1 8.413 3.488 11.824 11.824 0 0 1 3.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 0 1-5.688-1.448L.057 24z"/></svg>
                Konsultasi
            </a>
            @endif
        </div>
    </div>

    {{-- Desktop category bar / mega menu --}}
    <nav class="relative hidden border-t border-gray-100 lg:block" aria-label="Kategori">
        <div class="mx-auto flex max-w-7xl items-center gap-5 px-6 py-2 text-sm">
            @foreach($navCategories->take(8) as $cat)
                <a href="{{ route('categories.show', $cat->slug) }}" class="font-medium text-gray-600 hover:text-brand-700">{{ $cat->name }}</a>
            @endforeach
        </div>
        <div x-show="mega" x-cloak x-transition @click.away="mega=false" class="absolute inset-x-0 top-full z-40 border-t border-gray-100 bg-white shadow-lg">
            <div class="mx-auto grid max-w-7xl grid-cols-2 gap-6 px-6 py-6 md:grid-cols-4">
                @foreach($navCategories as $cat)
                    <div>
                        <a href="{{ route('categories.show', $cat->slug) }}" class="mb-2 flex items-center gap-2 font-semibold text-brand-700">{{ $cat->name }}</a>
                        <ul class="space-y-1">
                            @foreach($cat->activeChildren as $child)
                                <li><a href="{{ route('categories.show', $child->slug) }}" class="text-sm text-gray-600 hover:text-brand-700">{{ $child->name }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    </nav>

    {{-- Mobile drawer --}}
    <div x-show="mobileMenu" x-cloak class="fixed inset-0 z-50 lg:hidden">
        <div class="absolute inset-0 bg-black/40" @click="mobileMenu=false"></div>
        <div class="absolute left-0 top-0 h-full w-80 max-w-[85%] overflow-y-auto bg-white p-4 shadow-xl" x-transition:enter="transition" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0">
            <div class="mb-4 flex items-center justify-between">
                <span class="font-bold text-brand-700">Semua Kategori</span>
                <button @click="mobileMenu=false" aria-label="Tutup"><svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg></button>
            </div>
            <ul class="space-y-1">
                @foreach($navCategories as $cat)
                    <li>
                        <a href="{{ route('categories.show', $cat->slug) }}" class="block rounded-lg px-3 py-2 font-medium text-gray-700 hover:bg-brand-50">{{ $cat->name }}</a>
                        @if($cat->activeChildren->isNotEmpty())
                            <ul class="ml-3 border-l border-gray-100 pl-3">
                                @foreach($cat->activeChildren as $child)
                                    <li><a href="{{ route('categories.show', $child->slug) }}" class="block rounded px-2 py-1.5 text-sm text-gray-600 hover:bg-brand-50">{{ $child->name }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</header>
