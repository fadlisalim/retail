<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-5 lg:col-span-2">
        <div class="card space-y-4 p-5">
            <h2 class="font-semibold text-gray-900">Informasi Gudang</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input name="code" label="Kode" :value="$warehouse->code" required hint="Unik, mis. WH-MAIN." />
                <x-form.input name="name" label="Nama" :value="$warehouse->name" required />
            </div>
            <x-form.input name="city" label="Kota" :value="$warehouse->city" />
            <x-form.textarea name="address" label="Alamat" :value="$warehouse->address" rows="3" />
        </div>
    </div>

    <div class="space-y-5">
        <div class="card space-y-4 p-5">
            <h2 class="font-semibold text-gray-900">Pengaturan</h2>
            <x-form.checkbox name="is_default" label="Jadikan gudang default" :checked="(bool) $warehouse->is_default" />
            <x-form.checkbox name="is_active" label="Aktif" :checked="(bool) $warehouse->is_active" />
        </div>

        <div class="flex flex-col gap-2">
            <button type="submit" class="btn-primary w-full">Simpan</button>
            <a href="{{ route('admin.warehouses.index') }}" class="btn-outline w-full">Batal</a>
        </div>
    </div>
</div>
