@php
    $types = ['text' => 'Teks', 'number' => 'Angka', 'select' => 'Pilihan', 'boolean' => 'Ya/Tidak'];
@endphp

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-5 lg:col-span-2">
        <div class="card space-y-4 p-5">
            <h2 class="font-semibold text-gray-900">Informasi Atribut</h2>
            <x-form.select name="attribute_group_id" label="Grup Atribut" :options="$groups"
                           :selected="$attribute->attribute_group_id" placeholder="— Tanpa grup —" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input name="name" label="Nama" :value="$attribute->name" required />
                <x-form.input name="slug" label="Slug" :value="$attribute->slug" hint="Kosongkan untuk otomatis." />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.select name="type" label="Tipe" :options="$types" :selected="$attribute->type" required />
                <x-form.input name="unit" label="Satuan" :value="$attribute->unit" placeholder="mis. Wp, V, kWh" />
            </div>
        </div>
    </div>

    <div class="space-y-5">
        <div class="card space-y-4 p-5">
            <h2 class="font-semibold text-gray-900">Pengaturan</h2>
            <x-form.input type="number" name="sort_order" label="Urutan Tampil" :value="$attribute->sort_order" min="0" />
            <x-form.checkbox name="is_filterable" label="Bisa difilter" :checked="(bool) $attribute->is_filterable" />
            <x-form.checkbox name="is_comparable" label="Bisa dibandingkan" :checked="(bool) $attribute->is_comparable" />
        </div>

        <div class="flex flex-col gap-2">
            <button type="submit" class="btn-primary w-full">Simpan</button>
            <a href="{{ route('admin.attributes.index') }}" class="btn-outline w-full">Batal</a>
        </div>
    </div>
</div>
