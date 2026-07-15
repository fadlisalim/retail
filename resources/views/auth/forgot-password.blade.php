@extends('layouts.auth')
@section('title', 'Lupa Kata Sandi')

@section('content')
    <h1 class="mb-1 text-xl font-bold text-gray-900">Lupa Kata Sandi?</h1>
    <p class="mb-6 text-sm text-gray-500">Masukkan email akun Anda. Kami akan mengirim link untuk mengatur ulang kata sandi.</p>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('status') }}
        </div>
    @endif

    <form action="{{ route('password.email') }}" method="POST" class="space-y-4">
        @csrf
        <x-form.input name="email" label="Email" type="email" required autofocus />
        <button type="submit" class="btn-primary w-full">Kirim Link Reset</button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-500">
        <a href="{{ route('login') }}" class="font-medium text-brand-600 hover:underline">← Kembali ke Masuk</a>
    </p>
@endsection
