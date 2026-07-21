import Alpine from 'alpinejs';
import 'trix';
import 'trix/dist/trix.css';

// Alpine powers the lightweight interactions: mega-menu, mobile drawer,
// search autocomplete, gallery, quantity steppers, sticky purchase bar.
window.Alpine = Alpine;

/**
 * Debounced product search autocomplete.
 * Usage: x-data="search()" on the search form.
 */
Alpine.data('search', () => ({
    query: '',
    results: [],
    open: false,
    loading: false,
    timer: null,
    onInput() {
        clearTimeout(this.timer);
        if (this.query.trim().length < 2) {
            this.results = [];
            this.open = false;
            return;
        }
        this.timer = setTimeout(() => this.fetch(), 250);
    },
    async fetch() {
        this.loading = true;
        try {
            const res = await fetch(`/api/pencarian/suggest?q=${encodeURIComponent(this.query)}`, {
                headers: { Accept: 'application/json' },
            });
            this.results = res.ok ? await res.json() : [];
            this.open = this.results.length > 0;
        } catch (e) {
            this.results = [];
        } finally {
            this.loading = false;
        }
    },
}));

/**
 * International phone field: a country-code picker + local number, combined into
 * a hidden input as digits-only international format (e.g. 628123…). A leading 0
 * on the local part is dropped (Indonesian 08… → 62…). Wablas needs 62…, not 08….
 */
Alpine.data('phoneField', (value, dials) => ({
    dial: '62',
    local: '',
    full: '',
    init() {
        const sorted = [...(dials || [])].sort((a, b) => b.length - a.length);
        const v = String(value || '').replace(/[^\d]/g, '');
        if (v && !v.startsWith('0')) {
            const match = sorted.find((dc) => v.startsWith(dc));
            if (match) {
                this.dial = match;
                this.local = v.slice(match.length);
            } else {
                this.local = v;
            }
        } else {
            this.local = v; // leading 0 (or empty) → keep as local, dial stays 62
        }
        this.sync();
    },
    sync() {
        const local = this.local.replace(/\D/g, '').replace(/^0+/, '');
        this.full = local ? this.dial + local : '';
    },
}));

/**
 * Short-video uploader for the admin media panel. Captures the first frame in
 * the browser (canvas) as a poster so a thumbnail exists even without ffmpeg,
 * then posts the video + poster. The server compresses when ffmpeg is present.
 */
Alpine.data('videoUpload', (action, token) => ({
    busy: false,
    err: '',
    file: null,
    pick(e) {
        this.file = e.target.files[0] || null;
        this.err = '';
    },
    async submit() {
        if (!this.file) { this.err = 'Pilih file video dulu.'; return; }
        if (this.file.size > 20 * 1024 * 1024) { this.err = 'Ukuran video maksimal 20MB.'; return; }
        this.busy = true;
        this.err = '';
        try {
            const poster = await this.capturePoster(this.file).catch(() => null);
            const fd = new FormData();
            fd.append('_token', token);
            fd.append('video', this.file);
            if (poster) fd.append('poster', poster, 'poster.jpg');
            const res = await fetch(action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (res.ok || res.redirected) {
                window.location.reload();
                return;
            }
            this.err = res.status === 422 ? 'Format/ukuran video tidak didukung (MP4/WebM/MOV, maks 20MB).' : 'Gagal mengunggah video.';
        } catch (err) {
            this.err = 'Terjadi kesalahan jaringan. Coba lagi.';
        } finally {
            this.busy = false;
        }
    },
    /** Draw the first frame of the video to a JPEG blob for the poster. */
    capturePoster(file) {
        return new Promise((resolve, reject) => {
            const url = URL.createObjectURL(file);
            const v = document.createElement('video');
            v.preload = 'metadata';
            v.muted = true;
            v.src = url;
            v.onloadeddata = () => { try { v.currentTime = Math.min(0.5, (v.duration || 1) / 2); } catch (e) { reject(e); } };
            v.onseeked = () => {
                try {
                    const c = document.createElement('canvas');
                    c.width = v.videoWidth || 720;
                    c.height = v.videoHeight || 720;
                    c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);
                    c.toBlob((b) => { URL.revokeObjectURL(url); b ? resolve(b) : reject(new Error('no blob')); }, 'image/jpeg', 0.85);
                } catch (e) { URL.revokeObjectURL(url); reject(e); }
            };
            v.onerror = () => { URL.revokeObjectURL(url); reject(new Error('video decode error')); };
        });
    },
}));

/**
 * Image gallery with a selectable main image.
 */
Alpine.data('gallery', (main) => ({
    active: main,
    select(url) {
        this.active = url;
    },
}));

