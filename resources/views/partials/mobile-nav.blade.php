{{-- Mobile bottom navigation (Beranda, Kategori, Cari, Keranjang, Akun) --}}
<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white lg:hidden" aria-label="Navigasi bawah">
    <div class="mx-auto grid max-w-lg grid-cols-5">
        @php($nav = [
            ['home', 'Beranda', 'M2.25 12 12 3l9.75 9M4.5 9.75V21h5.25v-6h4.5v6H19.5V9.75'],
            ['categories.index', 'Kategori', 'M3.75 6h16.5M3.75 12h16.5m-16.5 6h16.5'],
        ])
        @foreach($nav as [$route, $label, $path])
            <a href="{{ route($route) }}" class="flex flex-col items-center gap-0.5 py-2 text-[11px] {{ request()->routeIs($route) ? 'text-brand-700' : 'text-gray-500' }}">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg>
                {{ $label }}
            </a>
        @endforeach

        {{-- Konsultasi WhatsApp (menggantikan tombol floating) --}}
        @if($whatsappEnabled)
            <a href="{{ whatsapp_link($siteSettings->get('whatsapp.greeting', 'Halo Rekasurya, saya ingin berkonsultasi.')) }}"
               target="_blank" rel="noopener" class="flex flex-col items-center gap-0.5 py-2 text-[11px] text-green-600">
                <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163a11.867 11.867 0 0 1-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 0 1 8.413 3.488 11.824 11.824 0 0 1 3.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 0 1-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 0 0 1.51 5.26l-.999 3.648 3.978-1.207z"/></svg>
                Konsultasi
            </a>
        @else
            <a href="{{ route('search') }}" class="flex flex-col items-center gap-0.5 py-2 text-[11px] {{ request()->routeIs('search') ? 'text-brand-700' : 'text-gray-500' }}">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m1.35-5.4a6.75 6.75 0 1 1-13.5 0 6.75 6.75 0 0 1 13.5 0Z"/></svg>
                Cari
            </a>
        @endif
        <a href="{{ route('cart.index') }}" @click.prevent="$store.cart.openDrawer()" class="relative flex flex-col items-center gap-0.5 py-2 text-[11px] {{ request()->routeIs('cart.*') ? 'text-brand-700' : 'text-gray-500' }}">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272"/></svg>
            <span x-cloak x-show="$store.cart.count > 0" x-text="$store.cart.count" class="absolute right-6 top-1 grid h-4 min-w-4 place-items-center rounded-full bg-accent-500 px-1 text-[10px] font-bold text-white"></span>
            Keranjang
        </a>
        <a href="{{ auth()->check() ? route('account.dashboard') : route('login') }}" class="flex flex-col items-center gap-0.5 py-2 text-[11px] {{ request()->routeIs('account.*') ? 'text-brand-700' : 'text-gray-500' }}">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.1a7.5 7.5 0 0 1 15 0A17.9 17.9 0 0 1 12 21.75c-2.7 0-5.2-.6-7.5-1.65Z"/></svg>
            Akun
        </a>
    </div>
</nav>
