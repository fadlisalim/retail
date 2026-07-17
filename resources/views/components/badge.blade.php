@props(['label', 'color' => 'brand'])
@php
    $map = [
        'Clearance' => 'bg-red-100 text-red-700',
        'Promo' => 'bg-accent-500/10 text-accent-600',
        'Baru' => 'bg-brand-100 text-brand-700',
        'Baru - Minor Defect' => 'bg-amber-100 text-amber-700',
        'Baru - Sisa Proyek' => 'bg-amber-100 text-amber-700',
        'Open Box' => 'bg-amber-100 text-amber-700',
        'Bekas Display' => 'bg-amber-100 text-amber-700',
        'Bekas Pakai' => 'bg-gray-200 text-gray-700',
        'Stok Terbatas' => 'bg-orange-100 text-orange-700',
        'Harga Nego' => 'bg-purple-100 text-purple-700',
        'Ambil di Lokasi' => 'bg-slate-100 text-slate-700',
        'Minta Penawaran' => 'bg-teal-100 text-teal-700',
    ];
    // Trust/price-guarantee tags (e.g. "JAMINAN HARGA TERMURAH") get a strong emerald look.
    $isGuarantee = (bool) preg_match('/\b(jaminan|termurah|murah|garansi harga)\b/i', $label);
    $classes = $map[$label] ?? ($isGuarantee ? 'bg-emerald-600 text-white shadow-sm' : 'bg-brand-100 text-brand-700');
@endphp
<span class="badge {{ $classes }}">{{ $label }}</span>
