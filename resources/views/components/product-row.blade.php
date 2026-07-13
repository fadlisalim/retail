@props(['title', 'products', 'viewAll' => null, 'subtitle' => null])
@if ($products->isNotEmpty())
    <section class="mt-8">
        <div class="mb-3 flex items-end justify-between">
            <div>
                <h2 class="text-lg font-bold text-gray-900 sm:text-xl">{{ $title }}</h2>
                @if ($subtitle)<p class="text-sm text-gray-500">{{ $subtitle }}</p>@endif
            </div>
            @if ($viewAll)
                <a href="{{ $viewAll }}" class="shrink-0 text-sm font-medium text-brand-600 hover:underline">Lihat semua →</a>
            @endif
        </div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
            @foreach ($products->take(10) as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
    </section>
@endif
