@php
    $selectedRoles = collect(old('roles', $user->roles->pluck('id')->all()))->map(fn ($v) => (int) $v)->all();
@endphp

<form method="POST" action="{{ $action }}" class="card space-y-5 p-6">
    @csrf
    @if ($isEdit)@method('PUT')@endif

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="name" class="input-label">Nama <span class="text-red-500">*</span></label>
            <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required class="form-input">
            @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="email" class="input-label">Email <span class="text-red-500">*</span></label>
            <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required class="form-input">
            @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password" class="input-label">Password @unless ($isEdit)<span class="text-red-500">*</span>@endunless</label>
            <input type="password" name="password" id="password" @unless ($isEdit) required @endunless class="form-input" autocomplete="new-password">
            <p class="mt-1 text-xs text-gray-400">{{ $isEdit ? 'Kosongkan jika tidak ingin mengubah.' : 'Minimal 8 karakter.' }}</p>
            @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="whatsapp" class="input-label">WhatsApp</label>
            <input type="text" name="whatsapp" id="whatsapp" value="{{ old('whatsapp', $user->whatsapp) }}" class="form-input">
            @error('whatsapp')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div>
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true)) class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
            <span>Akun aktif</span>
        </label>
    </div>

    <div>
        <p class="input-label mb-2">Peran</p>
        @if ($roles->isEmpty())
            <p class="text-sm text-gray-400">Belum ada peran tersedia.</p>
        @else
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($roles as $role)
                    <label class="flex items-start gap-2 rounded-lg border border-gray-200 p-3 text-sm">
                        <input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(in_array($role->id, $selectedRoles, true))
                               class="mt-0.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                        <span>
                            <span class="font-medium text-gray-800">{{ $role->name }}</span>
                            @if ($role->description)<span class="block text-xs text-gray-400">{{ $role->description }}</span>@endif
                        </span>
                    </label>
                @endforeach
            </div>
        @endif
        @error('roles')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="flex justify-end gap-2">
        <a href="{{ route('admin.users.index') }}" class="btn-outline">Batal</a>
        <button type="submit" class="btn-primary">{{ $isEdit ? 'Simpan Perubahan' : 'Buat User' }}</button>
    </div>
</form>
