<div>
    <x-account-shell active="affiliate" :title="__('Creator program')">
        @if ($affiliate === null)
            {{-- Enrolment --}}
            <div class="rounded-[var(--radius-card)] border border-line bg-surface p-6 shadow-soft">
                <div class="flex items-start gap-4">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-brass/15 text-brass">
                        <x-ui.star-mark :size="26" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="font-display text-xl font-bold">{{ __('Earn by sharing what you love') }}</h2>
                        <p class="mt-1 text-[13px] text-ink-soft">
                            {{ __('Join the creator program for your own share link. Earn :rate% commission whenever a shopper you referred completes an order.', ['rate' => rtrim(rtrim(number_format(config('affiliate.commission_rate_bp', 500) / 100, 2), '0'), '.')]) }}
                        </p>
                    </div>
                </div>
                <button type="button" wire:click="enroll" wire:loading.attr="disabled" wire:target="enroll"
                        class="mt-5 inline-flex min-h-11 items-center justify-center rounded-[var(--radius-control)] bg-emerald px-5 text-sm font-semibold text-white transition-colors hover:bg-emerald-deep disabled:opacity-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-1">
                    {{ __('Join the creator program') }}
                </button>
            </div>
        @else
            {{-- Stats --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-[var(--radius-card)] border border-line bg-surface p-4 shadow-soft">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-faint">{{ __('Confirmed earnings') }}</p>
                    <p class="mt-1 font-display text-2xl font-bold text-emerald tnum">@money($earningsSen)</p>
                </div>
                <div class="rounded-[var(--radius-card)] border border-line bg-surface p-4 shadow-soft">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-faint">{{ __('Available to withdraw') }}</p>
                    <p class="mt-1 font-display text-2xl font-bold tnum">@money($availableSen)</p>
                </div>
                <div class="rounded-[var(--radius-card)] border border-line bg-surface p-4 shadow-soft">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-faint">{{ __('Link clicks') }}</p>
                    <p class="mt-1 font-display text-2xl font-bold tnum">{{ number_format($affiliate->clicks) }}</p>
                </div>
                <div class="rounded-[var(--radius-card)] border border-line bg-surface p-4 shadow-soft">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-faint">{{ __('Referred sales') }}</p>
                    <p class="mt-1 font-display text-2xl font-bold tnum">{{ number_format($referrals->count()) }}</p>
                </div>
            </div>

            {{-- Share link --}}
            <div class="mt-5 rounded-[var(--radius-card)] border border-line bg-surface p-5 shadow-soft" x-data="{ copied: false }">
                <h2 class="font-display text-lg font-bold">{{ __('Your share link') }}</h2>
                <p class="mt-1 text-[13px] text-ink-soft">{{ __('Share this anywhere. We attribute orders for :days days after a click.', ['days' => config('affiliate.cookie_days', 30)]) }}</p>
                <div class="mt-3 flex gap-2">
                    <input type="text" readonly value="{{ $link }}" x-ref="link"
                           class="block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-paper px-3.5 py-2.5 font-mono text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                    <button type="button"
                            x-on:click="navigator.clipboard.writeText($refs.link.value); copied = true; setTimeout(() => copied = false, 2000)"
                            class="inline-flex min-h-11 shrink-0 items-center rounded-[var(--radius-control)] border border-line-strong bg-surface px-4 text-sm font-semibold text-ink hover:border-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                        <span x-show="! copied">{{ __('Copy') }}</span>
                        <span x-show="copied" x-cloak class="text-emerald">{{ __('Copied!') }}</span>
                    </button>
                </div>
            </div>

            {{-- Withdraw --}}
            <div class="mt-5 rounded-[var(--radius-card)] border border-line bg-surface p-5 shadow-soft">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-display text-lg font-bold">{{ __('Withdraw earnings') }}</h2>
                        <p class="mt-1 text-[13px] text-ink-soft">{{ __('Minimum :min. Paid to your bank within a few business days.', ['min' => \App\Support\Money::format($minPayoutSen)]) }}</p>
                    </div>
                    @if ($availableSen >= $minPayoutSen)
                        <button type="button" wire:click="$toggle('showWithdraw')"
                                class="inline-flex min-h-11 items-center justify-center rounded-[var(--radius-control)] bg-emerald px-4 text-sm font-semibold text-white hover:bg-emerald-deep focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-1">
                            {{ $showWithdraw ? __('Close') : __('Request withdrawal') }}
                        </button>
                    @else
                        <span class="text-[13px] text-ink-faint">{{ __(':amount available', ['amount' => \App\Support\Money::format($availableSen)]) }}</span>
                    @endif
                </div>

                @if ($showWithdraw)
                    <form wire:submit="requestPayout" class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="wd-amount" class="text-[13px] font-medium">{{ __('Amount (RM)') }}</label>
                            <input id="wd-amount" type="text" wire:model="withdrawAmount" inputmode="decimal" placeholder="0.00"
                                   class="mt-1 block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-paper px-3 text-sm tnum">
                            @error('withdrawAmount') <p class="mt-1 text-[13px] text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="wd-bank" class="text-[13px] font-medium">{{ __('Bank account details') }}</label>
                            <input id="wd-bank" type="text" wire:model="bankDetails" placeholder="{{ __('Bank · name · account no.') }}"
                                   class="mt-1 block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-paper px-3 text-sm">
                            @error('bankDetails') <p class="mt-1 text-[13px] text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-[var(--radius-control)] bg-emerald px-5 text-sm font-semibold text-white hover:bg-emerald-deep">{{ __('Submit request') }}</button>
                        </div>
                    </form>
                @endif

                @if ($payouts->isNotEmpty())
                    <ul class="mt-4 divide-y divide-line border-t border-line">
                        @foreach ($payouts as $payout)
                            <li class="flex items-center justify-between gap-3 py-2.5 text-[13px]">
                                <span class="text-ink-soft">{{ $payout->requested_at?->format('d M Y') }}</span>
                                <span class="font-semibold text-ink tnum">@money($payout->amount_sen)</span>
                                <x-ui.badge :variant="match($payout->status) {
                                    \App\Enums\AffiliatePayoutStatus::Paid => 'success',
                                    \App\Enums\AffiliatePayoutStatus::Rejected => 'danger',
                                    default => 'neutral',
                                }">{{ $payout->status->label() }}</x-ui.badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Referrals --}}
            <div class="mt-5 rounded-[var(--radius-card)] border border-line bg-surface shadow-soft">
                <h2 class="border-b border-line px-5 py-4 font-display text-lg font-bold">{{ __('Recent commissions') }}</h2>
                @if ($referrals->isEmpty())
                    <p class="px-5 py-8 text-center text-sm text-ink-soft">{{ __('No commissions yet — share your link to get started.') }}</p>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($referrals as $referral)
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-ink">{{ $referral->subOrder?->sub_order_no ?? __('Order') }}</p>
                                    <p class="text-xs text-ink-faint">{{ $referral->created_at?->format('d M Y') }} · {{ $referral->status->label() }}</p>
                                </div>
                                <span class="shrink-0 text-sm font-bold text-emerald tnum">+@money($referral->commission_sen)</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    </x-account-shell>
</div>
