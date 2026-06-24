<div class="space-y-4">
    <x-ui.section-heading :title="__('Loyalty Coins')" :subtitle="__('Economy overview and per-buyer grant / clawback (M2.1).')" as="h1" />

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="rounded-[var(--radius-card)] border border-line bg-surface p-4 shadow-soft">
            <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-faint">{{ __('Coins in circulation') }}</p>
            <p class="mt-1 font-display text-2xl font-bold tnum">{{ number_format($circulation) }}</p>
        </div>
        <div class="rounded-[var(--radius-card)] border border-line bg-surface p-4 shadow-soft">
            <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-ink-faint">{{ __('Wallets') }}</p>
            <p class="mt-1 font-display text-2xl font-bold tnum">{{ number_format($walletCount) }}</p>
        </div>
    </div>

    <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('Search buyer by name or email…') }}"
           class="block min-h-11 w-full max-w-md rounded-[var(--radius-control)] border border-line-strong bg-surface px-3.5 text-sm">

    <div class="overflow-hidden rounded-[var(--radius-card)] border border-line bg-surface shadow-soft">
        <table class="w-full text-sm">
            <thead class="border-b border-line text-left text-[11px] uppercase tracking-[0.06em] text-ink-faint">
                <tr>
                    <th class="px-4 py-3 font-semibold">{{ __('Buyer') }}</th>
                    <th class="px-4 py-3 text-right font-semibold">{{ __('Balance') }}</th>
                    <th class="px-4 py-3 text-right font-semibold">{{ __('Lifetime') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($wallets as $wallet)
                    <tr wire:key="wallet-{{ $wallet->id }}">
                        <td class="px-4 py-3">
                            <p class="font-medium text-ink">{{ $wallet->user?->name }}</p>
                            <p class="text-xs text-ink-faint">{{ $wallet->user?->email }}</p>
                        </td>
                        <td class="px-4 py-3 text-right font-bold tnum">{{ number_format($wallet->balance) }}</td>
                        <td class="px-4 py-3 text-right text-ink-soft tnum">{{ number_format($wallet->lifetime_earned) }}</td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" wire:click="openAdjust({{ $wallet->user_id }})" class="text-[13px] font-semibold text-emerald hover:text-emerald-deep">{{ __('Adjust') }}</button>
                        </td>
                    </tr>
                    @if ($adjustUserId === $wallet->user_id)
                        <tr class="bg-paper">
                            <td colspan="4" class="px-4 py-3">
                                <form wire:submit="adjust" class="flex flex-wrap items-end gap-3">
                                    <div>
                                        <label class="text-[13px] font-medium">{{ __('Coins (+ grant / − clawback)') }}</label>
                                        <input type="number" wire:model="adjustCoins" class="mt-1 block min-h-11 w-40 rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-sm tnum">
                                        @error('adjustCoins') <p class="mt-1 text-[13px] text-danger">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <label class="text-[13px] font-medium">{{ __('Reason') }}</label>
                                        <input type="text" wire:model="adjustReason" class="mt-1 block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-sm">
                                        @error('adjustReason') <p class="mt-1 text-[13px] text-danger">{{ $message }}</p> @enderror
                                    </div>
                                    <button type="submit" class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] bg-emerald px-4 text-sm font-semibold text-white hover:bg-emerald-deep">{{ __('Apply') }}</button>
                                    <button type="button" wire:click="$set('adjustUserId', null)" class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] border border-line-strong bg-surface px-4 text-sm font-semibold text-ink">{{ __('Cancel') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-ink-soft">{{ __('No wallets yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $wallets->links() }}
</div>
