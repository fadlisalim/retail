@props(['banners'])
{{-- Auto-sliding banner carousel (desktop + mobile artwork via <picture>).
     One slide when a single banner; dots + progress ring when multiple. --}}
<div class="group relative -mx-4 grid overflow-hidden rounded-none sm:mx-0 sm:rounded-2xl"
     x-data="{
        active: 0,
        count: {{ $banners->count() }},
        progress: 0,
        timer: null,
        tick: null,
        go(i) { this.active = (i + this.count) % this.count; this.progress = 0; },
        next() { this.go(this.active + 1); },
        start() {
            if (this.count <= 1) return;
            this.progress = 0;
            this.timer = setInterval(() => this.next(), 6000);
            this.tick = setInterval(() => { this.progress = Math.min(100, this.progress + 100 / 120); }, 50);
        },
        stop() { clearInterval(this.timer); clearInterval(this.tick); },
     }"
     x-init="start()"
     @mouseenter="stop()" @mouseleave="start()">
    @foreach ($banners as $i => $banner)
        <div x-show="active === {{ $i }}" x-transition.opacity.duration.500ms class="relative [grid-area:1/1]" @if ($i !== 0) style="display:none" @endif>
            @if ($banner->image_desktop_path)
                <a @if ($banner->button_url) href="{{ $banner->button_url }}" @endif class="block">
                    <picture>
                        @if ($banner->image_mobile_path)
                            <source media="(max-width: 640px)" srcset="{{ asset('storage/'.$banner->image_mobile_path) }}">
                        @endif
                        <img src="{{ asset('storage/'.$banner->image_desktop_path) }}" alt="{{ $banner->title ?: 'Banner' }}" class="block h-auto w-full">
                    </picture>
                </a>
            @else
                <div class="flex min-h-[220px] flex-col justify-center gap-3 bg-gradient-to-r from-brand-700 to-brand-500 p-6 text-white sm:min-h-[300px] sm:p-12">
                    <span class="text-xs font-semibold uppercase tracking-wide text-accent-300">{{ $banner->subtitle }}</span>
                    <h2 class="max-w-xl text-2xl font-extrabold sm:text-4xl">{{ $banner->title }}</h2>
                    <p class="max-w-lg text-sm text-brand-50 sm:text-base">{{ $banner->description }}</p>
                    @if ($banner->button_url)
                        <a href="{{ $banner->button_url }}" class="btn-accent mt-2 w-fit">{{ $banner->button_text ?: 'Belanja Sekarang' }}</a>
                    @endif
                </div>
            @endif
        </div>
    @endforeach
    @if ($banners->count() > 1)
        <div class="pointer-events-none absolute right-3 top-3 grid h-9 w-9 place-items-center rounded-full bg-black/25 backdrop-blur-sm">
            <svg class="h-9 w-9 -rotate-90" viewBox="0 0 36 36" aria-hidden="true">
                <circle cx="18" cy="18" r="15" fill="none" stroke="rgba(255,255,255,0.35)" stroke-width="2.5"/>
                <circle cx="18" cy="18" r="15" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round"
                        stroke-dasharray="94.25" :stroke-dashoffset="94.25 * (1 - progress / 100)"/>
            </svg>
            <span class="absolute text-[10px] font-bold text-white" x-text="(active + 1) + '/' + count"></span>
        </div>
        <div class="absolute bottom-4 left-1/2 flex -translate-x-1/2 gap-2 rounded-full bg-black/25 px-2.5 py-1.5 backdrop-blur-sm">
            @foreach ($banners as $i => $b)
                <button type="button" @click="go({{ $i }})" :class="active === {{ $i }} ? 'w-6 bg-white' : 'w-2 bg-white/70'" class="h-2 rounded-full transition-all" aria-label="Ke banner {{ $i + 1 }}"></button>
            @endforeach
        </div>
    @endif
</div>
