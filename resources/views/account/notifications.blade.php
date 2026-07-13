@extends('layouts.storefront')

@section('title', 'Notifikasi — '.config('rekasurya.company.brand_name'))
@section('noindex', 'noindex')

@section('content')
    <x-breadcrumbs :items="[['label' => 'Akun', 'url' => route('account.dashboard')], ['label' => 'Notifikasi']]" />

    <div class="grid gap-6 lg:grid-cols-[240px_1fr]">
        @include('partials.account-nav')

        <div class="min-w-0 space-y-6">
            <h1 class="text-xl font-bold text-gray-800">Notifikasi</h1>

            @if ($notifications->isEmpty())
                <div class="card px-4 py-12 text-center text-sm text-gray-500">Belum ada notifikasi.</div>
            @else
                <ul class="space-y-3">
                    @foreach ($notifications as $n)
                        @php
                            $data = $n->data ?? [];
                            $title = $data['title'] ?? 'Notifikasi';
                            $message = $data['message'] ?? '';
                            $url = $data['url'] ?? null;
                            $isUnread = is_null($n->read_at);
                        @endphp
                        <li class="card p-4 {{ $isUnread ? 'border-brand-200 bg-brand-50/60' : '' }}">
                            <div class="flex items-start gap-3">
                                <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $isUnread ? 'bg-accent-500' : 'bg-gray-300' }}" aria-hidden="true"></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p class="font-semibold text-gray-800">{{ $title }}</p>
                                        <span class="text-xs text-gray-400">{{ $n->created_at?->diffForHumans() }}</span>
                                    </div>
                                    @if ($message)
                                        <p class="mt-1 text-sm text-gray-600">{{ $message }}</p>
                                    @endif
                                    <div class="mt-2 flex items-center gap-4">
                                        @if ($url)
                                            <a href="{{ $url }}" class="text-sm font-medium text-brand-600 hover:underline">Lihat detail</a>
                                        @endif
                                        @if ($isUnread)
                                            <form action="{{ route('account.notifications.read', $n->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="text-sm font-medium text-gray-500 hover:text-brand-700">Tandai dibaca</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <div>{{ $notifications->links() }}</div>
            @endif
        </div>
    </div>
@endsection
