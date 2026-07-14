@php
    // Static class strings so Tailwind's JIT actually generates them.
    $styles = [
        'success' => 'border-green-200 bg-green-50 text-green-800',
        'error' => 'border-red-200 bg-red-50 text-red-800',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
        'status' => 'border-brand-200 bg-brand-50 text-brand-800',
    ];
@endphp

@foreach ($styles as $key => $classes)
    @if (session($key))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)"
             class="mb-4 flex items-start justify-between gap-3 rounded-lg border px-4 py-3 text-sm {{ $classes }}" role="alert">
            <span>{{ session($key) }}</span>
            <button @click="show=false" aria-label="Tutup" class="opacity-60 hover:opacity-100">&times;</button>
        </div>
    @endif
@endforeach

@if (isset($errors) && $errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
        <p class="font-semibold">Terdapat kesalahan:</p>
        <ul class="mt-1 list-inside list-disc">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
