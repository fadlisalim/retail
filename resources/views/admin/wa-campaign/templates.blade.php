@extends('layouts.admin')

@section('title', 'Template WA Campaign')

@section('content')
    <x-admin.page-header title="Template Pesan" subtitle="Pesan promo yang bisa dipakai ulang di campaign builder" />
    @include('admin.wa-campaign._nav')

    <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
        <div class="space-y-4">
            @forelse ($templates as $t)
                <div class="card p-4" x-data="{ edit: false }">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <h2 class="font-semibold text-gray-900">{{ $t->name }}</h2>
                            <p class="mt-1 whitespace-pre-wrap text-sm text-gray-600" x-show="!edit">{{ $t->body }}</p>
                            @if ($t->image_path)<img src="{{ asset('storage/'.$t->image_path) }}" class="mt-2 h-24 rounded object-cover" alt="">@endif
                            <p class="mt-1 text-[11px] text-gray-400">{{ $t->creator?->name }} · {{ $t->updated_at->format('d/m/Y') }}</p>
                        </div>
                        <div class="flex shrink-0 gap-2 text-xs">
                            <button type="button" class="text-brand-700 hover:underline" @click="edit = !edit">Edit</button>
                            <form method="POST" action="{{ route('admin.wa-campaign.templates.destroy', $t) }}" onsubmit="return confirm('Hapus template?')">@csrf @method('DELETE')<button class="text-red-600 hover:underline">Hapus</button></form>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.wa-campaign.templates.update', $t) }}" enctype="multipart/form-data" class="mt-3 space-y-2" x-show="edit" x-cloak>
                        @csrf @method('PUT')
                        <input type="text" name="name" value="{{ $t->name }}" required class="form-input text-sm">
                        <textarea name="body" rows="5" required class="form-textarea text-sm">{{ $t->body }}</textarea>
                        <div class="flex flex-wrap items-center gap-3 text-xs"><input type="file" name="image" accept="image/*" class="form-input text-xs">@if ($t->image_path)<label class="flex items-center gap-1"><input type="checkbox" name="remove_image" value="1" class="rounded"> hapus gambar</label>@endif</div>
                        <button class="btn-primary">Simpan</button>
                    </form>
                </div>
            @empty
                <div class="card p-8 text-center text-gray-400">Belum ada template.</div>
            @endforelse
        </div>
        <div class="card h-fit p-4">
            <h2 class="mb-2 font-semibold text-gray-900">Template baru</h2>
            <form method="POST" action="{{ route('admin.wa-campaign.templates.store') }}" enctype="multipart/form-data" class="space-y-2">
                @csrf
                <input type="text" name="name" placeholder="Nama template" required class="form-input text-sm" value="{{ old('name') }}">
                <textarea name="body" rows="7" required class="form-textarea text-sm" placeholder="Halo {nama}, …">{{ old('body') }}</textarea>
                <input type="file" name="image" accept="image/*" class="form-input text-xs">
                <p class="text-[11px] text-gray-400">Placeholder: {nama}, {nama_lengkap}, {link}, {produk}, {harga}. Footer STOP ditambahkan otomatis saat kirim.</p>
                @error('body')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                <button class="btn-primary w-full">Simpan template</button>
            </form>
        </div>
    </div>
@endsection
