@props(['color' => 'gray', 'label' => ''])
@php
    $map = [
        'gray' => 'bg-gray-100 text-gray-700',
        'amber' => 'bg-amber-100 text-amber-700',
        'green' => 'bg-green-100 text-green-700',
        'blue' => 'bg-blue-100 text-blue-700',
        'red' => 'bg-red-100 text-red-700',
        'purple' => 'bg-purple-100 text-purple-700',
    ];
    $cls = $map[$color] ?? $map['gray'];
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium $cls"]) }}>{{ $label }}</span>
