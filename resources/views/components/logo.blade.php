{{-- Energi.Click brand mark. Caller sizes it via class, e.g.
     <x-logo class="h-9 w-9" />. The mark's own colours (teal + orange) are baked
     into the image; it sits on light surfaces everywhere it's used. --}}
<img src="{{ asset('images/logo.png') }}" alt="{{ brand() }}" {{ $attributes->class('inline-block object-contain') }}>
