@props(['title', 'subtitle' => null])
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-bold text-gray-900">{{ $title }}</h1>
        @if ($subtitle)<p class="text-sm text-gray-500">{{ $subtitle }}</p>@endif
    </div>
    @if (isset($actions))
        <div class="flex items-center gap-2">{{ $actions }}</div>
    @endif
</div>
