{{-- Data customer/proyek penawaran. Variabel: $customers, $q (Quotation|null) --}}
<div class="grid gap-4 sm:grid-cols-2" x-data="{ pickCustomer(id) { const c = this.customers.find(x => String(x.id) === String(id)); if (!c) return; this.$refs.name.value = c.name; this.$refs.email.value = c.email || ''; this.$refs.phone.value = c.whatsapp || c.phone || ''; }, customers: @js($customers->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'email' => $c->email, 'whatsapp' => $c->whatsapp, 'phone' => $c->phone])->values()) }">
    <div class="sm:col-span-2">
        <label class="input-label">Akun customer (opsional — isi otomatis & penawaran tampil di akunnya)</label>
        <select name="user_id" class="form-select" @change="pickCustomer($event.target.value)">
            <option value="">— tanpa akun / customer baru —</option>
            @foreach ($customers as $c)
                <option value="{{ $c->id }}" @selected((string) old('user_id', $q?->user_id) === (string) $c->id)>{{ $c->name }} — {{ $c->email }}</option>
            @endforeach
        </select>
    </div>
    <div><label class="input-label">Nama customer / PIC <span class="text-red-500">*</span></label><input type="text" name="contact_name" x-ref="name" value="{{ old('contact_name', $q?->contact_name) }}" required class="form-input"></div>
    <div><label class="input-label">Perusahaan / instansi</label><input type="text" name="company_name" value="{{ old('company_name', $q?->company_name) }}" class="form-input"></div>
    <div><label class="input-label">No. WhatsApp</label><input type="text" name="contact_phone" x-ref="phone" value="{{ old('contact_phone', $q?->contact_phone) }}" placeholder="0812…" class="form-input"></div>
    <div><label class="input-label">Email</label><input type="email" name="contact_email" x-ref="email" value="{{ old('contact_email', $q?->contact_email) }}" class="form-input"></div>
    <div><label class="input-label">NPWP</label><input type="text" name="npwp" value="{{ old('npwp', $q?->npwp) }}" class="form-input"></div>
    <div><label class="input-label">Nama proyek / perihal</label><input type="text" name="project_name" value="{{ old('project_name', $q?->project_name) }}" placeholder="mis. PJU Desa Sukamaju 20 titik" class="form-input"></div>
    <div class="sm:col-span-2"><label class="input-label">Lokasi proyek / alamat</label><input type="text" name="project_location" value="{{ old('project_location', $q?->project_location) }}" class="form-input"></div>
    <div class="sm:col-span-2"><label class="input-label">Catatan internal (tidak tampil di PDF)</label><input type="text" name="technical_notes" value="{{ old('technical_notes', $q?->technical_notes) }}" class="form-input"></div>
</div>
