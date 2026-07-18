@extends('layouts.auth')
@section('title', 'Masuk')

@section('content')
    <h1 class="mb-1 text-xl font-bold text-gray-900">Masuk ke Akun Anda</h1>
    <p class="mb-6 text-sm text-gray-500">Belum punya akun? <a href="{{ route('register') }}" class="font-medium text-brand-600 hover:underline">Daftar gratis</a></p>

    <form action="{{ route('login') }}" method="POST" class="space-y-4">
        @csrf
        <x-form.input name="email" label="Email" type="email" required autofocus />
        <x-form.input name="password" label="Kata Sandi" type="password" required />

        <div class="flex items-center justify-between">
            <x-form.checkbox name="remember" label="Ingat saya" />
            <a href="{{ route('password.request') }}" class="text-sm font-medium text-brand-600 hover:underline">Lupa password?</a>
        </div>

        <button type="submit" class="btn-primary w-full">Masuk</button>
    </form>

    {{-- Resend verification (shown after a "belum diverifikasi" login attempt). --}}
    <div x-data="{ open: {{ request('verify') ? 'true' : 'false' }} }" class="mt-4">
        <button type="button" @click="open = !open" class="text-sm font-medium text-brand-600 hover:underline">
            Belum menerima email verifikasi?
        </button>
        <div x-show="open" x-cloak class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-4">
            <p class="mb-2 text-sm text-amber-800">Masukkan email &amp; kata sandi Anda untuk kirim ulang tautan verifikasi.</p>
            <form action="{{ route('verification.send') }}" method="POST" class="space-y-2">
                @csrf
                <input type="email" name="email" value="{{ request('verify') }}" placeholder="Email" required class="form-input">
                <x-form.input name="password" type="password" placeholder="Kata sandi" required />
                <button type="submit" class="btn-outline w-full">Kirim Ulang Tautan Verifikasi</button>
            </form>
        </div>
    </div>

    @if (config('rekasurya.demo.expose_credentials'))
        <div class="mt-6 rounded-lg border border-dashed border-brand-300 bg-brand-50 p-3 text-xs text-brand-800">
            <p class="mb-1 font-semibold">Akun demo (development):</p>
            <ul class="space-y-0.5">
                <li>Super Admin — <code>superadmin@rekasurya.test</code> / <code>password</code></li>
                <li>Admin Katalog — <code>katalog@rekasurya.test</code> / <code>password</code></li>
                <li>Admin Gudang — <code>gudang@rekasurya.test</code> / <code>password</code></li>
                <li>Customer — <code>customer@rekasurya.test</code> / <code>password</code></li>
            </ul>
        </div>
    @endif
@endsection
