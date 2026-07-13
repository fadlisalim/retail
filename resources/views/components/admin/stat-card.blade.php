@props(['label', 'value', 'sub' => null, 'color' => 'brand'])
@php
    $accent = [
        'brand' => 'text-brand-700', 'green' => 'text-green-600',
        'amber' => 'text-amber-600', 'red' => 'text-red-600', 'blue' => 'text-blue-600',
    ][$color] ?? 'text-brand-700';
@endphp
<div class="card p-4">
    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $label }}</p>
    <p class="mt-1 text-2xl font-bold {{ $accent }}">{{ $value }}</p>
    @if ($sub)<p class="mt-1 text-xs text-gray-400">{{ $sub }}</p>@endif
</div>
