<div>
    <x-account-shell active="subscriptions" :title="__('My subscriptions')">
        @if ($subscriptions->isEmpty())
            <div class="rounded-[var(--radius-card)] border border-line bg-surface p-8 text-center shadow-soft">
                <p class="text-sm text-ink-soft">{{ __('You have no subscriptions yet.') }}</p>
                <p class="mt-1 text-[13px] text-ink-faint">{{ __('Look for “Subscribe & save” on a product to start one.') }}</p>
            </div>
        @else
            <ul class="space-y-3">
                @foreach ($subscriptions as $subscription)
                    @php($product = $subscription->variant?->product)
                    @php($cancelled = $subscription->status === \App\Enums\SubscriptionStatus::Cancelled)
                    @php($paused = $subscription->status === \App\Enums\SubscriptionStatus::Paused)
                    <li class="rounded-[var(--radius-card)] border border-line bg-surface p-4 shadow-soft">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-ink">
                                    @if ($product)
                                        <a href="{{ route('product.show', $product->slug) }}" wire:navigate class="hover:text-emerald">{{ $product->getTranslation('name', app()->getLocale()) }}</a>
                                    @else
                                        {{ __('Product') }}
                                    @endif
                                </p>
                                <p class="mt-1 text-[13px] text-ink-soft">
                                    {{ \App\Enums\SubscriptionInterval::tryFrom($subscription->interval_days)?->label() ?? __('Every :n days', ['n' => $subscription->interval_days]) }}
                                    · {{ __('Qty :n', ['n' => $subscription->qty]) }}
                                    @if ($subscription->variant?->options_label)
                                        · {{ $subscription->variant->options_label }}
                                    @endif
                                </p>
                                @if (! $cancelled && $subscription->next_run_at)
                                    <p class="mt-0.5 text-[13px] text-ink-faint">
                                        {{ $paused ? __('Paused') : __('Next delivery :when', ['when' => $subscription->next_run_at->isPast() ? __('soon') : $subscription->next_run_at->diffForHumans()]) }}
                                    </p>
                                @endif
                            </div>

                            <div class="flex items-center gap-2">
                                <x-ui.badge :variant="match($subscription->status) {
                                    \App\Enums\SubscriptionStatus::Active => 'success',
                                    \App\Enums\SubscriptionStatus::Paused => 'neutral',
                                    default => 'neutral',
                                }">{{ $subscription->status->label() }}</x-ui.badge>
                            </div>
                        </div>

                        @unless ($cancelled)
                            <div class="mt-3 flex flex-wrap gap-2 border-t border-line pt-3">
                                @if ($paused)
                                    <button type="button" wire:click="resume({{ $subscription->id }})"
                                            class="inline-flex min-h-9 items-center rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-[13px] font-semibold text-ink hover:border-emerald hover:text-emerald">{{ __('Resume') }}</button>
                                @else
                                    <button type="button" wire:click="pause({{ $subscription->id }})"
                                            class="inline-flex min-h-9 items-center rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-[13px] font-semibold text-ink hover:border-ink">{{ __('Pause') }}</button>
                                @endif
                                <button type="button" wire:click="cancel({{ $subscription->id }})"
                                        wire:confirm="{{ __('Cancel this subscription?') }}"
                                        class="inline-flex min-h-9 items-center rounded-[var(--radius-control)] px-3 text-[13px] font-semibold text-ink-soft hover:text-danger">{{ __('Cancel') }}</button>
                            </div>
                        @endunless
                    </li>
                @endforeach
            </ul>
        @endif
    </x-account-shell>
</div>
