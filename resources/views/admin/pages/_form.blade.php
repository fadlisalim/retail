<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-5 lg:col-span-2">
        <div class="card space-y-4 p-5">
            <h2 class="font-semibold text-gray-900">Konten Halaman</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input name="title" label="Judul" :value="$page->title" required />
                <x-form.input name="slug" label="Slug" :value="$page->slug" hint="Kosongkan untuk otomatis dari judul." />
            </div>
            <x-form.textarea name="content" label="Konten" :value="$page->content" rows="14" hint="Mendukung HTML." />
        </div>

        <div class="card space-y-4 p-5">
            <h2 class="font-semibold text-gray-900">SEO</h2>
            <x-form.input name="meta_title" label="Meta Title" :value="$page->meta_title" />
            <x-form.textarea name="meta_description" label="Meta Description" :value="$page->meta_description" rows="3" />
        </div>
    </div>

    <div class="space-y-5">
        <div class="card space-y-4 p-5">
            <h2 class="font-semibold text-gray-900">Publikasi</h2>
            <x-form.checkbox name="is_published" label="Terbitkan" :checked="(bool) $page->is_published" />
        </div>

        <div class="flex flex-col gap-2">
            <button type="submit" class="btn-primary w-full">Simpan</button>
            <a href="{{ route('admin.pages.index') }}" class="btn-outline w-full">Batal</a>
        </div>
    </div>
</div>
