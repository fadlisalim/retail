@extends('layouts.auth')
@section('title', 'Atur Ulang Kata Sandi')

@section('content')
    <h1 class="mb-1 text-xl font-bold text-gray-900">Atur Ulang Kata Sandi</h1>
    <p class="mb-6 text-sm text-gray-500">Buat kata sandi baru untuk akun Anda.</p>

    <form action="{{ route('password.update') }}" method="POST" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-form.input name="email" label="Email" type="email" :value="old('email', $email)" required />
        <x-form.input name="password" label="Kata Sandi Baru" type="password" required hint="Minimal 8 karakter, ada huruf & angka." />
        <x-form.input name="password_confirmation" label="Ulangi Kata Sandi" type="password" required />
        <button type="submit" class="btn-primary w-full">Simpan Kata Sandi</button>
    </form>
@endsection
