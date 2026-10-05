@php
    $tabs = [
        ['admin.wa-campaign.index', 'Campaign', 'admin.wa-campaign.index|admin.wa-campaign.show|admin.wa-campaign.create|admin.wa-campaign.edit'],
        ['admin.wa-campaign.contacts', 'Kontak & Izin', 'admin.wa-campaign.contacts'],
        ['admin.wa-campaign.templates', 'Template', 'admin.wa-campaign.templates'],
        ['admin.wa-campaign.settings', 'Pengaturan', 'admin.wa-campaign.settings'],
    ];
@endphp
<nav class="mb-5 flex flex-wrap gap-1 border-b border-gray-200 text-sm">
    @foreach ($tabs as [$route, $label, $active])
        @php $on = collect(explode('|', $active))->contains(fn ($r) => request()->routeIs($r)); @endphp
        <a href="{{ route($route) }}" class="-mb-px border-b-2 px-3 py-2 {{ $on ? 'border-brand-600 font-semibold text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-800' }}">{{ $label }}</a>
    @endforeach
</nav>
