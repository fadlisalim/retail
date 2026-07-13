<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-5 lg:col-span-2">
        <div class="card space-y-4 p-5">
            <h2 class="font-semibold text-gray-900">Informasi Kategori</h2>
            <x-form.select name="parent_id" label="Kategori Induk" :options="$parents"
                           :selected="$category->parent_id" placeholder="— Root (tanpa induk) —" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input name="name" label="Nama" :value="$category->name" required />
                <x-form.input name="slug" label="Slug" :value="$category->slug" hint="Kosongkan untuk membuat otomatis dari nama." />
            </div>
            <x-form.input name="icon" label="Ikon" :value="$category->icon" hint="Nama atau kelas ikon (opsional)." />
            <x-form.textarea name="description" label="Deskripsi" :value="$category->description" />
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
