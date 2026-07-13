<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-5 lg:col-span-2">
        <div class="card space-y-4 p-5">
            <h2 class="font-semibold text-gray-900">Informasi Brand</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input name="name" label="Nama" :value="$brand->name" required />
                <x-form.input name="slug" label="Slug" :value="$brand->slug" hint="Kosongkan untuk membuat otomatis dari nama." />
            </div>
            <x-form.input name="website" label="Website" :value="$brand->website" placeholder="https://..." />
            <x-form.textarea name="description" label="Deskripsi" :value="$brand->description" />
        </div>

        <div class="card space-y-4 p-5">
            <h2 class="font-semibold text-gray-900">SEO</h2>
            <x-form.input name="meta_title" label="Meta Title" :value="$brand->meta_title" />
            <x-form.textarea name="meta_description" label="Meta Description" :value="$brand->meta_description" rows="3" />
        </div>
    </div>

    <div class="space-y-5">
        <div class="card space-y-4 p-5">
            <h2 class="font-semibold text-gray-900">Pengaturan</h2>
            <x-form.input type="number" name="sort_order" label="Urutan Tampil" :value="$brand->sort_order" min="0" />
            <x-form.checkbox name="is_featured" label="Brand unggulan" :checked="(bool) $brand->is_featured" />
            <x-form.checkbox name="is_active" label="Aktif" :checked="(bool) $brand->is_active" />
        </div>

        <div class="flex flex-col gap-2">
            <button type="submit" class="btn-primary w-full">Simpan</button>
            <a href="{{ route('admin.brands.index') }}" class="btn-outline w-full">Batal</a>
        </div>
    </div>
</div>
