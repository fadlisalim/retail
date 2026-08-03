@extends('layouts.admin')

@section('title', 'Chat Toko')

@section('content')
    <x-admin.page-header title="Chat Toko" subtitle="Balas chat pelanggan dari website (fitur Tanya Produk) — bukan chat AI" />

    @php
        use App\Http\Controllers\Admin\SiteChatController;
        $displayName = fn ($sid) => $userNames[$sid] ?? $leads[$sid]?->name ?? null;
        $initial = function ($sid) use ($displayName) {
            $n = $displayName($sid);
            return $n ? mb_strtoupper(mb_substr(trim($n), 0, 1)) : '#';
        };
    @endphp

    <div class="grid gap-0 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm lg:grid-cols-3" style="height: 42rem">
        {{-- ===== Conversation list ===== --}}
        <div class="flex min-h-0 flex-col border-r border-gray-200">
            <div class="border-b border-gray-100 bg-gray-50 px-4 py-3">
                <p class="font-semibold text-gray-800">Percakapan</p>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto">
                @forelse ($conversations as $c)
                    @php($label = $displayName($c->session_id) ?? SiteChatController::guestLabel($c->session_id))
                    @php($preview = $previews[$c->session_id] ?? null)
                    <a href="{{ route('admin.sitechat.index', ['sesi' => $c->session_id]) }}"
                       class="flex items-center gap-3 border-b border-gray-50 px-3 py-2.5 {{ $session === $c->session_id ? 'bg-gray-100' : 'hover:bg-gray-50' }}">
                        <span class="flex h-11 w-11 flex-none items-center justify-center rounded-full bg-brand-100 text-base font-bold text-brand-700">{{ $initial($c->session_id) }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-baseline justify-between gap-2">
                                <span class="truncate text-sm font-semibold text-gray-900">{{ $label }}</span>
                                <span class="flex-none text-[11px] {{ (int) $c->unread > 0 ? 'font-semibold text-green-600' : 'text-gray-400' }}">{{ \Illuminate\Support\Carbon::parse($c->last_at)->isToday() ? \Illuminate\Support\Carbon::parse($c->last_at)->format('H:i') : \Illuminate\Support\Carbon::parse($c->last_at)->format('d/m/y') }}</span>
                            </span>
                            <span class="flex items-center justify-between gap-2">
                                <span class="truncate text-xs text-gray-500">
                                    @if ($preview?->direction === 'out')<span class="text-gray-400">Anda: </span>@endif{{ \Illuminate\Support\Str::limit($preview?->message ?? '', 46) }}
                                </span>
                                @if ((int) $c->unread > 0)
                                    <span class="inline-flex h-5 min-w-[1.25rem] flex-none items-center justify-center rounded-full bg-green-500 px-1.5 text-[11px] font-bold leading-none text-white">{{ $c->unread }}</span>
                                @endif
                            </span>
                        </span>
                    </a>
                @empty
                    <p class="px-4 py-10 text-center text-sm text-gray-400">Belum ada percakapan. Chat dari tombol "Tanya Produk Ini" di website akan muncul di sini.</p>
                @endforelse
            </div>
        </div>

        {{-- ===== Thread ===== --}}
        <div class="flex min-h-0 flex-col lg:col-span-2">
            @if ($session)
                @php($lead = $leads[$session] ?? null)
                <div class="flex items-center gap-3 border-b border-gray-200 bg-gray-50 px-4 py-2.5">
                    <span class="flex h-10 w-10 flex-none items-center justify-center rounded-full bg-brand-100 text-base font-bold text-brand-700">{{ $initial($session) }}</span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-gray-900">{{ $displayName($session) ?? SiteChatController::guestLabel($session) }}</p>
                        <p class="text-xs text-gray-500">
                            @if ($lead?->phone)
                                {{ $lead->phone }} ·
                                <a class="text-green-600 hover:underline" target="_blank" rel="noopener" href="https://wa.me/{{ $lead->phone }}">WhatsApp</a> ·
                                <a class="text-brand-600 hover:underline" href="{{ route('admin.wachat.index', ['phone' => $lead->phone]) }}">WA Chat</a>
                            @else
                                sesi {{ \Illuminate\Support\Str::limit($session, 10, '…') }}
                            @endif
                        </p>
                    </div>
                </div>

                <div x-data="{
                        sesi: @js($session),
                        lastId: {{ (int) ($thread->last()?->id ?? 0) }},
                        lastDate: @js($thread->last()?->created_at?->format('d/m/Y') ?? ''),
                        sending: false,
                        text: '',
                        error: '',
                        csrf() { return document.querySelector('meta[name=csrf-token]')?.content || ''; },
                        scroll() { this.$nextTick(() => { const b = this.$refs.box; if (b) b.scrollTop = b.scrollHeight; }); },
                        daySep(label) {
                            const w = document.createElement('div');
                            w.className = 'flex justify-center';
                            const p = document.createElement('span');
                            p.className = 'rounded-lg bg-white/90 px-3 py-1 text-[11px] font-medium text-gray-500 shadow-sm';
                            p.textContent = label;
                            w.appendChild(p);
                            this.$refs.box.appendChild(w);
                        },
                        append(m) {
                            if (m.date && m.date !== this.lastDate) { this.daySep(m.date); this.lastDate = m.date; }
                            const out = m.direction === 'out';
                            const wrap = document.createElement('div');
                            wrap.className = out ? 'flex justify-end' : 'flex justify-start';
                            const bubble = document.createElement('div');
                            bubble.className = 'relative max-w-[75%] whitespace-pre-wrap rounded-lg px-2.5 py-1.5 text-[13.5px] leading-snug shadow-sm ' + (out ? 'bg-[#d9fdd3] text-[#111b21] rounded-tr-none' : 'bg-white text-[#111b21] rounded-tl-none');
                            if (m.product) {
                                const card = document.createElement('a');
                                card.href = m.product.url;
                                card.target = '_blank';
                                card.className = 'mb-1.5 flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 p-1.5';
                                const img = document.createElement('img');
                                img.src = m.product.image; img.className = 'h-10 w-10 rounded object-cover';
                                const info = document.createElement('div');
                                const nm = document.createElement('p'); nm.className = 'text-xs font-semibold'; nm.textContent = m.product.name;
                                const pr = document.createElement('p'); pr.className = 'text-xs font-bold text-brand-700'; pr.textContent = m.product.price;
                                info.appendChild(nm); info.appendChild(pr);
                                card.appendChild(img); card.appendChild(info);
                                bubble.appendChild(card);
                            }
                            bubble.appendChild(document.createTextNode(m.message));
                            const meta = document.createElement('span');
                            meta.className = 'ml-2 inline-flex translate-y-[3px] items-center gap-0.5 whitespace-nowrap text-[10px] text-gray-500/80';
                            meta.textContent = (m.time || '') + (m.notified ? ' · notif WA ✓' : '');
                            bubble.appendChild(meta);
                            wrap.appendChild(bubble);
                            this.$refs.box.appendChild(wrap);
                        },
                        async poll() {
                            try {
                                const res = await fetch(`{{ route('admin.sitechat.messages') }}?sesi=${encodeURIComponent(this.sesi)}&after_id=${this.lastId}`, { headers: { Accept: 'application/json' } });
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
                                const res = await fetch(`{{ route('admin.sitechat.send') }}`, {
                                    method: 'POST',
                                    headers: { 'X-CSRF-TOKEN': this.csrf(), 'Content-Type': 'application/json', Accept: 'application/json' },
                                    body: JSON.stringify({ sesi: this.sesi, message: msg }),
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

                    <div x-ref="box" class="flex-1 space-y-1.5 overflow-y-auto px-6 py-4"
                         style="background-color: #efeae2; background-image: radial-gradient(circle at 1px 1px, rgba(0,0,0,0.035) 1px, transparent 0); background-size: 22px 22px;">
                        @php($prevDate = null)
                        @foreach ($thread as $m)
                            @php($d = $m->created_at?->format('d/m/Y'))
                            @if ($d !== $prevDate)
                                <div class="flex justify-center py-1">
                                    <span class="rounded-lg bg-white/90 px-3 py-1 text-[11px] font-medium text-gray-500 shadow-sm">{{ $d }}</span>
                                </div>
                                @php($prevDate = $d)
                            @endif
                            @php($out = $m->direction === 'out')
                            @php($tp = $m->product_slug ? ($threadProducts[$m->product_slug] ?? null) : null)
                            <div class="flex {{ $out ? 'justify-end' : 'justify-start' }}">
                                <div class="relative max-w-[75%] whitespace-pre-wrap rounded-lg px-2.5 py-1.5 text-[13.5px] leading-snug shadow-sm {{ $out ? 'rounded-tr-none bg-[#d9fdd3] text-[#111b21]' : 'rounded-tl-none bg-white text-[#111b21]' }}">@if ($tp)<a href="{{ route('products.show', $tp->slug) }}" target="_blank" class="mb-1.5 flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 p-1.5"><img src="{{ $tp->primaryImageUrl() }}" class="h-10 w-10 rounded object-cover" alt="" /><span><span class="block text-xs font-semibold">{{ $tp->name }}</span><span class="block text-xs font-bold text-brand-700">{{ rupiah($tp->effectivePrice()) }}</span></span></a>@endif{{ $m->message }}<span class="ml-2 inline-flex translate-y-[3px] items-center gap-0.5 whitespace-nowrap text-[10px] text-gray-500/80">{{ $m->created_at?->format('H:i') }}@if ($m->notified_at) · notif WA ✓@endif</span></div>
                            </div>
                        @endforeach
                    </div>

                    <div class="border-t border-gray-200 bg-gray-50 p-2.5">
                        <p x-show="error" x-cloak class="mb-1.5 px-1 text-xs text-red-600" x-text="error"></p>
                        <form @submit.prevent="send()" class="flex items-end gap-2">
                            <textarea x-model="text" @keydown.enter.prevent="send()" rows="1" placeholder="Ketik balasan"
                                      class="max-h-28 min-h-[2.75rem] flex-1 resize-none rounded-full border border-gray-200 bg-white px-4 py-2.5 text-sm focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-400"></textarea>
                            <button type="submit" :disabled="sending || !text.trim()"
                                    class="flex h-11 w-11 flex-none items-center justify-center rounded-full bg-green-500 text-white shadow transition hover:bg-green-600 disabled:cursor-not-allowed disabled:opacity-50"
                                    aria-label="Kirim">
                                <svg class="h-5 w-5 translate-x-[1px]" fill="currentColor" viewBox="0 0 24 24"><path d="M3.4 20.4 20.85 12.9c.8-.35.8-1.45 0-1.8L3.4 3.6c-.66-.29-1.39.2-1.39.91L2 9.12c0 .5.37.93.87.99L17 12 2.87 13.88c-.5.07-.87.5-.87 1l.01 4.61c0 .71.73 1.2 1.39.91Z"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <div class="flex flex-1 flex-col items-center justify-center gap-2 p-10 text-center" style="background-color: #f0f2f5">
                    <svg class="h-16 w-16 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h8M8 14h5m-9 6 3.5-2.1A9 9 0 1 1 21 12a9 9 0 0 1-13 8.1L4 20Z" /></svg>
                    <p class="text-sm text-gray-500">Pilih percakapan di kiri untuk mulai membalas.</p>
                </div>
            @endif
        </div>
    </div>
@endsection
