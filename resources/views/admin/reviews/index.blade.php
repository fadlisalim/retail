@extends('layouts.admin')

@section('title', 'Moderasi Review')

@section('content')
    <x-admin.page-header title="Review" subtitle="Moderasi ulasan produk" />

    {{-- Filters --}}
    <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
        @php
            $tabs = ['all' => 'Semua', 'reported' => 'Dilaporkan', 'hidden' => 'Disembunyikan'];
        @endphp
        @foreach ($tabs as $key => $label)
            <a href="{{ route('admin.reviews.index', array_filter(['visibility' => $key, 'rating' => request('rating')])) }}"
               class="rounded-full px-3 py-1.5 {{ $visibility === $key ? 'bg-brand-600 font-semibold text-white' : 'bg-white text-gray-600 hover:bg-gray-100' }}">
                {{ $label }}
            </a>
        @endforeach

        <form method="GET" action="{{ route('admin.reviews.index') }}" class="ml-auto flex items-center gap-2">
            <input type="hidden" name="visibility" value="{{ $visibility }}">
            <label for="rating" class="text-gray-500">Rating</label>
            <select name="rating" id="rating" class="form-select w-32" onchange="this.form.submit()">
                <option value="">Semua</option>
                @for ($r = 5; $r >= 1; $r--)
                    <option value="{{ $r }}" @selected((string) request('rating') === (string) $r)>{{ $r }} bintang</option>
                @endfor
            </select>
        </form>
    </div>

    <div class="space-y-4">
        @forelse ($reviews as $review)
            <div class="card p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-stars :rating="$review->rating" />
                            @if ($review->is_visible)
                                <span class="badge bg-green-100 text-green-700">Tampil</span>
                            @else
                                <span class="badge bg-gray-200 text-gray-600">Disembunyikan</span>
                            @endif
                            @if ($review->is_verified_purchase)<span class="badge bg-brand-100 text-brand-700">Pembelian Terverifikasi</span>@endif
                            @if ($review->reports_count > 0)<span class="badge bg-red-100 text-red-700">{{ $review->reports_count }} laporan</span>@endif
                        </div>
                        <p class="mt-2 text-sm text-gray-500">
                            <span class="font-medium text-gray-700">{{ $review->user?->name ?? 'Pengguna' }}</span>
                            pada <span class="font-medium text-gray-700">{{ $review->product?->name ?? 'Produk' }}</span>
                            · {{ $review->created_at?->format('d/m/Y') }}
                        </p>
                    </div>
                    <form method="POST" action="{{ route('admin.reviews.visibility', $review) }}">
                        @csrf
                        <button type="submit" class="btn-outline">{{ $review->is_visible ? 'Sembunyikan' : 'Tampilkan' }}</button>
                    </form>
                </div>

                @if ($review->title)<p class="mt-3 font-semibold text-gray-900">{{ $review->title }}</p>@endif
                @if ($review->comment)<p class="mt-1 text-sm text-gray-700">{{ $review->comment }}</p>@endif

                @if ($review->admin_reply)
                    <div class="mt-3 rounded-lg border-l-4 border-brand-300 bg-brand-50 p-3 text-sm">
                        <p class="mb-0.5 text-xs font-semibold uppercase text-brand-600">Balasan Admin</p>
                        {{ $review->admin_reply }}
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.reviews.reply', $review) }}" class="mt-3 flex flex-col gap-2 sm:flex-row">
                    @csrf
                    <input type="text" name="reply" maxlength="1000" value="{{ old('reply') }}"
                           placeholder="{{ $review->admin_reply ? 'Perbarui balasan…' : 'Tulis balasan…' }}" required class="form-input flex-1">
                    <button type="submit" class="btn-primary">{{ $review->admin_reply ? 'Perbarui' : 'Balas' }}</button>
                </form>
            </div>
        @empty
            <div class="card p-10 text-center text-gray-400">Tidak ada review.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $reviews->links() }}</div>
@endsection
