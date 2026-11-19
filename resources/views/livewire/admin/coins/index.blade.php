<div class="space-y-4">
    <x-ui.section-heading :title="__('Loyalty Coins')" :subtitle="__('Economy overview and per-buyer grant / clawback.')" as="h1" />

    <div class="grid gap-3 sm:grid-cols-2">
        <x-ui.stat :label="__('Coins in circulation')" :value="number_format($circulation)" />
        <x-ui.stat :label="__('Wallets')" :value="number_format($walletCount)" />
    </div>

    <x-ui.table.search :placeholder="__('Search buyer by name or email')" />

    <x-ui.table.per-page />

    <x-ui.card class="overflow-x-auto">
        <x-ui.table-skeleton wire:loading wire:target="search, sortBy, perPage, gotoPage, nextPage, previousPage" />
        <div wire:loading.remove wire:target="search, sortBy, perPage, gotoPage, nextPage, previousPage">
        @if ($wallets->isEmpty())
            <x-ui.empty-state :title="$search !== '' ? __('No wallets match') : __('No wallets yet')" :message="$search !== '' ? __('Try searching for something else.') : __('A buyer gets a wallet when they first earn coins.')" />
        @else
        <table class="w-full min-w-[640px] text-[13px]">
            <thead class="sticky top-0 z-10 bg-surface">
                <tr class="border-b border-line text-left text-ink-soft">
                    <th scope="col" class="px-3 py-6.5 font-medium">{{ __('Buyer') }}</th>
                    <x-ui.table.sortable field="balance" default="desc" :sort="$sort" :direction="$direction" class="px-3 py-6.5 text-right font-medium">{{ __('Balance') }}</x-ui.table.sortable>
                    <x-ui.table.sortable field="lifetime" :sort="$sort" :direction="$direction" class="px-3 py-6.5 text-right font-medium">{{ __('Lifetime') }}</x-ui.table.sortable>
                    <th scope="col" class="px-3 py-6.5 text-right font-medium">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($wallets as $wallet)
                    <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="wallet-{{ $wallet->id }}">
                        <td class="px-3 py-2">
                            <p class="font-medium text-ink">{{ $wallet->user?->name }}</p>
                            <p class="text-[12px] text-ink-faint">{{ $wallet->user?->email }}</p>
                        </td>
                        <td class="px-3 py-2 text-right font-mono font-medium tabular-nums">{{ number_format($wallet->balance) }}</td>
                        <td class="px-3 py-2 text-right font-mono tabular-nums text-ink-soft">{{ number_format($wallet->lifetime_earned) }}</td>
                        <td class="px-3 py-2 text-right">
                            <x-ui.button variant="ghost" type="button" wire:click="openAdjust({{ $wallet->user_id }})">{{ __('Adjust') }}</x-ui.button>
                        </td>
                    </tr>
                    @if ($adjustUserId === $wallet->user_id)
                        <tr class="border-b border-line bg-paper" wire:key="adjust-{{ $wallet->id }}">
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
                                    <x-ui.button variant="plain" type="submit" class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] bg-emerald px-4 text-sm font-medium text-white hover:bg-emerald-deep" target="adjust">{{ __('Apply') }}</x-ui.button>
                                    <x-ui.button variant="plain" type="button" wire:click="$set('adjustUserId', null)" class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] border border-line-strong bg-surface px-4 text-sm font-medium text-ink">{{ __('Cancel') }}</x-ui.button>
                                </form>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
        @endif
        </div>
    </x-ui.card>

    <x-ui.table.footer :rows="$wallets" />
</div>
