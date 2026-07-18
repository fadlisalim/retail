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
        <div class="card space-y-3 p-5">
            <h2 class="font-semibold text-gray-900">Logo Brand</h2>
            @if ($brand->logo_path)
                <div class="flex items-center gap-3">
                    <img src="{{ asset('storage/'.$brand->logo_path) }}" alt="{{ $brand->name }}" class="h-16 w-16 rounded-lg border border-gray-200 object-contain p-1">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" name="remove_logo" value="1" class="rounded border-gray-300 text-brand-600">
                        Hapus logo
                    </label>
                </div>
            @endif
            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="form-input">
            @error('logo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            <p class="text-xs text-gray-400">PNG/JPG/WebP/SVG, maks 2MB. Disarankan latar transparan.</p>
        </div>

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
