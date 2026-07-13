<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-5 lg:col-span-2">
        <div class="card space-y-4 p-5">
            <h2 class="font-semibold text-gray-900">Konten Artikel</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input name="title" label="Judul" :value="$article->title" required />
                <x-form.input name="slug" label="Slug" :value="$article->slug" hint="Kosongkan untuk otomatis dari judul." />
            </div>
            <x-form.textarea name="excerpt" label="Ringkasan" :value="$article->excerpt" rows="2" hint="Maksimal 500 karakter." />
            <x-form.textarea name="content" label="Konten" :value="$article->content" rows="14" hint="Mendukung HTML." />
        </div>

        <div class="card space-y-4 p-5">
            <h2 class="font-semibold text-gray-900">SEO</h2>
            <x-form.input name="meta_title" label="Meta Title" :value="$article->meta_title" />
            <x-form.textarea name="meta_description" label="Meta Description" :value="$article->meta_description" rows="3" />
        </div>
    </div>

    <div class="space-y-5">
        <div class="card space-y-4 p-5">
            <h2 class="font-semibold text-gray-900">Publikasi</h2>
            <x-form.checkbox name="is_published" label="Terbitkan" :checked="(bool) $article->is_published" />
            <x-form.input type="datetime-local" name="published_at" label="Tanggal Publikasi"
                          :value="$article->published_at?->format('Y-m-d\TH:i')"
                          hint="Otomatis terisi saat diterbitkan bila kosong." />
        </div>

        <div class="card space-y-4 p-5">
            <h2 class="font-semibold text-gray-900">Gambar Sampul</h2>
            @if ($article->cover_path)
                <img src="{{ asset('storage/'.$article->cover_path) }}" alt="" class="mb-2 w-full rounded border border-gray-200 object-cover">
            @endif
            <input type="file" name="cover" id="cover" accept="image/*" class="form-input">
            @error('cover')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            <p class="text-xs text-gray-400">Maks 2MB. Kosongkan untuk mempertahankan gambar.</p>
        </div>

        <div class="flex flex-col gap-2">
            <button type="submit" class="btn-primary w-full">Simpan</button>
            <a href="{{ route('admin.articles.index') }}" class="btn-outline w-full">Batal</a>
        </div>
    </div>
</div>
