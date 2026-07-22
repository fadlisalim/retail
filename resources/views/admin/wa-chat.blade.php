@extends('layouts.admin')

@section('title', 'WA Chat')

@section('content')
    <x-admin.page-header title="WA Chat" subtitle="Balas WhatsApp pelanggan langsung dari web (via Wablas)" />

    @unless ($waEnabled)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
            Wablas belum aktif — set <code>WABLAS_ENABLED=true</code> &amp; <code>WABLAS_TOKEN</code> di .env server.
            Pesan masuk juga butuh webhook: setel URL <code>{{ route('webhook.wablas') }}?token=…</code> di console Wablas.
        </div>
    @endunless

    <div class="grid gap-4 lg:grid-cols-3" style="min-height: 32rem">
        {{-- Conversation list --}}
        <div class="card overflow-y-auto p-2" style="max-height: 40rem">
            @forelse ($conversations as $c)
                @php($label = $names[$c->phone] ?? $leadNames[$c->phone] ?? null)
                <a href="{{ route('admin.wachat.index', ['phone' => $c->phone]) }}"
                   class="flex items-center justify-between gap-2 rounded-lg px-3 py-2.5 text-sm {{ $phone === $c->phone ? 'bg-brand-600 text-white' : 'hover:bg-gray-50' }}">
                    <span class="min-w-0">
                        <span class="block truncate font-medium {{ $phone === $c->phone ? 'text-white' : 'text-gray-800' }}">{{ $label ?? $c->phone }}</span>
                        <span class="block text-xs {{ $phone === $c->phone ? 'text-white/70' : 'text-gray-400' }}">{{ $label ? $c->phone.' · ' : '' }}{{ \Illuminate\Support\Carbon::parse($c->last_at)->format('d/m H:i') }}</span>
                    </span>
                    @if ((int) $c->unread > 0)
                        <span class="inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-red-500 px-1.5 py-0.5 text-xs font-semibold leading-none text-white">{{ $c->unread }}</span>
                    @endif
                </a>
            @empty
                <p class="px-3 py-8 text-center text-sm text-gray-400">Belum ada percakapan. Pesan WA masuk akan muncul di sini setelah webhook Wablas disetel.</p>
            @endforelse
        </div>

        {{-- Thread --}}
        <div class="card flex flex-col lg:col-span-2" style="max-height: 40rem">
            @if ($phone)
                <div class="border-b border-gray-100 px-4 py-3">
                    <p class="font-semibold text-gray-800">{{ $names[$phone] ?? $leadNames[$phone] ?? $phone }}</p>
                    <p class="text-xs text-gray-400">{{ $phone }} · <a class="text-brand-600 underline" target="_blank" rel="noopener" href="https://wa.me/{{ $phone }}">buka di WhatsApp</a></p>
                </div>

                <div x-data="{
                        phone: @js($phone),
                        lastId: {{ (int) ($thread->last()?->id ?? 0) }},
                        sending: false,
                        text: '',
                        error: '',
                        csrf() { return document.querySelector('meta[name=csrf-token]')?.content || ''; },
                        scroll() { this.$nextTick(() => { const b = this.$refs.box; if (b) b.scrollTop = b.scrollHeight; }); },
                        append(m) {
                            const wrap = document.createElement('div');
                            wrap.className = m.direction === 'out' ? 'flex justify-end' : 'flex justify-start';
                            const bubble = document.createElement('div');
                            bubble.className = (m.direction === 'out' ? 'bg-brand-600 text-white rounded-br-sm' : 'bg-white text-gray-800 shadow-sm rounded-bl-sm') + ' max-w-[80%] whitespace-pre-wrap rounded-2xl px-3 py-2 text-sm';
                            bubble.textContent = m.message;
                            const t = document.createElement('div');
                            t.className = m.direction === 'out' ? 'mt-0.5 text-[10px] text-white/70' : 'mt-0.5 text-[10px] text-gray-400';
                            t.textContent = (m.time || '') + (m.direction === 'out' && m.sent_ok === false ? ' · gagal terkirim' : '');
                            bubble.appendChild(t);
                            wrap.appendChild(bubble);
                            this.$refs.box.appendChild(wrap);
                        },
                        async poll() {
                            try {
                                const res = await fetch(`{{ route('admin.wachat.messages') }}?phone=${encodeURIComponent(this.phone)}&after_id=${this.lastId}`, { headers: { Accept: 'application/json' } });
                                const data = await res.json().catch(() => ({}));
                                if (res.ok && Array.isArray(data.messages) && data.messages.length) {
                                    data.messages.forEach((m) => { this.append(m); this.lastId = Math.max(this.lastId, m.id); });
                                    this.scroll();
                                }
                            } catch (e) { /* retry next tick */ }
                        },
                        async send() {
                            const msg = this.text.trim();
                            if (!msg || this.sending) return;
                            this.sending = true; this.error = '';
                            try {
                                const res = await fetch(`{{ route('admin.wachat.send') }}`, {
                                    method: 'POST',
                                    headers: { 'X-CSRF-TOKEN': this.csrf(), 'Content-Type': 'application/json', Accept: 'application/json' },
                                    body: JSON.stringify({ phone: this.phone, message: msg }),
                                });
                                const data = await res.json().catch(() => ({}));
                                if (res.ok && data.ok) {
                                    this.text = '';
                                    await this.poll();
                                } else {
                                    this.error = data.error || 'Gagal mengirim pesan.';
                                }
                            } catch (e) { this.error = 'Koneksi bermasalah.'; }
                            this.sending = false;
                        },
                        init() { this.scroll(); setInterval(() => this.poll(), 6000); },
                    }"
                    class="flex min-h-0 flex-1 flex-col">
                    <div x-ref="box" class="flex-1 space-y-2 overflow-y-auto bg-gray-50 px-4 py-4">
                        @foreach ($thread as $m)
                            <div class="flex {{ $m->direction === 'out' ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-[80%] whitespace-pre-wrap rounded-2xl px-3 py-2 text-sm {{ $m->direction === 'out' ? 'rounded-br-sm bg-brand-600 text-white' : 'rounded-bl-sm bg-white text-gray-800 shadow-sm' }}">
                                    {{ $m->message }}
                                    <div class="mt-0.5 text-[10px] {{ $m->direction === 'out' ? 'text-white/70' : 'text-gray-400' }}">
                                        {{ $m->created_at?->format('d/m H:i') }}{{ $m->direction === 'out' && $m->sent_ok === false ? ' · gagal terkirim' : '' }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <form @submit.prevent="send()" class="border-t border-gray-100 p-3">
                        <p x-show="error" x-cloak class="mb-2 text-xs text-red-600" x-text="error"></p>
                        <div class="flex items-end gap-2">
                            <textarea x-model="text" @keydown.enter.prevent="send()" rows="2" placeholder="Tulis balasan…"
                                      class="form-textarea flex-1 text-sm"></textarea>
                            <button type="submit" :disabled="sending || !text.trim()" class="btn-primary disabled:cursor-not-allowed disabled:opacity-50">
                                <span x-show="!sending">Kirim</span><span x-show="sending" x-cloak>Mengirim…</span>
                            </button>
                        </div>
                    </form>
                </div>
            @else
                <div class="flex flex-1 items-center justify-center p-10 text-center text-sm text-gray-400">
                    Pilih percakapan di kiri untuk mulai membalas.
                </div>
            @endif
        </div>
    </div>
@endsection
