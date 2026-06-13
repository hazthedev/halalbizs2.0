@php use App\Enums\LedgerEntryType; use App\Enums\PayoutStatus; @endphp

<div class="space-y-4">

    <x-ui.section-heading as="h1" :title="__('Earnings')" />

    {{-- ===== Balance cards ===== --}}
    <div class="grid gap-3 sm:grid-cols-3">
        <x-ui.card class="p-4">
            <p class="text-[13px] font-medium text-ink-soft">{{ __('Available') }}</p>
            <p class="mt-1 font-display text-[28px] font-bold leading-tight tabular-nums {{ $availableSen < 0 ? 'text-danger' : 'text-ink' }}">@money($availableSen)</p>
            @if ($availableSen < 0)
                <p class="mt-1 text-[12px] text-danger">{{ __('COD commission owed — future online-payment sales recover it.') }}</p>
            @endif
        </x-ui.card>

        <x-ui.card class="p-4">
            <p class="text-[13px] font-medium text-ink-soft">{{ __('Pending payout') }}</p>
            @if ($pendingPayout !== null)
                <p class="mt-1 font-display text-[28px] font-bold leading-tight tabular-nums text-ink">@money($pendingPayout->amount_sen)</p>
                <p class="mt-1 text-[12px] text-ink-soft"><span class="font-mono">{{ $pendingPayout->payout_no }}</span> · {{ $pendingPayout->status->label() }}</p>
            @else
                <p class="mt-1 font-display text-[28px] font-bold leading-tight text-ink-faint">—</p>
                <p class="mt-1 text-[12px] text-ink-soft">{{ __('No payout in progress.') }}</p>
            @endif
        </x-ui.card>

        <x-ui.card class="p-4">
            <p class="text-[13px] font-medium text-ink-soft">{{ __('Paid out (lifetime)') }}</p>
            <p class="mt-1 font-display text-[28px] font-bold leading-tight tabular-nums text-ink">@money($paidOutSen)</p>
        </x-ui.card>
    </div>

    {{-- ===== Request payout ===== --}}
    <x-ui.card class="p-4">
        <h2 class="text-sm font-semibold">{{ __('Request a payout') }}</h2>

        @if ($pendingPayout !== null)
            <p class="mt-2 text-[13px] text-ink-soft">
                {{ __('A payout is already in progress — :no for :amount. You can request again once it is paid or rejected.', ['no' => $pendingPayout->payout_no, 'amount' => \App\Support\Money::format($pendingPayout->amount_sen)]) }}
            </p>
        @else
            <form wire:submit="requestPayout" class="mt-3 flex flex-wrap items-start gap-2">
                <div>
                    <label for="payout-amount" class="sr-only">{{ __('Amount (RM)') }}</label>
                    <input id="payout-amount" type="text" inputmode="decimal" wire:model="amount" placeholder="120.00"
                           class="block min-h-11 w-36 rounded-[var(--radius-control)] border bg-surface px-3 py-2 text-right font-mono text-sm tabular-nums focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('amount') ? 'border-danger' : 'border-line-strong' }}">
                    @error('amount')
                        <p class="mt-1 text-[13px] text-danger">{{ $message }}</p>
                    @enderror
                </div>
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="requestPayout">{{ __('Request') }}</x-ui.button>
            </form>

            <p class="mt-2 text-[13px] text-ink-faint">
                {{ __('Minimum :min, up to your available balance.', ['min' => \App\Support\Money::format($minSen)]) }}
            </p>
        @endif

        {{-- Bank details snapshot — the payout is wired to this account --}}
        <div class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 border-t border-line pt-3 text-[13px]">
            <span class="text-ink-soft">{{ __('Paid to:') }}</span>
            @if (($bank['account_number'] ?? '') !== '')
                <span class="font-medium text-ink">{{ $bank['bank_name'] ?? '—' }}</span>
                <span class="font-mono text-ink">{{ $bank['account_number'] }}</span>
                <span class="text-ink-soft">{{ $bank['account_name'] ?? '' }}</span>
            @else
                <span class="text-warn">{{ __('No bank details yet.') }}</span>
            @endif
            <a href="{{ route('seller.settings') }}" wire:navigate class="font-medium text-emerald hover:text-emerald-deep">{{ __('Update in settings') }}</a>
        </div>
    </x-ui.card>

    {{-- ===== Ledger ===== --}}
    <x-ui.card class="overflow-x-auto">
        <div class="border-b border-line px-4 py-3">
            <h2 class="text-sm font-semibold">{{ __('Ledger') }}</h2>
        </div>

        @if ($entries->isEmpty())
            <x-ui.empty-state :title="__('No entries yet')" :message="__('Sales land here when an order completes — that\'s when the money becomes yours.')" />
        @else
            <table class="w-full min-w-[640px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Date') }}</th>
                        <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Type') }}</th>
                        <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Description') }}</th>
                        <th scope="col" class="px-4 py-2.5 text-right font-medium">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entries as $entry)
                        @php
                            $chip = match ($entry->type) {
                                LedgerEntryType::Sale => 'sale',
                                LedgerEntryType::Adjustment => 'warn',
                                default => 'neutral', // commission, cod_offset, payout, shipping
                            };
                        @endphp
                        <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="entry-{{ $entry->id }}">
                            <td class="px-4 py-2.5 whitespace-nowrap text-ink-soft">{{ $entry->created_at->format('j M Y, g:ia') }}</td>
                            <td class="px-4 py-2.5"><x-ui.badge :variant="$chip">{{ $entry->type->label() }}</x-ui.badge></td>
                            <td class="px-4 py-2.5">{{ $entry->description }}</td>
                            <td class="px-4 py-2.5 text-right font-mono font-semibold tabular-nums whitespace-nowrap {{ $entry->amount_sen >= 0 ? 'text-emerald' : 'text-ink' }}">{{ $entry->amount_sen >= 0 ? '+' : '' }}@money($entry->amount_sen)</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    @if ($entries->hasPages())
        <div>{{ $entries->links() }}</div>
    @endif

    {{-- ===== Payout history ===== --}}
    <x-ui.card class="overflow-x-auto">
        <div class="border-b border-line px-4 py-3">
            <h2 class="text-sm font-semibold">{{ __('Payout history') }}</h2>
        </div>

        @if ($payouts->isEmpty())
            <x-ui.empty-state :title="__('No payouts yet')" :message="__('Request your available balance above — payouts land here.')" />
        @else
            <table class="w-full min-w-[640px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Payout') }}</th>
                        <th scope="col" class="px-4 py-2.5 text-right font-medium">{{ __('Amount') }}</th>
                        <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Status') }}</th>
                        <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Requested') }}</th>
                        <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Paid') }}</th>
                        <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Reference') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payouts as $payout)
                        @php
                            $pill = match ($payout->status) {
                                PayoutStatus::Requested => 'warn',
                                PayoutStatus::Approved => 'neutral',
                                PayoutStatus::Paid => 'sale',
                                PayoutStatus::Rejected => 'danger',
                            };
                        @endphp
                        <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="payout-{{ $payout->id }}">
                            <td class="px-4 py-2.5 whitespace-nowrap font-mono font-medium">{{ $payout->payout_no }}</td>
                            <td class="px-4 py-2.5 text-right font-mono font-semibold tabular-nums whitespace-nowrap">@money($payout->amount_sen)</td>
                            <td class="px-4 py-2.5"><x-ui.badge :variant="$pill">{{ $payout->status->label() }}</x-ui.badge></td>
                            <td class="px-4 py-2.5 whitespace-nowrap text-ink-soft">{{ $payout->requested_at?->format('j M Y') ?? '—' }}</td>
                            <td class="px-4 py-2.5 whitespace-nowrap text-ink-soft">{{ $payout->paid_at?->format('j M Y') ?? '—' }}</td>
                            {{-- On the rejected path `reference` carries the admin's reason --}}
                            <td class="px-4 py-2.5 {{ $payout->status === PayoutStatus::Rejected ? 'text-ink-soft' : 'font-mono' }}">{{ $payout->reference ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>
</div>
