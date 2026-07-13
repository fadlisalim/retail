<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-5 lg:col-span-2">
        <div class="card space-y-4 p-5">
            <h2 class="font-semibold text-gray-900">Isi FAQ</h2>
            <x-form.input name="category" label="Kategori" :value="$faq->category" required hint="mis. Umum, Pembayaran, Pengiriman." />
            <x-form.input name="question" label="Pertanyaan" :value="$faq->question" required />
            <x-form.textarea name="answer" label="Jawaban" :value="$faq->answer" rows="6" required />
        </div>
    </div>

    <div class="space-y-5">
        <div class="card space-y-4 p-5">
            <h2 class="font-semibold text-gray-900">Pengaturan</h2>
            <x-form.input type="number" min="0" name="sort_order" label="Urutan Tampil" :value="$faq->sort_order" />
            <x-form.checkbox name="is_active" label="Aktif" :checked="(bool) $faq->is_active" />
        </div>

        <div class="flex flex-col gap-2">
            <button type="submit" class="btn-primary w-full">Simpan</button>
            <a href="{{ route('admin.faqs.index') }}" class="btn-outline w-full">Batal</a>
        </div>
    </div>
</div>
