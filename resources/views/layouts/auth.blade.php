<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Masuk') — {{ brand() }}</title>
    @include('partials.favicons')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-gradient-to-br from-brand-50 to-white">
    <div class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-10">
        <a href="{{ route('home') }}" class="mb-8 flex items-center justify-center gap-2">
            <x-logo class="h-10 w-10 shrink-0 text-brand-700" />
            <x-wordmark class="text-xl font-extrabold text-brand-700" />
        </a>
        <div class="card p-6 sm:p-8">
            <x-flash />
            @yield('content')
        </div>
        <p class="mt-6 text-center text-xs text-gray-400">{{ config('rekasurya.company.legal_name') }}</p>
    </div>
</body>
</html>
