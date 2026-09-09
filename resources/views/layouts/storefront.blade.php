<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.favicons')

    <title>@yield('title', config('rekasurya.company.brand_name').' — '.config('rekasurya.company.tagline'))</title>
    <meta name="description" content="@yield('meta_description', 'Pusat produk energi terbarukan: panel surya, inverter, baterai lithium, paket PLTS, dan kebutuhan proyek.')">
    @hasSection('meta_keywords')<meta name="keywords" content="@yield('meta_keywords')">@endif
    <link rel="canonical" href="@yield('canonical', url()->current())">
    @hasSection('noindex')<meta name="robots" content="noindex,nofollow">@endif

    {{-- Open Graph / Twitter — powers WhatsApp / social link previews --}}
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:site_name" content="{{ config('rekasurya.company.brand_name') }}">
    <meta property="og:url" content="@yield('canonical', url()->current())">
    <meta property="og:title" content="@yield('title', config('rekasurya.company.brand_name'))">
    <meta property="og:description" content="@yield('meta_description', config('rekasurya.company.tagline'))">
    <meta property="og:image" content="@yield('og_image', asset('images/og-default.png'))">
    <meta property="og:image:secure_url" content="@yield('og_image', asset('images/og-default.png'))">
    {{-- Explicit type + dimensions are what make WhatsApp render the LARGE image
         card on the first scrape (without them it falls back to a small icon). --}}
    <meta property="og:image:type" content="@yield('og_image_type', 'image/png')">
    <meta property="og:image:width" content="@yield('og_image_width', '1200')">
    <meta property="og:image:height" content="@yield('og_image_height', '630')">
    @hasSection('og_image_alt')<meta property="og:image:alt" content="@yield('og_image_alt')">@endif
    <meta name="twitter:card" content="summary_large_image">

    @include('partials.meta-pixel')
    @stack('head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data data-cart-count="{{ $cartCount }}" class="min-h-screen bg-gray-50 text-gray-800">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-2 focus:rounded focus:bg-brand-600 focus:px-4 focus:py-2 focus:text-white">Lewati ke konten</a>

    @include('partials.header')

    <main id="main" class="mx-auto w-full max-w-7xl px-4 pb-24 pt-4 sm:px-6 lg:pb-10">
        <x-flash />
        @yield('content')
    </main>

    @include('partials.footer')
    @include('partials.mobile-nav')
    @include('partials.mini-cart')
    {{-- Halaman /konsultasi punya chat Kirana full-page sendiri — widget
         mengambang disembunyikan di sana supaya tidak dobel. --}}
    @unless (View::hasSection('hide_cs_widget'))
        @include('partials.cs-chat')
    @endunless
    @include('partials.site-chat')

    @stack('scripts')
</body>
</html>
