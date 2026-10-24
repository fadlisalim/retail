{{-- Energi.Click brand mark. Caller sizes it via class, e.g.
     <x-logo class="h-9 w-9" />. The mark's own colours (teal + orange) are baked
     into the image; it sits on light surfaces everywhere it's used.
     Pakai versi 128px (logo.png 512px tetap untuk PDF/OG); lazy="true" untuk logo di bawah lipatan. --}}
@props(['lazy' => false])
<img src="{{ asset('images/logo-sm.png') }}" alt="{{ brand() }}" width="128" height="128" decoding="async"@if ($lazy) loading="lazy"@endif {{ $attributes->class('inline-block object-contain') }}>
