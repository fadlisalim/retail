@extends('layouts.storefront')

@section('title', 'Alamat Saya — '.config('rekasurya.company.brand_name'))
@section('noindex', 'noindex')

@section('content')
    <x-breadcrumbs :items="[['label' => 'Akun', 'url' => route('account.dashboard')], ['label' => 'Alamat']]" />

    <div class="grid gap-6 lg:grid-cols-[240px_1fr]">
        @include('partials.account-nav')

        <div class="min-w-0 space-y-6">
            <div class="flex items-center justify-between gap-3">
                <h1 class="text-xl font-bold text-gray-800">Alamat Saya</h1>
                <a href="{{ route('account.addresses.create') }}" class="btn-primary text-sm">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    Tambah Alamat
                </a>
            </div>

            @if ($addresses->isEmpty())
                <div class="card px-4 py-12 text-center">
                    <p class="text-sm text-gray-500">Anda belum menambahkan alamat pengiriman.</p>
                    <a href="{{ route('account.addresses.create') }}" class="btn-primary mt-4">Tambah Alamat Pertama</a>
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($addresses as $a)
                        <article class="card flex flex-col p-4">
                            <div class="mb-2 flex items-center gap-2">
                                <span class="badge bg-brand-100 text-brand-700">{{ $a->label }}</span>
                                @if ($a->is_default)
                                    <span class="badge bg-green-100 text-green-700">Utama</span>
                                @endif
                            </div>
                            <p class="font-semibold text-gray-800">{{ $a->recipient_name }}</p>
                            <p class="text-sm text-gray-500">{{ $a->phone }}</p>
                            @if ($a->company_name)
                                <p class="text-sm text-gray-500">{{ $a->company_name }}</p>
                            @endif
                            <p class="mt-2 flex-1 text-sm text-gray-600">{{ $a->fullAddress() }}</p>

                            <div class="mt-4 flex items-center gap-3 border-t border-gray-100 pt-3">
                                <a href="{{ route('account.addresses.edit', $a) }}" class="text-sm font-medium text-brand-600 hover:underline">Ubah</a>
                                <form action="{{ route('account.addresses.destroy', $a) }}" method="POST"
                                      onsubmit="return confirm('Hapus alamat ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-medium text-red-600 hover:underline">Hapus</button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
