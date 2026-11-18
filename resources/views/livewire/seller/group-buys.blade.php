<div class="mx-auto w-full max-w-5xl px-4 py-6 lg:py-8">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="font-display text-2xl font-medium">{{ __('Group buys') }}</h1>
            <p class="mt-1 text-[13px] text-ink-soft">{{ __('Offer a lower price that unlocks when shoppers team up.') }}</p>
        </div>
        <x-ui.button icon="plus" :href="route('seller.group-buys.create')" class="focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-1">
            {{ __('Create deal') }}
        </x-ui.button>
    </div>

    @if (session('status'))
        <p role="status" class="mt-4 text-sm text-emerald">{{ session('status') }}</p>
    @endif

    <div class="mt-6 overflow-hidden rounded-[var(--radius-card)] border border-line bg-surface shadow-soft">
        @if ($deals->isEmpty())
            <p class="px-5 py-10 text-center text-sm text-ink-soft">{{ __('No group-buy deals yet.') }}</p>
        @else
            <ul class="divide-y divide-line">
                @foreach ($deals as $deal)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-ink">{{ $deal->product?->getTranslation('name', 'en') }}</p>
                            <p class="text-[13px] text-ink-soft">
                                {{ $deal->variant?->options_label ?: __('Default') }} ·
                                <span class="font-medium text-ink">@money($deal->group_price_sen)</span> ·
                                {{ __(':n people', ['n' => $deal->target_size]) }} ·
                                {{ __(':n teams', ['n' => $deal->teams_count]) }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <x-ui.badge :variant="$deal->status === \App\Enums\GroupBuyStatus::Active ? 'success' : 'neutral'">{{ $deal->status->label() }}</x-ui.badge>
                            @if ($deal->status === \App\Enums\GroupBuyStatus::Active)
                                <button type="button" wire:click="end({{ $deal->id }})" class="text-[13px] font-medium text-ink-soft hover:text-danger">{{ __('End') }}</button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
