@php
    $types = ['percent' => 'Persen (%)', 'fixed' => 'Nominal (Rp)', 'free_shipping' => 'Gratis Ongkir'];
@endphp

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-5 lg:col-span-2">
        <div class="card space-y-4 p-5">
            <h2 class="font-semibold text-gray-900">Informasi Voucher</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input name="code" label="Kode" :value="$coupon->code" required hint="Otomatis huruf kapital." />
                <x-form.input name="name" label="Nama / Deskripsi" :value="$coupon->name" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.select name="type" label="Tipe Diskon" :options="$types" :selected="$coupon->type" required />
                <x-form.input type="number" step="0.01" min="0" name="value" label="Nilai" :value="$coupon->value" required
                              hint="Persen atau nominal sesuai tipe." />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input type="number" step="0.01" min="0" name="min_subtotal" label="Minimal Belanja (Rp)" :value="$coupon->min_subtotal" />
                <x-form.input type="number" step="0.01" min="0" name="max_discount" label="Maksimal Diskon (Rp)" :value="$coupon->max_discount"
                              hint="Kosongkan untuk tanpa batas." />
            </div>
        </div>

        <div class="card space-y-4 p-5">
            <h2 class="font-semibold text-gray-900">Batas & Periode</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input type="number" min="0" name="usage_limit" label="Kuota Total" :value="$coupon->usage_limit"
                              hint="Kosongkan untuk tanpa batas." />
                <x-form.input type="number" min="0" name="usage_limit_per_user" label="Kuota per User" :value="$coupon->usage_limit_per_user" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input type="datetime-local" name="starts_at" label="Mulai" :value="$coupon->starts_at?->format('Y-m-d\TH:i')" />
                <x-form.input type="datetime-local" name="ends_at" label="Berakhir" :value="$coupon->ends_at?->format('Y-m-d\TH:i')" />
            </div>
        </div>
    </div>

    <div class="space-y-5">
        <div class="card space-y-4 p-5">
            <h2 class="font-semibold text-gray-900">Pengaturan</h2>
            <x-form.checkbox name="is_combinable" label="Bisa digabung promo lain" :checked="(bool) $coupon->is_combinable" />
            <x-form.checkbox name="is_active" label="Aktif" :checked="(bool) $coupon->is_active" />
        </div>

        <div class="flex flex-col gap-2">
            <button type="submit" class="btn-primary w-full">Simpan</button>
            <a href="{{ route('admin.coupons.index') }}" class="btn-outline w-full">Batal</a>
        </div>
    </div>
</div>
