@props(['rating' => 0, 'count' => null, 'size' => 'h-4 w-4'])
@php($rating = (float) $rating)
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1']) }} aria-label="Rating {{ number_format($rating, 1) }} dari 5">
    <span class="flex text-amber-400">
        @for ($i = 1; $i <= 5; $i++)
            <svg class="{{ $size }}" viewBox="0 0 20 20" fill="{{ $i <= round($rating) ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.2" aria-hidden="true">
                <path stroke-linejoin="round" d="M9.05 2.93c.3-.92 1.6-.92 1.9 0l1.36 4.18a1 1 0 0 0 .95.69h4.4c.97 0 1.37 1.24.59 1.81l-3.56 2.59a1 1 0 0 0-.36 1.12l1.36 4.18c.3.92-.75 1.68-1.54 1.12l-3.56-2.59a1 1 0 0 0-1.18 0l-3.56 2.59c-.79.56-1.84-.2-1.54-1.12l1.36-4.18a1 1 0 0 0-.36-1.12L1.15 9.6c-.78-.57-.38-1.81.59-1.81h4.4a1 1 0 0 0 .95-.69L9.05 2.93Z"/>
            </svg>
        @endfor
    </span>
    @if (! is_null($count))
        <span class="text-xs text-gray-500">({{ $count }})</span>
    @endif
</span>
