@php
    $navItems = [
        ['route' => 'account.dashboard', 'pattern' => 'account.dashboard', 'label' => 'Dashboard', 'icon' => 'M2.25 12 11.2 3.05a1.13 1.13 0 0 1 1.6 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75'],
        ['route' => 'account.orders', 'pattern' => 'account.orders*', 'label' => 'Pesanan', 'icon' => 'M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12A1.125 1.125 0 0 1 19.749 21H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007Z'],
        ['route' => 'account.quotations', 'pattern' => 'account.quotations*', 'label' => 'Quotation', 'icon' => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z'],
        ['route' => 'account.addresses.index', 'pattern' => 'account.addresses.*', 'label' => 'Alamat', 'icon' => 'M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z'],
        ['route' => 'account.wishlist', 'pattern' => 'account.wishlist', 'label' => 'Wishlist', 'icon' => 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z'],
        ['route' => 'account.reviews', 'pattern' => 'account.reviews*', 'label' => 'Review', 'icon' => 'M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z'],
        ['route' => 'account.affiliate.dashboard', 'pattern' => 'account.affiliate*', 'label' => 'Afiliasi', 'icon' => 'M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244'],
        ['route' => 'account.notifications', 'pattern' => 'account.notifications*', 'label' => 'Notifikasi', 'icon' => 'M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0'],
        ['route' => 'account.profile', 'pattern' => 'account.profile*', 'label' => 'Profil', 'icon' => 'M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z'],
    ];
    $logoutIcon = 'M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75';
@endphp
<aside class="lg:sticky lg:top-24 lg:self-start" aria-label="Menu akun">
    {{-- ============================================================
         MOBILE (< lg): all menus visible as a compact icon-card grid
         so every section is discoverable at a glance. Pure links —
         no JS toggle needed.
         ============================================================ --}}
    <div class="lg:hidden">
        <div class="mb-3 flex items-center justify-between gap-3 px-1">
            <div class="min-w-0">
                <p class="text-xs uppercase tracking-wide text-brand-600">Akun Saya</p>
                <p class="truncate font-semibold text-brand-800">{{ auth()->user()->name }}</p>
            </div>
            <form action="{{ route('logout') }}" method="POST" class="shrink-0">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $logoutIcon }}" />
                    </svg>
                    Keluar
                </button>
            </form>
        </div>
        <nav aria-label="Menu akun" class="grid grid-cols-5 gap-2">
            @foreach ($navItems as $item)
                @php($active = request()->routeIs($item['pattern']))
                @php($isAffiliate = $item['route'] === 'account.affiliate.dashboard')
                <a href="{{ route($item['route']) }}"
                   @if($active) aria-current="page" @endif
                   class="relative flex min-h-[4.75rem] flex-col items-center justify-center gap-1.5 rounded-xl border px-1 py-2 text-center transition
                          {{ $isAffiliate
                                ? 'border-red-200 bg-red-50 text-red-600'
                                : ($active
                                    ? 'border-brand-200 bg-brand-50 text-brand-700'
                                    : 'border-gray-100 bg-white text-gray-600 hover:border-brand-200 hover:bg-brand-50/60') }}">
                    @if ($isAffiliate)
                        {{-- Static red dot to draw the eye (strong pulse lives on the dashboard banner). --}}
                        <span class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-red-500"></span>
                    @endif
                    <svg class="h-6 w-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                    </svg>
                    <span class="text-[11px] font-medium leading-tight">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>
    </div>

    {{-- ============================================================
         DESKTOP (lg+): vertical sidebar (unchanged behaviour)
         ============================================================ --}}
    <div class="hidden lg:block">
        <div class="card overflow-hidden">
            <div class="border-b border-gray-100 bg-brand-50 px-4 py-3">
                <p class="text-xs uppercase tracking-wide text-brand-600">Akun Saya</p>
                <p class="truncate font-semibold text-brand-800">{{ auth()->user()->name }}</p>
            </div>
            <nav class="p-2">
                <ul class="space-y-0.5">
                    @foreach ($navItems as $item)
                        @php($active = request()->routeIs($item['pattern']))
                        @php($isAffiliate = $item['route'] === 'account.affiliate.dashboard')
                        <li>
                            <a href="{{ route($item['route']) }}"
                               @if($active) aria-current="page" @endif
                               class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition {{ $active ? 'bg-brand-50 font-semibold text-brand-700' : 'text-gray-600 hover:bg-gray-50 hover:text-brand-700' }}">
                                <svg class="h-5 w-5 shrink-0 {{ $isAffiliate && ! $active ? 'text-red-500' : '' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                                </svg>
                                <span>{{ $item['label'] }}</span>
                                @if ($isAffiliate)
                                    <span class="ml-auto h-2 w-2 rounded-full bg-red-500"></span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
            <div class="border-t border-gray-100 p-2">
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-red-600 transition hover:bg-red-50">
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $logoutIcon }}" />
                        </svg>
                        <span>Keluar</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</aside>
