{{-- Product media manager: gallery images, datasheet PDFs, YouTube videos.
     Each block posts to its own endpoint so items add/remove without touching
     the main product form. Requires an existing $product. --}}
<div class="mt-6 space-y-6">
    {{-- ---------- Gambar ---------- --}}
    <div class="card space-y-4 p-5">
        <h2 class="font-semibold text-gray-900">Gambar Produk</h2>

        @if ($product->images->isNotEmpty())
            <div class="grid grid-cols-3 gap-3 sm:grid-cols-5">
                @foreach ($product->images as $img)
                    <div class="group relative overflow-hidden rounded-lg border border-gray-200">
                        <img src="{{ asset('storage/'.$img->path) }}" alt="{{ $img->alt }}" class="aspect-square w-full object-cover {{ $product->main_image_path === $img->path ? 'ring-2 ring-brand-500' : '' }}">
                        @if ($product->main_image_path === $img->path)
                            <span class="absolute left-1 top-1 rounded bg-brand-600 px-1.5 py-0.5 text-[10px] font-semibold text-white">Utama</span>
                        @endif
                        <div class="absolute inset-x-0 bottom-0 flex divide-x divide-white/20 bg-black/55 text-[11px] text-white opacity-0 transition group-hover:opacity-100">
                            @if ($product->main_image_path !== $img->path)
                                <form action="{{ route('admin.products.image.primary', [$product, $img]) }}" method="POST" class="flex-1">
                                    @csrf
                                    <button class="w-full py-1 hover:bg-white/10">Jadikan Utama</button>
                                </form>
                            @endif
                            <form action="{{ route('admin.products.image.destroy', $img) }}" method="POST" class="flex-1" onsubmit="return confirm('Hapus gambar ini?')">
                                @csrf @method('DELETE')
                                <button class="w-full py-1 text-red-200 hover:bg-white/10">Hapus</button>
                            </form>
                        </div>
                    </div>
                @endforeach
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
