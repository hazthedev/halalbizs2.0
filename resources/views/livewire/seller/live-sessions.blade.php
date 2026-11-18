<div class="mx-auto w-full max-w-5xl px-4 py-6 lg:py-8">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="font-display text-2xl font-medium">{{ __('Live shopping') }}</h1>
            <p class="mt-1 text-[13px] text-ink-soft">{{ __('Host a shoppable live session with a product rail and a pinned voucher.') }}</p>
        </div>
        <x-ui.button icon="plus" :href="route('seller.live.create')" class="focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-1">
            {{ __('New session') }}
        </x-ui.button>
    </div>

    @if (session('status'))
        <p role="status" class="mt-4 text-sm text-emerald">{{ session('status') }}</p>
    @endif

    <div class="mt-6 space-y-3">
        @forelse ($sessions as $session)
            <div class="rounded-[var(--radius-card)] border border-line bg-surface shadow-soft">
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-ink">{{ $session->title }}</p>
                        <p class="text-[13px] text-ink-soft">{{ trans_choice(':count product|:count products', $session->products->count(), ['count' => $session->products->count()]) }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-ui.badge :variant="$session->status === \App\Enums\LiveSessionStatus::Live ? 'success' : 'neutral'">{{ $session->status->label() }}</x-ui.badge>
                        @if ($session->status === \App\Enums\LiveSessionStatus::Live)
                            <a href="{{ route('live.room', $session->slug) }}" target="_blank" class="text-[13px] font-medium text-emerald hover:text-emerald-deep">{{ __('Open room') }}</a>
                            <button type="button" wire:click="end({{ $session->id }})" class="text-[13px] font-medium text-ink-soft hover:text-danger">{{ __('End') }}</button>
                        @elseif ($session->status === \App\Enums\LiveSessionStatus::Scheduled)
                            <button type="button" wire:click="goLive({{ $session->id }})" class="inline-flex min-h-9 items-center rounded-[var(--radius-control)] bg-emerald px-3 text-[13px] font-medium text-white hover:bg-emerald-deep">{{ __('Go live') }}</button>
                        @endif
                        <x-ui.button variant="ghost" :href="route('seller.live.show', $session)">{{ __('Manage rail') }}</x-ui.button>
                    </div>
                </div>

            </div>
        @empty
            <div class="rounded-[var(--radius-card)] border border-line bg-surface p-10 text-center shadow-soft">
                <p class="text-sm text-ink-soft">{{ __('No live sessions yet.') }}</p>
            </div>
        @endforelse
    </div>
</div>
