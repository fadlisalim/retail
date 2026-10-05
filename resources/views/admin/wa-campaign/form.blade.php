@extends('layouts.admin')

@section('title', $campaign->exists ? 'Edit Campaign' : 'Buat Campaign')

@section('content')
    @php
        $seg = $campaign->segment ?? [];
        $productsJs = $products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'image' => $p->main_image_path ? asset('storage/'.$p->main_image_path) : null, 'url' => route('products.show', $p->slug), 'price' => rupiah($p->sale_price ?: $p->price)])->values();
        $templatesJs = $templates->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'body' => $t->body])->values();
    @endphp

    <x-admin.page-header :title="$campaign->exists ? 'Edit Campaign' : 'Buat Campaign'" subtitle="Pilih penerima, tulis pesan, cek preview, kirim tes, lalu mulai / jadwalkan" />
    @include('admin.wa-campaign._nav')

    @if ($eligibleTotal === 0)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">Belum ada kontak yang memberi izin promo. Campaign tetap bisa disimpan sebagai draf, tapi tidak akan punya penerima. Catat izin di tab <a href="{{ route('admin.wa-campaign.contacts') }}" class="underline">Kontak &amp; Izin</a>.</div>
    @endif

    <form method="POST" action="{{ $campaign->exists ? route('admin.wa-campaign.update', $campaign) : route('admin.wa-campaign.store') }}" enctype="multipart/form-data"
          x-data="waCampaignForm({{ \Illuminate\Support\Js::from([
              'message' => old('message', $campaign->message ?? ''),
              'productId' => old('product_id', $campaign->product_id),
              'linkUrl' => old('link_url', $campaign->link_url ?? ''),
              'utm' => old('utm_campaign', $campaign->utm_campaign ?? ''),
              'name' => old('name', $campaign->name ?? ''),
              'footer' => $footer,
              'products' => $productsJs,
              'templates' => $templatesJs,
              'audienceUrl' => route('admin.wa-campaign.audience'),
              'testUrl' => route('admin.wa-campaign.test'),
              'existingImage' => $campaign->image_path ? asset('storage/'.$campaign->image_path) : null,
              'csrf' => csrf_token(),
          ]) }})" class="grid gap-6 lg:grid-cols-[1fr_380px]">
        @csrf
        @if ($campaign->exists) @method('PUT') @endif

        <div class="space-y-6">
            {{-- 1. Penerima --}}
            <div class="card p-5">
                <h2 class="mb-1 font-semibold text-gray-900">1. Penerima</h2>
                <p class="mb-4 text-xs text-gray-500">Hanya kontak dengan <strong>izin promo</strong> yang bisa jadi penerima ({{ number_format($eligibleTotal) }} kontak). Filter di bawah mempersempit dari situ. Kosongkan semua = semua kontak ber-izin.</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="input-label">Tag</label>
                        <div class="flex flex-wrap gap-2">
                            @forelse ($tags as $tag)
                                <label class="flex items-center gap-1 rounded-md border border-gray-200 px-2 py-1 text-xs"><input type="checkbox" name="tags[]" value="{{ $tag }}" @checked(in_array($tag, old('tags', $seg['tags'] ?? []))) @change="countAudience()" class="rounded"> {{ $tag }}</label>
                            @empty
                                <span class="text-xs text-gray-400">Belum ada tag — beri tag kontak di halaman Kontak atau lewat import CSV.</span>
                            @endforelse
                        </div>
                    </div>
                    <div>
                        <label class="input-label">Minat (kategori yang pernah dibeli)</label>
                        <select name="interests[]" multiple size="5" class="form-select text-xs" @change="countAudience()">
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->slug }}" @selected(in_array($cat->slug, old('interests', $seg['interests'] ?? [])))>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-[11px] text-gray-400">Ctrl/⌘ + klik untuk beberapa kategori.</p>
                    </div>
                    <div>
                        <label class="input-label">Riwayat pembelian</label>
                        <select name="purchase" class="form-select" @change="countAudience()">
                            <option value="any" @selected(old('purchase', $seg['purchase'] ?? 'any') === 'any')>Semua</option>
                            <option value="buyers" @selected(old('purchase', $seg['purchase'] ?? '') === 'buyers')>Pernah membeli (lunas)</option>
                            <option value="never" @selected(old('purchase', $seg['purchase'] ?? '') === 'never')>Belum pernah membeli</option>
                        </select>
                    </div>
                    <div>
                        <label class="input-label">Pesanan terakhir dalam … hari</label>
                        <input type="number" name="last_order_days" min="1" max="3650" value="{{ old('last_order_days', $seg['last_order_days'] ?? '') }}" placeholder="mis. 180" class="form-input" @change="countAudience()">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="input-label">Sumber kontak</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($sources as $key => $label)
                                <label class="flex items-center gap-1 rounded-md border border-gray-200 px-2 py-1 text-xs"><input type="checkbox" name="sources[]" value="{{ $key }}" @checked(in_array($key, old('sources', $seg['sources'] ?? []))) @change="countAudience()" class="rounded"> {{ $label }}</label>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="mt-4 rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-800">
                    <span x-show="audience === null">Klik filter untuk menghitung penerima.</span>
                    <span x-show="audience !== null" x-cloak><strong x-text="audience"></strong> penerima dari <span x-text="eligibleTotal"></span> kontak ber-izin. <span class="text-xs text-brand-600" x-text="sample.length ? 'Contoh: ' + sample.join(', ') : ''"></span></span>
                    <span class="ml-2 text-xs text-brand-600" x-show="counting">menghitung…</span>
                </div>
            </div>

            {{-- 2. Pesan --}}
            <div class="card p-5">
                <h2 class="mb-1 font-semibold text-gray-900">2. Pesan</h2>
                <div class="mb-3 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="name" class="input-label">Nama campaign <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="name" x-model="name" required class="form-input" placeholder="Promo PJU Oktober">
                        @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="input-label">Pakai template</label>
                        <select class="form-select" @change="applyTemplate($event.target.value)">
                            <option value="">— pilih template —</option>
                            <template x-for="t in templates" :key="t.id"><option :value="t.id" x-text="t.name"></option></template>
                        </select>
                    </div>
                </div>
                <label for="message" class="input-label">Isi pesan <span class="text-red-500">*</span></label>
                <textarea name="message" id="message" rows="7" x-model="message" required class="form-textarea" placeholder="Halo {nama}, ada promo panel surya bekas proyek Rp 850.000/panel…"></textarea>
                <p class="mt-1 text-xs text-gray-500">Placeholder: <code>{nama}</code> (nama depan), <code>{nama_lengkap}</code>, <code>{link}</code>, <code>{produk}</code>, <code>{harga}</code>. Link produk otomatis ditambah di akhir bila <code>{link}</code> tidak dipakai. Footer <em>"{{ $footer }}"</em> ditambahkan otomatis.</p>
                @error('message')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="product_id" class="input-label">Produk dari katalog (opsional)</label>
                        <select name="product_id" id="product_id" x-model="productId" class="form-select">
                            <option value="">— tanpa produk —</option>
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-400">Foto utama produk dipakai sebagai gambar (bila tidak upload) dan link produk ditambah UTM.</p>
                    </div>
                    <div>
                        <label for="link_url" class="input-label">Link lain (opsional)</label>
                        <input type="url" name="link_url" id="link_url" x-model="linkUrl" class="form-input" placeholder="https://energi.click/barang-clearance">
                        <p class="mt-1 text-xs text-gray-400">UTM: utm_source=whatsapp, utm_medium=campaign, utm_campaign=<span x-text="utmSlug()"></span></p>
                    </div>
                    <div>
                        <label for="utm_campaign" class="input-label">utm_campaign</label>
                        <input type="text" name="utm_campaign" id="utm_campaign" x-model="utm" class="form-input" placeholder="otomatis dari nama">
                    </div>
                    <div>
                        <label for="image" class="input-label">Upload gambar (opsional, JPG/PNG/WebP ≤ 4 MB)</label>
                        <input type="file" name="image" id="image" accept="image/*" class="form-input" @change="previewImage($event)">
                        @if ($campaign->image_path)
                            <label class="mt-1 flex items-center gap-1 text-xs text-gray-500"><input type="checkbox" name="remove_image" value="1" class="rounded"> hapus gambar saat ini</label>
                        @endif
                    </div>
                </div>
                <label class="mt-4 flex items-center gap-2 text-sm text-gray-600"><input type="checkbox" name="save_template" value="1" class="rounded" x-model="saveTemplate"> Simpan pesan ini sebagai template</label>
                <input type="text" name="template_name" x-show="saveTemplate" x-cloak class="form-input mt-2" placeholder="Nama template">
            </div>

            {{-- 3. Jadwal --}}
            <div class="card p-5">
                <h2 class="mb-1 font-semibold text-gray-900">3. Mulai / jadwal</h2>
                <p class="mb-3 text-xs text-gray-500">Pengiriman selalu bertahap mengikuti jam kirim, batas per menit/hari, dan aturan 1 promo per kontak per 7 hari di Pengaturan. Tidak ada jaminan bebas blokir.</p>
                <label for="scheduled_at" class="input-label">Jadwalkan (kosongkan = mulai sekarang)</label>
                <input type="datetime-local" name="scheduled_at" id="scheduled_at" value="{{ old('scheduled_at') }}" class="form-input sm:w-64">
                <p class="mt-1 text-xs text-gray-400">Zona waktu {{ $timezone }}.</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="submit" name="action" value="draft" class="btn-outline">Simpan draf</button>
                    <button type="submit" name="action" value="start" class="btn-primary" onclick="return confirm('Mulai campaign? Penerima akan dibekukan ke antrean dan dikirim bertahap.')">Simpan &amp; Mulai / Jadwalkan</button>
                </div>
            </div>
        </div>

        {{-- Preview WhatsApp + kirim tes --}}
        <div class="space-y-4">
            <div class="card p-4">
                <h2 class="mb-2 font-semibold text-gray-900">Preview WhatsApp</h2>
                <div class="rounded-xl bg-[#e5ddd5] p-3">
                    <div class="max-w-[300px] rounded-lg bg-white p-2 text-[13px] leading-snug text-gray-800 shadow">
                        <template x-if="imageUrl()"><img :src="imageUrl()" class="mb-2 w-full rounded object-cover" alt=""></template>
                        <p class="whitespace-pre-wrap" x-text="rendered()"></p>
                        <p class="mt-1 text-right text-[10px] text-gray-400">09:41 ✓✓</p>
                    </div>
                </div>
                <p class="mt-2 text-[11px] text-gray-400">Nama contoh "Budi". Link pada pesan nyata memakai UTM.</p>
            </div>
            <div class="card p-4">
                <h2 class="mb-1 font-semibold text-gray-900">Kirim tes</h2>
                @if ($testPhone)
                    <p class="mb-2 text-xs text-gray-500">Ke nomor tes <strong>{{ $testPhone }}</strong> (Pengaturan).{{ $mock ? ' MODE MOCK: tidak benar-benar terkirim.' : '' }}</p>
                    <button type="button" class="btn-outline w-full" @click="sendTest()" :disabled="testing"><span x-show="!testing">Kirim pesan tes</span><span x-show="testing">Mengirim…</span></button>
                    <p class="mt-2 text-xs" :class="testOk ? 'text-green-700' : 'text-red-600'" x-text="testMsg" x-show="testMsg" x-cloak></p>
                @else
                    <p class="text-xs text-amber-700">Isi "Nomor tes" di <a href="{{ route('admin.wa-campaign.settings') }}" class="underline">Pengaturan</a> untuk bisa kirim tes.</p>
                @endif
            </div>
        </div>
    </form>

    @push('scripts')
    <script>
        function waCampaignForm(cfg) {
            return {
                message: cfg.message, productId: cfg.productId ? String(cfg.productId) : '', linkUrl: cfg.linkUrl, utm: cfg.utm, name: cfg.name,
                products: cfg.products, templates: cfg.templates, uploaded: null, saveTemplate: false,
                audience: null, eligibleTotal: 0, sample: [], counting: false, testing: false, testMsg: '', testOk: false,
                init() { this.countAudience(); },
                product() { return this.products.find(p => String(p.id) === String(this.productId)) || null; },
                utmSlug() { return (this.utm || this.name || 'campaign').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''); },
                link() {
                    const base = this.linkUrl || (this.product() ? this.product().url : '');
                    if (!base) return '';
                    return base + (base.includes('?') ? '&' : '?') + 'utm_source=whatsapp&utm_medium=campaign&utm_campaign=' + this.utmSlug();
                },
                imageUrl() { return this.uploaded || cfg.existingImage || (this.product() ? this.product().image : null); },
                rendered() {
                    const p = this.product(); const link = this.link();
                    let t = (this.message || '').replace(/\{nama\}/g, 'Budi').replace(/\{nama_lengkap\}/g, 'Budi Santoso').replace(/\{link\}/g, link).replace(/\{produk\}/g, p ? p.name : '').replace(/\{harga\}/g, p ? p.price : '').trim();
                    if (link && !(this.message || '').includes('{link}')) t += '\n\n' + link;
                    if (cfg.footer && !t.toLowerCase().includes('stop')) t += '\n\n' + cfg.footer;
                    return t;
                },
                applyTemplate(id) { const t = this.templates.find(x => String(x.id) === String(id)); if (t) this.message = t.body; },
                previewImage(e) { const f = e.target.files && e.target.files[0]; this.uploaded = f ? URL.createObjectURL(f) : null; },
                async countAudience() {
                    this.counting = true;
                    try {
                        const fd = new FormData(this.$el);
                        const res = await fetch(cfg.audienceUrl, { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': cfg.csrf, Accept: 'application/json' } });
                        const d = await res.json();
                        this.audience = d.count; this.eligibleTotal = d.eligible_total; this.sample = d.sample || [];
                    } catch (e) { this.audience = null; } finally { this.counting = false; }
                },
                async sendTest() {
                    this.testing = true; this.testMsg = '';
                    try {
                        const fd = new FormData(this.$el); fd.delete('image');
                        const res = await fetch(cfg.testUrl, { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': cfg.csrf, Accept: 'application/json' } });
                        const d = await res.json().catch(() => ({}));
                        this.testOk = res.ok; this.testMsg = d.message || (res.ok ? 'Terkirim.' : 'Gagal.');
                    } catch (e) { this.testOk = false; this.testMsg = 'Gagal menghubungi server.'; } finally { this.testing = false; }
                },
            };
        }
    </script>
    @endpush
@endsection
