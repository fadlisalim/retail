@php
    $positions = ['hero' => 'Hero', 'promo' => 'Promo', 'quotation' => 'Quotation'];
@endphp

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-5 lg:col-span-2">
        <div class="card space-y-4 p-5">
            <h2 class="font-semibold text-gray-900">Konten Banner</h2>
            <x-form.input name="title" label="Judul" :value="$banner->title" required />
            <x-form.input name="subtitle" label="Subjudul" :value="$banner->subtitle" />
            <x-form.textarea name="description" label="Deskripsi" :value="$banner->description" rows="3" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input name="button_text" label="Teks Tombol" :value="$banner->button_text" />
                <x-form.input name="button_url" label="URL Tombol" :value="$banner->button_url" placeholder="/promo atau https://..." />
            </div>
        </div>

        <div class="card space-y-4 p-5">
            <h2 class="font-semibold text-gray-900">Gambar</h2>
            <div>
                <label class="input-label" for="image_desktop">Gambar Desktop</label>
                @if ($banner->image_desktop_path)
                    <img src="{{ asset('storage/'.$banner->image_desktop_path) }}" alt="" class="mb-2 h-24 rounded border border-gray-200 object-cover">
                @endif
                <input type="file" name="image_desktop" id="image_desktop" accept="image/*" class="form-input">
                @error('image_desktop')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-gray-400">Maks 2MB. Kosongkan untuk mempertahankan gambar.</p>
            </div>
            <div>
                <label class="input-label" for="image_mobile">Gambar Mobile</label>
                @if ($banner->image_mobile_path)
                    <img src="{{ asset('storage/'.$banner->image_mobile_path) }}" alt="" class="mb-2 h-24 rounded border border-gray-200 object-cover">
                @endif
                <input type="file" name="image_mobile" id="image_mobile" accept="image/*" class="form-input">
                @error('image_mobile')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-gray-400">Maks 2MB. Kosongkan untuk mempertahankan gambar.</p>
            </div>
        </div>
    </div>

    <div class="space-y-5">
        <div class="card space-y-4 p-5">
            <h2 class="font-semibold text-gray-900">Pengaturan</h2>
            <x-form.select name="position" label="Posisi" :options="$positions" :selected="$banner->position" required />
            <x-form.input type="number" min="0" name="sort_order" label="Urutan Tampil" :value="$banner->sort_order" />
            <x-form.input type="date" name="starts_at" label="Mulai (opsional)" :value="$banner->starts_at?->format('Y-m-d')" />
            <x-form.input type="date" name="ends_at" label="Berakhir (opsional)" :value="$banner->ends_at?->format('Y-m-d')" />
            <x-form.checkbox name="is_active" label="Aktif" :checked="(bool) $banner->is_active" />
        </div>

        <div class="flex flex-col gap-2">
            <button type="submit" class="btn-primary w-full">Simpan</button>
            <a href="{{ route('admin.banners.index') }}" class="btn-outline w-full">Batal</a>
        </div>
    </div>
</div>
