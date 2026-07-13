@props(['items' => []])
{{-- $items: array of ['label' => ..., 'url' => ... (optional)] --}}
<nav aria-label="Breadcrumb" class="mb-4 text-sm">
    <ol class="flex flex-wrap items-center gap-1 text-gray-500">
        <li><a href="{{ route('home') }}" class="hover:text-brand-700">Beranda</a></li>
        @foreach ($items as $item)
            <li aria-hidden="true" class="px-1">/</li>
            <li>
                @if (! empty($item['url']) && ! $loop->last)
                    <a href="{{ $item['url'] }}" class="hover:text-brand-700">{{ $item['label'] }}</a>
                @else
                    <span class="font-medium text-gray-700" @if($loop->last) aria-current="page" @endif>{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
