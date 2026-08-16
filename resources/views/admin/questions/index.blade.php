@extends('layouts.admin')

@section('title', 'Tanya Jawab Produk')

@section('content')
    <x-admin.page-header title="Tanya Jawab Produk"
        :subtitle="$unansweredCount.' pertanyaan belum dijawab — jawaban terkirim juga ke WA penanya'" />

    <div class="mb-4 flex flex-wrap gap-2">
        @foreach (['unanswered' => 'Belum Dijawab', 'answered' => 'Sudah Dijawab', 'hidden' => 'Disembunyikan', 'all' => 'Semua'] as $val => $lbl)
            <a href="{{ route('admin.questions.index', ['filter' => $val]) }}"
               class="rounded-full px-4 py-1.5 text-sm font-medium transition {{ $filter === $val ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                {{ $lbl }}@if ($val === 'unanswered' && $unansweredCount) ({{ $unansweredCount }})@endif
            </a>
        @endforeach
    </div>

    <div class="space-y-4">
        @forelse ($questions as $q)
            <div class="card p-5" x-data="{ reply: {{ $q->answers->isEmpty() ? 'true' : 'false' }} }">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <a href="{{ route('products.show', $q->product->slug) }}" target="_blank"
                           class="text-sm font-semibold text-brand-700 hover:underline">{{ $q->product->name }}</a>
                        <p class="mt-1 text-sm font-medium text-gray-800">T: {{ $q->question }}</p>
                        {{-- Nomor utuh hanya di admin; publik melihat versi sensor. --}}
                        <p class="mt-0.5 text-xs text-gray-400">
                            {{ $q->name }} · {{ $q->phone ?: 'tanpa WA' }} · {{ $q->created_at?->format('d M Y H:i') }}
                            @unless ($q->is_visible)<span class="badge bg-gray-200 text-gray-600">Disembunyikan</span>@endunless
                        </p>
                    </div>
                    <div class="flex flex-none gap-1.5">
                        <button type="button" @click="reply = !reply"
                                class="rounded-md bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700 hover:bg-brand-100">Jawab</button>
                        <form method="POST" action="{{ route('admin.questions.visibility', $q) }}">
                            @csrf
                            <button class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-200">
                                {{ $q->is_visible ? 'Sembunyikan' : 'Tampilkan' }}
                            </button>
                        </form>
                    </div>
                </div>

                @foreach ($q->answers as $a)
                    <div class="mt-3 rounded-lg bg-gray-50 p-3">
                        <p class="text-sm text-gray-700">J: {{ $a->answer }}</p>
                        <p class="mt-0.5 text-xs text-gray-400">
                            {{ $a->created_at?->format('d M Y H:i') }}
                            @if ($a->wa_notified_at)
                                <span class="text-green-600">✓ WA terkirim {{ $a->wa_notified_at->format('d/m H:i') }}</span>
                            @elseif ($q->phone)
                                <span class="text-amber-600">WA belum terkirim</span>
                            @endif
                        </p>
                    </div>
                @endforeach

                <form x-show="reply" x-cloak method="POST" action="{{ route('admin.questions.answer', $q) }}"
                      class="mt-3 flex flex-col gap-2 sm:flex-row">
                    @csrf
                    <input name="answer" required minlength="2" maxlength="2000" placeholder="Tulis jawaban…"
                           class="form-input flex-1">
                    <button class="btn-primary">Kirim Jawaban{{ $q->phone ? ' + WA' : '' }}</button>
                </form>
            </div>
        @empty
            <div class="card p-8 text-center text-gray-400">Tidak ada pertanyaan pada filter ini.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $questions->links() }}</div>
@endsection
