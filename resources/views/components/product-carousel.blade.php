@props(['title', 'products', 'viewAll' => null, 'subtitle' => null])
@if ($products->isNotEmpty())
    {{-- Horizontal swiper-style row (monotaro concept): snap-scroll track with prev/next arrows. --}}
    <section class="mt-8" x-data="{
        scroll(dir) {
            const t = $refs.track;
            t.scrollBy({ left: dir * (t.clientWidth * 0.85), behavior: 'smooth' });
        },
    }">
        <div class="mb-3 flex items-end justify-between gap-3">
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-gray-900 sm:text-xl">{{ $title }}</h2>
                @if ($subtitle)<p class="truncate text-sm text-gray-500">{{ $subtitle }}</p>@endif
            </div>
            <div class="flex shrink-0 items-center gap-2">
                @if ($viewAll)
                    <a href="{{ $viewAll }}" class="text-sm font-medium text-brand-600 hover:underline">Lihat semua →</a>
                @endif
                <div class="hidden items-center gap-1 sm:flex">
                    <button type="button" @click="scroll(-1)" aria-label="Sebelumnya"
                            class="grid h-8 w-8 place-items-center rounded-full border border-gray-200 bg-white text-gray-600 transition hover:border-brand-400 hover:text-brand-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
                    </button>
                    <button type="button" @click="scroll(1)" aria-label="Berikutnya"
                            class="grid h-8 w-8 place-items-center rounded-full border border-gray-200 bg-white text-gray-600 transition hover:border-brand-400 hover:text-brand-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                    </button>
                </div>
            </div>
        </div>
        <div x-ref="track"
             class="flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-smooth pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            @foreach ($products->take(12) as $product)
                <div class="w-[46%] shrink-0 snap-start sm:w-[31%] md:w-[23%] lg:w-[18.5%]">
                    <x-product-card :product="$product" />
                </div>
            @endforeach
        </div>
    </section>
@endif
