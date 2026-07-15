<div class="grid gap-6 lg:grid-cols-3" x-data="{ parent: '{{ old('parent_id', $category->parent_id) }}' }">
    <div class="space-y-5 lg:col-span-2">
        <div class="card space-y-4 p-5">
            <h2 class="font-semibold text-gray-900">Informasi Kategori</h2>
            <x-form.select name="parent_id" label="Kategori Induk" :options="$parents"
                           :selected="$category->parent_id" placeholder="— Root (tanpa induk) —" x-model="parent" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input name="name" label="Nama" :value="$category->name" required />
                <x-form.input name="slug" label="Slug" :value="$category->slug" hint="Kosongkan untuk membuat otomatis dari nama." />
            </div>
            <x-form.input name="icon" label="Ikon" :value="$category->icon" hint="Nama atau kelas ikon (opsional)." />
            <x-form.textarea name="description" label="Deskripsi" :value="$category->description" />
        </div>

        {{-- Category image: only meaningful for main (root) categories — that's what the
             "Kategori Unggulan" cards on the homepage render. Hidden for sub-categories. --}}
        <div class="card space-y-4 p-5" x-show="!parent" x-cloak>
            <div>
                <h2 class="font-semibold text-gray-900">Gambar Kategori</h2>
                <p class="text-xs text-gray-400">Tampil di kartu <strong>Kategori Unggulan</strong> di halaman depan. Untuk kategori utama saja.</p>
            </div>
            @if ($category->image_path)
                <div class="flex items-center gap-3">
                    <img src="{{ asset('storage/'.$category->image_path) }}" alt="" class="h-16 w-16 rounded-full border border-gray-200 object-cover">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" name="remove_image" value="1" class="rounded border-gray-300">
                        Hapus gambar
                    </label>
                </div>
            @endif
            <div>
                <label class="input-label" for="image">Unggah Gambar</label>
                <input type="file" name="image" id="image" accept="image/*" class="form-input">
                @error('image')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-gray-400">Maks 2MB. Idealnya persegi (mis. 200×200). Kosongkan untuk mempertahankan gambar.</p>
            </div>
        </div>

        <div class="card space-y-4 p-5">
            <h2 class="font-semibold text-gray-900">SEO</h2>
            <x-form.input name="meta_title" label="Meta Title" :value="$category->meta_title" />
            <x-form.textarea name="meta_description" label="Meta Description" :value="$category->meta_description" rows="3" />
        </div>
    </div>

    <div class="space-y-5">
        <div class="card space-y-4 p-5">
            <h2 class="font-semibold text-gray-900">Pengaturan</h2>
            <x-form.input type="number" name="sort_order" label="Urutan Tampil" :value="$category->sort_order" min="0" />
            <x-form.checkbox name="is_featured" label="Kategori unggulan" :checked="(bool) $category->is_featured" />
            <x-form.checkbox name="is_active" label="Aktif" :checked="(bool) $category->is_active" />
        </div>

        <div class="flex flex-col gap-2">
            <button type="submit" class="btn-primary w-full">Simpan</button>
            <a href="{{ route('admin.categories.index') }}" class="btn-outline w-full">Batal</a>
        </div>
    </div>
</div>
