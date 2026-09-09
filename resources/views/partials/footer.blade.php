<footer class="border-t border-gray-200 bg-white">
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
        <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-4">
            <div>
                <div class="mb-3 flex items-center gap-2">
                    <x-logo class="h-9 w-9 shrink-0 text-brand-700" />
                    <x-wordmark class="text-lg font-extrabold text-brand-700" />
                </div>
                <p class="text-sm text-gray-500">{{ $siteSettings->company()['legal_name'] }} — {{ config('rekasurya.company.tagline') }}.</p>
                <p class="mt-3 text-sm text-gray-500">{{ $siteSettings->company()['address'] }}</p>
                <p class="mt-1 text-sm text-gray-500">{{ $siteSettings->company()['email'] }} • {{ $siteSettings->company()['phone'] }}</p>
            </div>
            <div>
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Belanja</h3>
                <ul class="space-y-2 text-sm text-gray-500">
                    <li><a href="{{ route('products.index') }}" class="hover:text-brand-700">Semua Produk</a></li>
                    <li><a href="{{ route('brands.index') }}" class="hover:text-brand-700">Brand</a></li>
                    <li><a href="{{ route('promo') }}" class="hover:text-brand-700">Promo</a></li>
                    <li><a href="{{ route('products.new') }}" class="hover:text-brand-700">Produk Baru</a></li>
                    <li><a href="{{ route('clearance') }}" class="hover:text-brand-700">Clearance</a></li>
                </ul>
            </div>
            <div>
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Layanan</h3>
                <ul class="space-y-2 text-sm text-gray-500">
                    <li><a href="{{ route('consultation') }}" class="hover:text-brand-700">Konsultasi Gratis (Kirana)</a></li>
                    <li><a href="{{ route('quotations.create') }}" class="hover:text-brand-700">Permintaan Penawaran</a></li>
                    <li><a href="{{ route('affiliate.landing') }}" class="hover:text-brand-700">Program Afiliasi</a></li>
                    <li><a href="{{ route('documentation') }}" class="hover:text-brand-700">Dokumentasi Pengerjaan</a></li>
                    <li><a href="{{ route('articles.index') }}" class="hover:text-brand-700">Panduan Energi Surya</a></li>
                    <li><a href="{{ route('faq') }}" class="hover:text-brand-700">FAQ</a></li>
                    @foreach(['tentang-kami' => 'Tentang Rekasurya', 'kebijakan-pengiriman' => 'Kebijakan Pengiriman', 'kebijakan-retur' => 'Kebijakan Retur'] as $slug => $label)
                        <li><a href="{{ route('pages.show', $slug) }}" class="hover:text-brand-700">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-700">Newsletter</h3>
                <p class="mb-3 text-sm text-gray-500">Info produk & promo energi terbarukan.</p>
                <form action="{{ route('newsletter.subscribe') }}" method="POST" class="flex gap-2">
                    @csrf
                    <label for="newsletter" class="sr-only">Email</label>
                    <input id="newsletter" name="email" type="email" required placeholder="Email Anda" class="form-input rounded-lg">
                    <button class="btn-primary shrink-0">Ikuti</button>
                </form>
            </div>
        </div>
        <div class="mt-10 flex flex-col items-center justify-between gap-3 border-t border-gray-100 pt-6 text-sm text-gray-400 sm:flex-row">
            <p>&copy; {{ now()->year }} {{ $siteSettings->company()['legal_name'] }}. Seluruh hak cipta dilindungi.</p>
            <div class="flex gap-4">
                <a href="{{ route('pages.show', 'syarat-ketentuan') }}" class="hover:text-brand-700">Syarat &amp; Ketentuan</a>
                <a href="{{ route('pages.show', 'kebijakan-privasi') }}" class="hover:text-brand-700">Privasi</a>
            </div>
        </div>
    </div>
</footer>
