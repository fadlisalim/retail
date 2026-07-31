<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.favicons')
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'Admin') — {{ brand() }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-gray-100 text-gray-800" x-data="{ sidebar: false }">
<div class="flex min-h-full">
    {{-- Sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full overflow-y-auto bg-brand-800 text-brand-50 transition lg:translate-x-0"
           :class="sidebar && 'translate-x-0'">
        <div class="flex items-center gap-2 px-5 py-4">
            <span class="grid h-9 w-9 place-items-center rounded-lg bg-white text-brand-700"><x-logo class="h-6 w-6" /></span>
            <span class="font-bold">{{ brand() }} <span class="text-accent-400">Admin</span></span>
        </div>
        @php
            $menu = [
                ['admin.dashboard', 'Dashboard', null],
                ['admin.products.index', 'Produk', 'catalog.manage'],
                ['admin.categories.index', 'Kategori', 'catalog.manage'],
                ['admin.brands.index', 'Brand', 'catalog.manage'],
                ['admin.attributes.index', 'Atribut', 'catalog.manage'],
                ['admin.stock.index', 'Stok', 'inventory.manage'],
                ['admin.warehouses.index', 'Gudang', 'inventory.manage'],
                ['admin.coupons.index', 'Voucher', 'price.manage'],
                ['admin.orders.index', 'Pesanan', 'order.view', 'orders'],
                ['admin.quotations.index', 'Quotation', 'quotation.manage', 'quotations'],
                ['admin.reviews.index', 'Review', 'review.moderate', 'reviews'],
                ['admin.customers.index', 'Customer', 'customer.manage'],
                ['admin.affiliates.index', 'Afiliasi', 'affiliate.manage', 'affiliates'],
                ['admin.banners.index', 'Banner', 'content.manage'],
                ['admin.pages.index', 'Halaman', 'content.manage'],
                ['admin.articles.index', 'Artikel', 'content.manage'],
                ['admin.faqs.index', 'FAQ', 'content.manage'],
                ['admin.settings.edit', 'Pengaturan', 'setting.manage'],
                ['admin.users.index', 'User Admin', 'user.manage'],
                ['admin.traffic.index', 'Statistik Trafik', 'traffic.view'],
                ['admin.assistant.index', 'CS Assistant', 'assistant.view'],
                ['admin.wachat.index', 'WA Chat', 'assistant.view', 'wachat'],
                ['admin.sitechat.index', 'Chat Toko', 'assistant.view', 'sitechat'],
                ['admin.audit.index', 'Audit Log', 'audit.view'],
            ];
        @endphp
        <nav class="space-y-0.5 px-3 pb-10 text-sm">
            @foreach ($menu as $item)
                @php
                    [$route, $label, $permission] = $item;
                    $badgeKey = $item[3] ?? null;
                    $badgeCount = $badgeKey ? (int) ($menuBadges[$badgeKey] ?? 0) : 0;
                @endphp
                @if (! $permission || auth()->user()->can($permission))
                    <a href="{{ route($route) }}"
                       class="flex items-center justify-between gap-2 rounded-lg px-3 py-2 {{ request()->routeIs(str_replace('.index', '', $route).'*') || request()->routeIs($route) ? 'bg-brand-600 font-semibold text-white' : 'text-brand-100 hover:bg-brand-700' }}">
                        <span>{{ $label }}</span>
                        @if ($badgeCount > 0)
                            <span class="inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-red-500 px-1.5 py-0.5 text-xs font-semibold leading-none text-white"
                                  title="{{ $badgeCount }} perlu ditindak">{{ $badgeCount > 99 ? '99+' : $badgeCount }}</span>
                        @endif
                    </a>
                @endif
            @endforeach
        </nav>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col lg:pl-64">
        {{-- Topbar --}}
        <header class="sticky top-0 z-30 flex items-center justify-between border-b border-gray-200 bg-white px-4 py-3">
            <button class="lg:hidden" @click="sidebar = !sidebar" aria-label="Menu">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/></svg>
            </button>
            <div class="ml-auto flex items-center gap-4 text-sm">
                <a href="{{ route('home') }}" target="_blank" class="text-gray-500 hover:text-brand-700">Lihat Toko ↗</a>
                <span class="text-gray-600">{{ auth()->user()->name }}</span>
                <form action="{{ route('logout') }}" method="POST">@csrf<button class="text-red-600 hover:underline">Keluar</button></form>
            </div>
        </header>

        <main class="flex-1 p-4 sm:p-6">
            <x-flash />
            @yield('content')
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
