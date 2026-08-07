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

    @php
        $displayName = fn ($p) => $names[$p] ?? $leadNames[$p] ?? null;
        $initial = function ($p) use ($displayName) {
            $n = $displayName($p);
            return $n ? mb_strtoupper(mb_substr(trim($n), 0, 1)) : '#';
        };
    @endphp

    <div class="grid gap-0 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm lg:grid-cols-3" style="height: 42rem">
        {{-- ===== Conversation list (WA style) ===== --}}
        <div class="flex min-h-0 flex-col border-r border-gray-200">
            <div class="border-b border-gray-100 bg-gray-50 px-4 py-3">
                <p class="font-semibold text-gray-800">Percakapan</p>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto">
                @forelse ($conversations as $c)
                    @php($label = $displayName($c->phone))
                    @php($preview = $previews[$c->phone] ?? null)
                    <a href="{{ route('admin.wachat.index', ['phone' => $c->phone]) }}"
                       class="flex items-center gap-3 border-b border-gray-50 px-3 py-2.5 {{ $phone === $c->phone ? 'bg-gray-100' : 'hover:bg-gray-50' }}">
                        <span class="flex h-11 w-11 flex-none items-center justify-center rounded-full bg-brand-100 text-base font-bold text-brand-700">{{ $initial($c->phone) }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-baseline justify-between gap-2">
                                <span class="truncate text-sm font-semibold text-gray-900">{{ $label ?? $c->phone }}</span>
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
                    <p class="px-4 py-10 text-center text-sm text-gray-400">Belum ada percakapan. Pesan WA masuk akan muncul di sini setelah webhook Wablas disetel.</p>
                @endforelse
            </div>
        </div>

        {{-- ===== Thread (WA style) ===== --}}
        <div class="flex min-h-0 flex-col lg:col-span-2">
            @if ($phone)
                {{-- Header --}}
                <div class="flex items-center gap-3 border-b border-gray-200 bg-gray-50 px-4 py-2.5">
                    <span class="flex h-10 w-10 flex-none items-center justify-center rounded-full bg-brand-100 text-base font-bold text-brand-700">{{ $initial($phone) }}</span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-gray-900">{{ $displayName($phone) ?? $phone }}</p>
                        <p class="text-xs text-gray-500">{{ $phone }} · <a class="text-brand-600 hover:underline" target="_blank" rel="noopener" href="https://wa.me/{{ $phone }}">buka di WhatsApp</a></p>
                    </div>
                </div>

                <div x-data="{
                        phone: @js($phone),
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
                            // Media first (thumbnail for images, link otherwise), caption below.
                            if (m.media_url) {
                                const link = document.createElement('a');
                                link.href = m.media_url; link.target = '_blank'; link.rel = 'noopener';
                                link.className = 'mb-1 block';
                                if (m.is_image) {
                                    const img = document.createElement('img');
                                    img.src = m.media_url; img.loading = 'lazy';
                                    img.className = 'max-h-56 w-full rounded object-cover';
                                    link.appendChild(img);
                                } else {
                                    link.className += ' rounded bg-black/5 px-2 py-1.5 text-xs font-medium text-blue-700 underline';
                                    link.textContent = '📎 ' + (m.media_name || m.media_type || 'Lampiran');
                                }
                                bubble.appendChild(link);
                            }
                            bubble.appendChild(document.createTextNode(m.message || ''));
                            const meta = document.createElement('span');
                            meta.className = 'ml-2 inline-flex translate-y-[3px] items-center gap-0.5 whitespace-nowrap text-[10px] text-gray-500/80';
                            meta.textContent = (m.time || '') + (out ? (m.sent_ok === false ? ' ✗' : ' ✓') : '');
                            if (out && m.sent_ok === false) meta.classList.add('text-red-500');
                            bubble.appendChild(meta);
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
                        /** Send the picked file (image/doc) with the composer text as caption. */
                        async sendFile(event) {
                            const file = event.target.files?.[0];
                            if (!file || this.sending) return;
                            this.sending = true; this.error = '';
                            try {
                                const body = new FormData();
                                body.append('phone', this.phone);
                                body.append('file', file);
                                if (this.text.trim()) body.append('caption', this.text.trim());

                                const res = await fetch(`{{ route('admin.wachat.media') }}`, {
                                    method: 'POST',
                                    headers: { 'X-CSRF-TOKEN': this.csrf(), Accept: 'application/json' },
                                    body,
                                });
                                const data = await res.json().catch(() => ({}));
                                if (res.ok && data.ok) { this.text = ''; await this.poll(); }
                                else { this.error = data.error || 'Gagal mengirim lampiran.'; await this.poll(); }
                            } catch (e) { this.error = 'Koneksi bermasalah.'; }
                            this.sending = false;
                            event.target.value = '';
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
                                    await this.poll();
                                }
                            } catch (e) { this.error = 'Koneksi bermasalah.'; }
                            this.sending = false;
                        },
                        init() { this.scroll(); setInterval(() => this.poll(), 6000); },
                    }"
                    class="flex min-h-0 flex-1 flex-col">

                    {{-- Messages: WA chat canvas --}}
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
                            <div class="flex {{ $out ? 'justify-end' : 'justify-start' }}">
                                <div class="relative max-w-[75%] whitespace-pre-wrap rounded-lg px-2.5 py-1.5 text-[13.5px] leading-snug shadow-sm {{ $out ? 'rounded-tr-none bg-[#d9fdd3] text-[#111b21]' : 'rounded-tl-none bg-white text-[#111b21]' }}">@if ($m->media_url)@if ($m->isImage())<a href="{{ $m->media_url }}" target="_blank" rel="noopener" class="mb-1 block"><img src="{{ $m->media_url }}" alt="{{ $m->media_name }}" loading="lazy" class="max-h-56 w-full rounded object-cover"></a>@else<a href="{{ \Illuminate\Support\Str::startsWith($m->media_url, ['http://', 'https://']) ? $m->media_url : '#' }}" @if (\Illuminate\Support\Str::startsWith($m->media_url, ['http://', 'https://'])) target="_blank" rel="noopener" @endif class="mb-1 block rounded bg-black/5 px-2 py-1.5 text-xs font-medium text-blue-700 underline">📎 {{ $m->media_name ?? 'Lampiran' }}</a>@endif @endif{{ $m->message }}<span class="ml-2 inline-flex translate-y-[3px] items-center gap-0.5 whitespace-nowrap text-[10px] {{ $out && $m->sent_ok === false ? 'text-red-500' : 'text-gray-500/80' }}">{{ $m->created_at?->format('H:i') }}{{ $out ? ($m->sent_ok === false ? ' ✗' : ' ✓') : '' }}</span></div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Composer (WA style) --}}
                    <div class="border-t border-gray-200 bg-gray-50 p-2.5">
                        <p x-show="error" x-cloak class="mb-1.5 px-1 text-xs text-red-600" x-text="error"></p>
                        <form @submit.prevent="send()" class="flex items-end gap-2">
                            {{-- Attachment: Wablas fetches media by URL, so the file is
                                 uploaded to the public disk first (see controller). --}}
                            <input type="file" x-ref="file" class="hidden" @change="sendFile($event)"
                                   accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.zip">
                            <button type="button" @click="$refs.file.click()" :disabled="sending"
                                    class="flex h-11 w-11 flex-none items-center justify-center rounded-full text-gray-500 transition hover:bg-gray-200 disabled:opacity-50"
                                    aria-label="Lampirkan file" title="Lampirkan foto / dokumen">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13"/></svg>
                            </button>
                            <textarea x-model="text" @keydown.enter.prevent="send()" rows="1" placeholder="Ketik pesan"
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
                    <svg class="h-16 w-16 text-gray-300" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163a11.867 11.867 0 0 1-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 0 1 8.413 3.488 11.824 11.824 0 0 1 3.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 0 1-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 0 0 1.51 5.26l-.999 3.648 3.978-1.207z"/></svg>
                    <p class="text-sm text-gray-500">Pilih percakapan di kiri untuk mulai membalas.</p>
                </div>
            @endif
        </div>
    </div>
@endsection
