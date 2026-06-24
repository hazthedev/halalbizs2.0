<div class="mx-auto max-w-7xl px-4 py-6" wire:poll.30s>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl font-bold text-ink">{{ __('Flash Sale') }}</h1>

        @if ($endsAt)
            <div class="flex items-center gap-2 text-sm" x-data="{
                ends: {{ \Illuminate\Support\Carbon::parse($endsAt)->timestamp }} * 1000,
                left: '',
                tick() {
                    let s = Math.max(0, Math.floor((this.ends - Date.now()) / 1000));
                    let h = String(Math.floor(s / 3600)).padStart(2, '0');
                    let m = String(Math.floor((s % 3600) / 60)).padStart(2, '0');
                    let sec = String(s % 60).padStart(2, '0');
                    this.left = `${h}:${m}:${sec}`;
                },
            }" x-init="tick(); setInterval(() => tick(), 1000)">
                <span class="text-ink-soft">{{ __('Ends in') }}</span>
                <span class="rounded-lg bg-ink px-2 py-1 font-mono text-sm font-bold text-canvas tnum" x-text="left"></span>
            </div>
        @endif
    </div>

    @if ($items->isEmpty())
        <x-ui.empty-state class="mt-8" :title="__('No live deals right now')" :message="__('Check back soon — flash deals drop throughout the day.')" />
    @else
        <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($items as $item)
                @php($product = $item->variant->product)
                <a href="{{ route('product.show', $product->slug) }}" wire:navigate
                   class="group block overflow-hidden rounded-xl border border-line bg-surface shadow-soft transition hover:shadow-card">
                    <div class="aspect-square overflow-hidden bg-paper">
                        @if ($img = $product->getFirstMediaUrl('images', 'card'))
                            <img src="{{ $img }}" alt="{{ $product->getTranslation('name', app()->getLocale()) }}"
                                 class="size-full object-cover transition group-hover:scale-105" loading="lazy">
                        @endif
                    </div>
                    <div class="p-2.5">
                        <p class="line-clamp-2 text-[13px] font-medium text-ink">{{ $product->getTranslation('name', app()->getLocale()) }}</p>
                        <div class="mt-1 flex items-baseline gap-1.5">
                            <span class="text-base font-bold text-emerald tnum">@money($item->promo_price_sen)</span>
                            <span class="text-[12px] text-ink-faint line-through tnum">@money($item->variant->price_sen)</span>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-canvas-deep">
                            <div class="h-full rounded-full bg-brass" style="width: {{ $item->percentClaimed() }}%"></div>
                        </div>
                        <p class="mt-1 text-[11px] text-ink-soft">
                            {{ $item->remaining() > 0 ? __(':n left', ['n' => $item->remaining()]) : __('Sold out') }}
                        </p>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
