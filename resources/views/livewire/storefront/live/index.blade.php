<div class="mx-auto w-full max-w-7xl px-4 py-8 lg:py-12">
    <div class="flex items-center gap-2.5">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-danger px-2.5 py-1 text-[11px] font-bold uppercase tracking-[0.06em] text-white">
            <span class="size-1.5 animate-pulse rounded-full bg-white"></span>{{ __('Live') }}
        </span>
        <x-ui.section-heading as="h1" :title="__('Live shopping')" />
    </div>

    {{-- Live now --}}
    @if ($liveNow->isNotEmpty())
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($liveNow as $session)
                <a href="{{ route('live.room', $session->slug) }}" wire:navigate
                   class="group overflow-hidden rounded-[var(--radius-card)] border border-line bg-surface shadow-soft transition-all hover:-translate-y-0.5 hover:shadow-card">
                    <div class="surface-girih relative flex aspect-video items-center justify-center bg-ink">
                        <span class="absolute left-3 top-3 inline-flex items-center gap-1.5 rounded-full bg-danger px-2.5 py-1 text-[11px] font-bold uppercase tracking-[0.06em] text-white">
                            <span class="size-1.5 animate-pulse rounded-full bg-white"></span>{{ __('Live') }}
                        </span>
                        <x-ui.star-mark :size="40" class="text-brass/50" />
                    </div>
                    <div class="p-4">
                        <h2 class="line-clamp-1 font-display text-base font-bold text-ink">{{ $session->title }}</h2>
                        <p class="mt-1 text-[13px] text-ink-soft">{{ $session->store?->name }} · {{ trans_choice(':count item|:count items', $session->products_count, ['count' => $session->products_count]) }}</p>
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <div class="mt-6 rounded-[var(--radius-card)] border border-line bg-surface p-10 text-center shadow-soft">
            <p class="text-sm text-ink-soft">{{ __('No streams live right now.') }}</p>
        </div>
    @endif

    {{-- Upcoming --}}
    @if ($upcoming->isNotEmpty())
        <h2 class="mt-10 font-display text-lg font-bold">{{ __('Coming up') }}</h2>
        <ul class="mt-4 divide-y divide-line rounded-[var(--radius-card)] border border-line bg-surface shadow-soft">
            @foreach ($upcoming as $session)
                <li class="flex items-center justify-between gap-3 px-5 py-3.5">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-ink">{{ $session->title }}</p>
                        <p class="text-[13px] text-ink-soft">{{ $session->store?->name }}</p>
                    </div>
                    <span class="shrink-0 text-[13px] text-ink-soft">{{ $session->scheduled_for?->diffForHumans() }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
