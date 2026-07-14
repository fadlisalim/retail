{{-- Energi.Click brand mark: a stylised "E" of three sheared slabs. The three
     teal blades inherit `currentColor` (so the mark turns white on dark surfaces
     like the admin sidebar) while the bottom-right accent slab stays brand
     orange. Caller sizes it via class, e.g. <x-logo class="h-9 w-9 text-brand-700" /> --}}
<svg viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="{{ brand() }}" {{ $attributes }}>
    <g transform="translate(14,0) skewX(-16)">
        <rect x="34" y="20" width="60" height="22" rx="9" fill="currentColor"/>
        <rect x="27" y="49" width="60" height="22" rx="9" fill="currentColor"/>
        <rect x="20" y="78" width="36" height="22" rx="9" fill="currentColor"/>
        <rect x="62" y="78" width="30" height="22" rx="9" fill="#F26F1F"/>
    </g>
</svg>
