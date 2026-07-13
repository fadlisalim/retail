@props(['label', 'color' => 'brand'])
@php
    $map = [
        'Clearance' => 'bg-red-100 text-red-700',
        'Promo' => 'bg-accent-500/10 text-accent-600',
        'Baru' => 'bg-brand-100 text-brand-700',
        'Open Box' => 'bg-amber-100 text-amber-700',
        'Bekas Display' => 'bg-amber-100 text-amber-700',
        'Bekas Pakai' => 'bg-gray-200 text-gray-700',
        'Stok Terbatas' => 'bg-orange-100 text-orange-700',
        'Harga Nego' => 'bg-purple-100 text-purple-700',
        'Ambil di Lokasi' => 'bg-slate-100 text-slate-700',
        'Minta Penawaran' => 'bg-teal-100 text-teal-700',
    ];
    $classes = $map[$label] ?? 'bg-brand-100 text-brand-700';
@endphp
<span class="badge {{ $classes }}">{{ $label }}</span>
