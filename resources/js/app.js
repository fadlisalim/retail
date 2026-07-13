import Alpine from 'alpinejs';

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

Alpine.start();
