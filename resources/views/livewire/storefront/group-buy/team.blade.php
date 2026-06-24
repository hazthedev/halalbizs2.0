<div class="mx-auto w-full max-w-xl px-4 py-10">
    @php($target = $deal->target_size)
    @php($remaining = max(0, $target - $memberCount))
    @php($unlocked = $team->status === \App\Enums\GroupBuyTeamStatus::Unlocked)
    @php($expired = $team->status === \App\Enums\GroupBuyTeamStatus::Expired || (! $unlocked && $team->expires_at->isPast()))

    <div class="overflow-hidden rounded-[var(--radius-card)] border border-brass/30 bg-surface shadow-card">
        <div class="surface-girih flex items-center gap-2 border-b border-brass/25 bg-ink px-5 py-3 text-paper">
            <x-ui.star-mark :size="18" class="text-brass" />
            <h1 class="font-display text-lg font-bold">{{ __('Group buy') }}</h1>
        </div>

        <div class="p-5">
            @if ($product !== null)
                <div class="flex items-center gap-3">
                    <span class="size-16 shrink-0 overflow-hidden rounded-[var(--radius-card)] bg-paper">
                        @if ($product->getFirstMediaUrl('images', 'thumb'))
                            <img src="{{ $product->getFirstMediaUrl('images', 'thumb') }}" alt="" class="size-full object-cover">
                        @endif
                    </span>
                    <div class="min-w-0">
                        <a href="{{ route('product.show', $product->slug) }}" wire:navigate class="line-clamp-2 text-sm font-semibold text-ink hover:text-emerald">
                            {{ $product->getTranslation('name', app()->getLocale()) }}
                        </a>
                        <p class="mt-1 text-lg font-bold text-ink tnum">@price($deal->group_price_sen)
                            <span class="text-[13px] font-normal text-ink-soft">{{ __('group price') }}</span>
                        </p>
                    </div>
                </div>
            @endif

            {{-- Progress --}}
            <div class="mt-5">
                <div class="flex items-center justify-between text-[13px]">
                    <span class="font-medium text-ink">{{ __(':n of :t joined', ['n' => $memberCount, 't' => $target]) }}</span>
                    @unless ($unlocked || $expired)
                        <span class="text-ink-soft">{{ __('expires :when', ['when' => $team->expires_at->diffForHumans()]) }}</span>
                    @endunless
                </div>
                <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-paper">
                    <div class="h-full rounded-full {{ $unlocked ? 'bg-emerald' : 'bg-brass' }}" style="width: {{ min(100, (int) round($memberCount / max(1, $target) * 100)) }}%"></div>
                </div>
            </div>

            {{-- State + action --}}
            <div class="mt-5">
                @if ($unlocked)
                    <div class="rounded-[var(--radius-control)] bg-emerald-tint px-4 py-3 text-center text-[13px] font-semibold text-emerald">
                        {{ __('Unlocked! Add the item to your cart to check out at the group price.') }}
                    </div>
                    @if ($product !== null)
                        <a href="{{ route('product.show', $product->slug) }}" wire:navigate
                           class="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-[var(--radius-control)] bg-emerald px-4 text-sm font-semibold text-white transition-colors hover:bg-emerald-deep">
                            {{ $hasPurchased ? __('View product') : __('Shop now at the group price') }}
                        </a>
                    @endif
                @elseif ($expired)
                    <p class="rounded-[var(--radius-control)] border border-line bg-paper px-4 py-3 text-center text-[13px] text-ink-soft">
                        {{ __('This group didn’t fill in time. Start a fresh one from the product page.') }}
                    </p>
                @elseif ($isMember)
                    <p class="text-center text-[13px] text-ink-soft">{{ __('You’re in! Share this link — :n more to unlock.', ['n' => $remaining]) }}</p>
                    <div class="mt-3 flex gap-2" x-data="{ copied: false }">
                        <input type="text" readonly value="{{ route('group-buy.team', $team->code) }}" x-ref="link"
                               class="block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-paper px-3.5 py-2.5 font-mono text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                        <button type="button" x-on:click="navigator.clipboard.writeText($refs.link.value); copied = true; setTimeout(() => copied = false, 2000)"
                                class="inline-flex min-h-11 shrink-0 items-center rounded-[var(--radius-control)] border border-line-strong bg-surface px-4 text-sm font-semibold text-ink hover:border-ink">
                            <span x-show="! copied">{{ __('Copy') }}</span>
                            <span x-show="copied" x-cloak class="text-emerald">{{ __('Copied!') }}</span>
                        </button>
                    </div>
                @else
                    <button type="button" wire:click="join" wire:loading.attr="disabled" wire:target="join"
                            class="inline-flex min-h-11 w-full items-center justify-center rounded-[var(--radius-control)] bg-emerald px-4 text-sm font-semibold text-white transition-colors hover:bg-emerald-deep disabled:opacity-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-1">
                        {{ __('Join this group') }}
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
