@extends('layouts.auth')
@section('title', 'Verifikasi Email')

@section('content')
    <h1 class="mb-1 text-xl font-bold text-gray-900">Verifikasi Email Anda</h1>
    <p class="mb-6 text-sm text-gray-500">
        Kami sudah mengirim link verifikasi ke <strong>{{ auth()->user()->email }}</strong>.
        Silakan buka email dan klik link tersebut untuk mengaktifkan akun.
    </p>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('status') }}
        </div>
    @endif

    <div class="space-y-3">
        <form action="{{ route('verification.send') }}" method="POST">
            @csrf
            <button type="submit" class="btn-primary w-full">Kirim Ulang Email Verifikasi</button>
        </form>
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="btn-outline w-full">Keluar</button>
        </form>
    </div>

    <p class="mt-6 text-xs text-gray-400">Tidak menerima email? Cek folder spam, atau klik kirim ulang di atas.</p>
@endsection
