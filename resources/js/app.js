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
});

Alpine.start();
