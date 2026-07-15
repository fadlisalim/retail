{{-- Product media manager: gallery images, datasheet PDFs, YouTube videos.
     Each block posts to its own endpoint so items add/remove without touching
     the main product form. Requires an existing $product. --}}
<div class="mt-6 space-y-6">
    {{-- ---------- Gambar ---------- --}}
    <div class="card space-y-4 p-5">
        <h2 class="font-semibold text-gray-900">Gambar Produk</h2>

        @if ($product->images->isNotEmpty())
            @php
                $imagesData = $product->images->map(fn ($i) => [
                    'id' => $i->id,
                    'url' => asset('storage/'.$i->path),
                    'isMain' => $product->main_image_path === $i->path,
                ])->values();
            @endphp
            <div x-data="{
                items: {{ Illuminate\Support\Js::from($imagesData) }},
                dragIndex: null, dirty: false, saving: false,
                onDrop(i) {
                    if (this.dragIndex === null || this.dragIndex === i) return;
                    const moved = this.items.splice(this.dragIndex, 1)[0];
                    this.items.splice(i, 0, moved);
                    this.dragIndex = null; this.dirty = true;
                },
                submit(action, method = 'POST') {
                    const f = document.createElement('form');
                    f.method = 'POST'; f.action = action;
                    let html = '<input type=\'hidden\' name=\'_token\' value=\'{{ csrf_token() }}\'>';
                    if (method !== 'POST') html += `<input type='hidden' name='_method' value='${method}'>`;
                    f.innerHTML = html; document.body.appendChild(f); f.submit();
                },
                setMain(id) { this.submit(`{{ url('admin/produk/'.$product->id.'/gambar') }}/${id}/utama`); },
                remove(id) { if (confirm('Hapus gambar ini?')) this.submit(`{{ url('admin/produk-gambar') }}/${id}`, 'DELETE'); },
                async save() {
                    this.saving = true;
                    const body = new FormData();
                    body.append('_token', '{{ csrf_token() }}');
                    this.items.forEach(it => body.append('order[]', it.id));
                    await fetch('{{ route('admin.products.image.reorder', $product) }}', { method: 'POST', body, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    window.location.reload();
                },
            }">
                <p class="mb-2 text-xs text-gray-400">Seret gambar untuk mengubah urutan. Gambar paling depan tampil pertama di katalog.</p>
                <div class="grid grid-cols-3 gap-3 sm:grid-cols-5">
                    <template x-for="(img, i) in items" :key="img.id">
                        <div draggable="true"
                             @dragstart="dragIndex = i" @dragover.prevent @drop.prevent="onDrop(i)"
                             class="relative cursor-move overflow-hidden rounded-lg border border-gray-200"
                             :class="img.isMain && 'ring-2 ring-brand-500'">
                            <img :src="img.url" class="aspect-square w-full object-cover" draggable="false">
                            <span x-show="img.isMain" class="absolute left-1 top-1 rounded bg-brand-600 px-1.5 py-0.5 text-[10px] font-semibold text-white">Utama</span>
                            <div class="absolute inset-x-0 bottom-0 flex divide-x divide-white/20 bg-black/60 text-[11px] text-white">
                                <button type="button" x-show="!img.isMain" @click="setMain(img.id)" class="flex-1 py-1.5 hover:bg-white/15">Jadikan Utama</button>
                                <button type="button" @click="remove(img.id)" class="flex-1 py-1.5 text-red-200 hover:bg-white/15">Hapus</button>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="mt-3 flex items-center gap-2" x-show="dirty" x-cloak>
                    <button type="button" class="btn-primary" @click="save()" x-text="saving ? 'Menyimpan…' : 'Simpan Urutan'" :disabled="saving"></button>
                    <span class="text-xs text-gray-400">Urutan berubah — klik simpan.</span>
                </div>
            </div>
        @else
            <p class="text-sm text-gray-400">Belum ada gambar. Unggah minimal satu — yang pertama jadi gambar utama.</p>
        @endif

        <form action="{{ route('admin.products.image.store', $product) }}" method="POST" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3 border-t border-gray-100 pt-4">
            @csrf
            <div>
                <label class="input-label" for="images">Tambah gambar (bisa banyak)</label>
                <input id="images" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required class="text-sm">
                <p class="mt-1 text-xs text-gray-400">JPG/PNG/WEBP, maks 5 MB per file.</p>
            </div>
            <button class="btn-primary">Unggah Gambar</button>
        </form>
    </div>

    {{-- ---------- Dokumen / Datasheet ---------- --}}
    <div class="card space-y-4 p-5">
        <h2 class="font-semibold text-gray-900">Dokumen / Datasheet (PDF)</h2>

        @if ($product->documents->isNotEmpty())
            <ul class="divide-y divide-gray-100">
                @foreach ($product->documents as $doc)
                    <li class="flex items-center justify-between py-2 text-sm">
                        <a href="{{ asset('storage/'.$doc->path) }}" target="_blank" rel="noopener" class="text-brand-600 hover:underline">📄 {{ $doc->title }} <span class="text-gray-400">({{ ucfirst($doc->type) }})</span></a>
                        <form action="{{ route('admin.products.document.destroy', $doc) }}" method="POST" onsubmit="return confirm('Hapus dokumen ini?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-red-500 hover:underline">Hapus</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-sm text-gray-400">Belum ada dokumen.</p>
        @endif

        <form action="{{ route('admin.products.document.store', $product) }}" method="POST" enctype="multipart/form-data" class="grid gap-3 border-t border-gray-100 pt-4 sm:grid-cols-[1fr_180px_auto] sm:items-end">
            @csrf
            <div>
                <label class="input-label" for="doc_title">Judul dokumen</label>
                <input id="doc_title" name="title" required placeholder="mis. Datasheet Panel 550Wp" class="form-input">
            </div>
            <div>
                <label class="input-label" for="doc_type">Jenis</label>
                <select id="doc_type" name="type" class="form-select">
                    <option value="datasheet">Datasheet</option>
                    <option value="manual">Manual</option>
                    <option value="sertifikat">Sertifikat</option>
                    <option value="garansi">Garansi</option>
                    <option value="brosur">Brosur</option>
                </select>
            </div>
            <div>
                <input type="file" name="document" accept="application/pdf" required class="mb-2 block text-sm">
                <button class="btn-primary w-full">Unggah PDF</button>
            </div>
        </form>
    </div>

    {{-- ---------- Video YouTube ---------- --}}
    <div class="card space-y-4 p-5">
        <h2 class="font-semibold text-gray-900">Video (YouTube)</h2>

        @if ($product->videos->isNotEmpty())
            <ul class="divide-y divide-gray-100">
                @foreach ($product->videos as $vid)
                    <li class="flex items-center justify-between gap-3 py-2 text-sm">
                        <a href="{{ $vid->url }}" target="_blank" rel="noopener" class="min-w-0 truncate text-brand-600 hover:underline">▶ {{ $vid->title }} <span class="text-gray-400">— {{ $vid->url }}</span></a>
                        <form action="{{ route('admin.products.video.destroy', $vid) }}" method="POST" onsubmit="return confirm('Hapus video ini?')">
                            @csrf @method('DELETE')
                            <button class="shrink-0 text-xs text-red-500 hover:underline">Hapus</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-sm text-gray-400">Belum ada video.</p>
        @endif

        <form action="{{ route('admin.products.video.store', $product) }}" method="POST" class="grid gap-3 border-t border-gray-100 pt-4 sm:grid-cols-[220px_1fr_auto] sm:items-end">
            @csrf
            <div>
                <label class="input-label" for="vid_title">Judul (opsional)</label>
                <input id="vid_title" name="title" placeholder="mis. Unboxing & Instalasi" class="form-input">
            </div>
            <div>
                <label class="input-label" for="vid_url">Link YouTube</label>
                <input id="vid_url" name="url" type="url" required placeholder="https://www.youtube.com/watch?v=..." class="form-input">
            </div>
            <button class="btn-primary">Tambah Video</button>
        </form>
    </div>
</div>
