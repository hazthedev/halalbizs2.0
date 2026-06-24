<div>
    <x-account-shell active="coins" :title="__('My Coins')">
        {{-- Balance — brass coin ornament, neutral money figure --}}
        <div class="surface-girih relative overflow-hidden rounded-[var(--radius-card)] border border-brass/25 bg-ink p-6 text-paper shadow-card">
            <div class="relative flex items-center gap-4">
                <span class="flex size-14 shrink-0 items-center justify-center rounded-full bg-brass/15 text-brass">
                    <x-ui.star-mark :size="30" />
                </span>
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-brass-tint/70">{{ __('Coin balance') }}</p>
                    <p class="font-display text-4xl font-bold tnum">{{ number_format($wallet->balance) }}</p>
                    <p class="mt-0.5 text-[13px] text-paper/64">{{ __(':n earned all-time', ['n' => number_format($wallet->lifetime_earned)]) }}</p>
                </div>
            </div>

            @if ($expiringAt)
                <p class="relative mt-4 text-[13px] text-brass-tint/80">
                    {{ __('Some coins expire :when — spend them at checkout.', ['when' => $expiringAt->diffForHumans()]) }}
                </p>
            @endif
        </div>

        <div class="mt-5 grid gap-5 sm:grid-cols-2">
            {{-- Daily check-in --}}
            <div class="flex flex-col rounded-[var(--radius-card)] border border-line bg-surface p-5 shadow-soft">
                <h2 class="font-display text-lg font-bold">{{ __('Daily check-in') }}</h2>
                <p class="mt-1 text-[13px] text-ink-soft">{{ __('Check in every day to grow your streak and earn more coins.') }}</p>

                {{-- 7-day streak dots --}}
                <div class="mt-4 flex items-center gap-1.5" role="img" aria-label="{{ __('Current streak: :n days', ['n' => $wallet->checkin_streak]) }}">
                    @php($streakInWeek = $wallet->checkin_streak % 7 ?: ($wallet->checkin_streak > 0 ? 7 : 0))
                    @for ($day = 1; $day <= 7; $day++)
                        <span @class([
                            'flex h-8 flex-1 items-center justify-center rounded-md text-[11px] font-bold tnum',
                            'bg-brass/15 text-brass' => $day <= $streakInWeek,
                            'bg-paper text-ink-faint' => $day > $streakInWeek,
                        ])>{{ $day }}</span>
                    @endfor
                </div>

                <div class="mt-4">
                    @if ($canCheckIn)
                        <button type="button" wire:click="checkIn" wire:loading.attr="disabled" wire:target="checkIn"
                                class="inline-flex min-h-11 w-full items-center justify-center rounded-[var(--radius-control)] bg-emerald px-4 text-sm font-semibold text-white transition-colors hover:bg-emerald-deep disabled:opacity-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-1">
                            {{ __('Check in today') }}
                        </button>
                    @else
                        <p class="inline-flex min-h-11 w-full items-center justify-center rounded-[var(--radius-control)] border border-line bg-paper px-4 text-sm font-medium text-ink-soft">
                            {{ __('Checked in — see you tomorrow!') }}
                        </p>
                    @endif
                </div>
            </div>

            {{-- Spin-to-win --}}
            <div class="flex flex-col rounded-[var(--radius-card)] border border-line bg-surface p-5 shadow-soft">
                <h2 class="font-display text-lg font-bold">{{ __('Spin to win') }}</h2>
                <p class="mt-1 text-[13px] text-ink-soft">{{ __('One free spin a day — coins or a surprise voucher.') }}</p>

                <div class="my-4 flex flex-1 items-center justify-center">
                    <span @class([
                        'flex size-20 items-center justify-center rounded-full border-4 border-brass/30 text-brass',
                        'animate-spin' => false,
                    ]) wire:loading.class="animate-spin" wire:target="spin">
                        <x-ui.star-mark :size="40" />
                    </span>
                </div>

                @if ($canSpin)
                    <button type="button" wire:click="spin" wire:loading.attr="disabled" wire:target="spin"
                            class="inline-flex min-h-11 w-full items-center justify-center rounded-[var(--radius-control)] bg-emerald px-4 text-sm font-semibold text-white transition-colors hover:bg-emerald-deep disabled:opacity-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-1">
                        {{ __('Spin the wheel') }}
                    </button>
                @else
                    <p class="inline-flex min-h-11 w-full items-center justify-center rounded-[var(--radius-control)] border border-line bg-paper px-4 text-sm font-medium text-ink-soft">
                        {{ __('Spun today — come back tomorrow!') }}
                    </p>
                @endif

                @if ($reward)
                    <p class="mt-3 rounded-[var(--radius-control)] bg-brass/10 px-3 py-2 text-center text-[13px] font-semibold text-brass-deep">{{ $reward }}</p>
                @endif
            </div>
        </div>

        {{-- Ledger --}}
        <div class="mt-5 rounded-[var(--radius-card)] border border-line bg-surface shadow-soft">
            <h2 class="border-b border-line px-5 py-4 font-display text-lg font-bold">{{ __('Recent activity') }}</h2>
            @if ($history->isEmpty())
                <p class="px-5 py-8 text-center text-sm text-ink-soft">{{ __('No coin activity yet — start with a daily check-in.') }}</p>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($history as $txn)
                        <li class="flex items-center justify-between gap-3 px-5 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-ink">{{ $txn->description ?: $txn->type->label() }}</p>
                                <p class="text-xs text-ink-faint">{{ $txn->created_at?->format('d M Y') }}</p>
                            </div>
                            <span @class([
                                'shrink-0 text-sm font-bold tnum',
                                'text-emerald' => $txn->amount > 0,
                                'text-ink-soft' => $txn->amount < 0,
                            ])>{{ $txn->amount > 0 ? '+' : '' }}{{ number_format($txn->amount) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </x-account-shell>
</div>