/**
 * Global cart store — powers the mini-cart drawer and the header/mobile badge.
 * "+ Keranjang" forms submit through submit()/add() via fetch, so the drawer and
 * count update without a full page reload. "Beli Sekarang" (buy_now) is left to
 * post normally so it still routes to checkout, and with JS disabled every form
 * keeps its plain action/method (progressive enhancement).
 */
Alpine.store('cart', {
    count: 0,
    open: false,
    loading: false,
    busy: false,
    items: [],
    subtotal: '',
    error: '',

    init() {
        this.count = Number(document.body?.dataset.cartCount || 0);
    },

    csrf() {
        return document.querySelector('meta[name=csrf-token]')?.content || '';
    },

    apply(data) {
        this.count = data.count ?? this.count;
        this.items = data.items ?? [];
        this.subtotal = data.subtotal_formatted ?? '';
    },

    /** Form @submit entry point. Lets "Beli Sekarang" (buy_now) post normally. */
    async submit(e) {
        const btn = e.submitter;
        if (btn && btn.name === 'buy_now') return; // proceed to checkout via normal POST
        e.preventDefault();
        const label = btn ? btn.innerHTML : null;
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="animate-pulse">Menambah…</span>';
        }
        await this.add(e.target);
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = label;
        }
    },

    async add(form) {
        this.error = '';
        try {
            const res = await fetch(form.action, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': this.csrf(), Accept: 'application/json' },
                body: new FormData(form),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok) {
                this.apply(data);
            } else {
                this.error = data.message || 'Gagal menambahkan produk ke keranjang.';
            }
        } catch (err) {
            this.error = 'Terjadi kesalahan jaringan. Coba lagi.';
        }
        this.open = true;
    },

    async openDrawer() {
        this.open = true;
        this.loading = true;
        this.error = '';
        try {
            const res = await fetch('/keranjang/mini', { headers: { Accept: 'application/json' } });
            if (res.ok) this.apply(await res.json());
        } catch (err) {
            this.error = 'Gagal memuat keranjang.';
        } finally {
            this.loading = false;
        }
    },

    /** Re-pull the drawer state from the server (after a mutation). */
    async refresh() {
        try {
            const res = await fetch('/keranjang/mini', { headers: { Accept: 'application/json' } });
            if (res.ok) this.apply(await res.json());
        } catch (err) {
            /* keep the current view on network error */
        }
    },

    /** Set a line's quantity (server enforces stock + min purchase), then refresh. */
    async updateQty(id, qty) {
        if (this.busy) return;
        qty = Math.max(1, parseInt(qty, 10) || 1);
        this.busy = true;
        this.error = '';
        try {
            const res = await fetch(`/keranjang/${id}`, {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': this.csrf(), 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ quantity: qty }),
            });
            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                this.error = data.message || 'Gagal memperbarui jumlah.';
            }
        } catch (err) {
            this.error = 'Terjadi kesalahan jaringan. Coba lagi.';
        } finally {
            // Always re-sync so the UI reflects the server's clamped value.
            await this.refresh();
            this.busy = false;
        }
    },

    /** Remove one line from the cart, then refresh the drawer. */
    async remove(id) {
        if (this.busy) return;
        this.busy = true;
        this.error = '';
        try {
            const res = await fetch(`/keranjang/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': this.csrf(), Accept: 'application/json' },
            });
            if (res.ok) {
                await this.refresh();
            } else {
                this.error = 'Gagal menghapus produk dari keranjang.';
            }
        } catch (err) {
            this.error = 'Terjadi kesalahan jaringan. Coba lagi.';
        } finally {
            this.busy = false;
        }
    },
});

/**
 * CS chat assistant — floating widget. Sends the message + prior turns to the
 * Claude-backed endpoint, which grounds answers in the product catalogue and can
 * return related product cards. Config (endpoint, brand, welcome) is passed in
 * from the blade partial.
 */
Alpine.data('csChat', (config = {}) => ({
    open: false,
    loading: false,
    input: '',
    messages: [],
    endpoint: config.endpoint || '/api/asisten/tanya',
    historyEndpoint: config.history || '/api/asisten/riwayat',
    welcomes: config.welcomes || [config.welcome || 'Halo Kak! 👋 Ada yang bisa aku bantu seputar produk kami?'],
    sessionId: '',

    init() {
        this.sessionId = this.resolveSession();
        this.restore();
    },

    /**
     * Reload this browser's previous conversation from the server (keyed by the
     * persistent session id) so a refresh doesn't wipe the chat. Falls back to
     * a random greeting when there's no history.
     */
    async restore() {
        if (this.sessionId) {
            try {
                const res = await fetch(`${this.historyEndpoint}?session_id=${encodeURIComponent(this.sessionId)}`, {
                    headers: { Accept: 'application/json' },
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok && Array.isArray(data.messages) && data.messages.length) {
                    this.messages = data.messages.map((m) => ({
                        role: m.role,
                        content: m.content,
                        products: m.products || [],
                        whatsapp: '',
                    }));
                    this.scrollSoon();
                    return;
                }
            } catch (err) {
                /* fall through to the greeting */
            }
        }
        // Random greeting so the widget feels alive on every first visit.
        const welcome = this.welcomes[Math.floor(Math.random() * this.welcomes.length)];
        this.messages.push({ role: 'assistant', content: welcome, products: [], welcome: true });
    },

    /** Stable per-browser id so the admin can group a conversation's turns. */
    resolveSession() {
        try {
            let id = localStorage.getItem('cs_sid');
            if (!id) {
                id = (crypto.randomUUID?.() || String(Date.now()) + Math.random().toString(36).slice(2)).replace(/[^A-Za-z0-9_-]/g, '');
                localStorage.setItem('cs_sid', id);
            }
            return id.slice(0, 64);
        } catch (e) {
            return '';
        }
    },

    csrf() {
        return document.querySelector('meta[name=csrf-token]')?.content || '';
    },

    toggle() {
        this.open = !this.open;
        if (this.open) this.scrollSoon();
    },

    scrollSoon() {
        this.$nextTick(() => {
            const box = this.$refs.log;
            if (box) box.scrollTop = box.scrollHeight;
        });
    },

    /**
     * Render a reply's light Markdown safely: escape HTML first, then convert a
     * small whitelist (**bold**, `- ` bullets, `---` divider, line breaks). AI
     * output isn't trusted HTML, so everything is escaped before formatting.
     */
    render(text) {
        let s = String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        // Links → clickable, via placeholders so later passes don't touch them.
        // Only http(s) URLs are linkified (never javascript:), and the anchor
        // HTML we inject is ours, so this stays XSS-safe after the escape above.
        const links = [];
        const stash = (url, label) => {
            links.push('<a href="' + url + '" target="_blank" rel="noopener" class="font-medium text-brand-600 underline">' + label + '</a>');
            return 'L' + (links.length - 1) + '';
        };
        s = s.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, (m, label, url) => stash(url, label));
        s = s.replace(/(https?:\/\/[^\s<]+)/g, (m, url) => stash(url, url));

        s = s.replace(/\*\*([^*\n]+?)\*\*/g, '<strong>$1</strong>');

        let html = '';
        let inList = false;
        for (const raw of s.split('\n')) {
            const line = raw.trimEnd();
            const bullet = line.match(/^\s*[-*]\s+(.*)$/);
            if (bullet) {
                if (!inList) { html += '<ul class="my-1 list-disc space-y-0.5 pl-4">'; inList = true; }
                html += '<li>' + bullet[1] + '</li>';
                continue;
            }
            if (inList) { html += '</ul>'; inList = false; }
            if (/^\s*-{3,}\s*$/.test(line)) { html += '<hr class="my-2 border-gray-200">'; continue; }
            html += line === '' ? '<br>' : line + '<br>';
        }
        if (inList) html += '</ul>';

        return html.replace(/L(\d+)/g, (m, i) => links[+i]);
    },

    /**
     * Prior turns as [{role, content}]. Skip greeting bubbles and cap at the
     * last 10 — the server only feeds the model the recent turns anyway, and
     * sending a long restored history would trip the request validation.
     */
    history() {
        return this.messages
            .filter((m) => m.content && !m.welcome)
            .map((m) => ({ role: m.role, content: m.content }))
            .slice(-10);
    },

    async send() {
        const text = this.input.trim();
        if (!text || this.loading) return;

        const history = this.history();
        this.messages.push({ role: 'user', content: text, products: [] });
        this.input = '';
        this.loading = true;
        this.scrollSoon();

        try {
            const res = await fetch(this.endpoint, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': this.csrf(), 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ message: text, history, session_id: this.sessionId }),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.reply) {
                this.messages.push({ role: 'assistant', content: data.reply, products: (data.products || []).slice(0, 6), whatsapp: data.escalate ? (data.whatsapp || '') : '' });
            } else {
                this.messages.push({ role: 'assistant', content: 'Maaf, terjadi kendala. Coba lagi sebentar ya.', products: [], whatsapp: '' });
            }
        } catch (err) {
            this.messages.push({ role: 'assistant', content: 'Koneksi bermasalah. Coba lagi ya.', products: [], whatsapp: '' });
        } finally {
            this.loading = false;
            this.scrollSoon();
        }
    },
}));

Alpine.start();
