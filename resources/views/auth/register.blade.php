@extends('layouts.auth')
@section('title', 'Daftar')

@section('content')
    <h1 class="mb-1 text-xl font-bold text-gray-900">Buat Akun Baru</h1>
    <p class="mb-6 text-sm text-gray-500">Sudah punya akun? <a href="{{ route('login') }}" class="font-medium text-brand-600 hover:underline">Masuk</a></p>

    <form action="{{ route('register') }}" method="POST" class="space-y-4">
        @csrf
        <x-form.input name="name" label="Nama Lengkap" required autofocus />
        <x-form.input name="email" label="Email" type="email" required />
        <x-form.phone name="whatsapp" label="Nomor WhatsApp" :value="old('whatsapp')" required />
        <x-form.input name="password" label="Kata Sandi" type="password" required hint="Minimal 8 karakter, mengandung huruf dan angka." />
        <x-form.input name="password_confirmation" label="Ulangi Kata Sandi" type="password" required />

        <button type="submit" class="btn-primary w-full">Daftar</button>
        <p class="text-center text-xs text-gray-400">Dengan mendaftar Anda menyetujui <a href="{{ route('pages.show', 'syarat-ketentuan') }}" class="underline">Syarat &amp; Ketentuan</a>.</p>
    </form>
@endsection
