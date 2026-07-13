@php
    // Two-tone brand wordmark. Splits the brand name before the first "." or space
    // so e.g. "Energi.Click" renders as Energi + .Click, "Rekasurya Store" as
    // Rekasurya + Store. Caller supplies sizing/base colour via class attributes.
    $b = brand();
    $parts = preg_split('/(?=[.\s])/u', $b, 2);
    $main = $parts[0];
    $accent = $parts[1] ?? '';
@endphp
<span {{ $attributes }}>{{ $main }}<span class="text-accent-500">{{ $accent }}</span></span>
