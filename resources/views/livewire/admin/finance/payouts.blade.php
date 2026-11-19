@php use App\Enums\PayoutStatus; @endphp

{{-- Selection lives in the browser (entangled, sent with the next action):
     ticking a row or the header box makes no server trip. --}}
<div class="space-y-4" x-data="{ selected: $wire.entangle('selected') }">

    {{-- Header --}}
    <x-ui.section-heading :title="__('Payouts')" :subtitle="__('Seller payout queue — approve requests, export the bank CSV, then mark them paid with the bank reference.')" as="h1" />

    {{-- Status tabs --}}
    <nav class="flex gap-1 overflow-x-auto border-b border-line" aria-label="{{ __('Payout status') }}">
        @foreach (PayoutStatus::cases() as $statusCase)
            <x-ui.tab wire:click="$set('tab', '{{ $statusCase->value }}')" wire:key="tab-{{ $statusCase->value }}" :active="$tab === $statusCase->value" :count="$counts[$statusCase->value]">{{ $statusCase->label() }}</x-ui.tab>
        @endforeach
    </nav>

    <x-ui.table.search :placeholder="__('Search payout no. or store')" />

    {{-- Batch bar (approved tab) --}}
    @if ($tab === PayoutStatus::Approved->value && $payouts->isNotEmpty())
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-[13px] text-ink-soft"
               x-text="selected.length === 0 ? @js(trans_choice('{0}No payouts selected|{1}:count payout selected|[2,*]:count payouts selected', 0, ['count' => 0]))
                       : selected.length === 1 ? @js(trans_choice('{0}No payouts selected|{1}:count payout selected|[2,*]:count payouts selected', 1, ['count' => 1]))
                       : @js(trans_choice('{0}No payouts selected|{1}:count payout selected|[2,*]:count payouts selected', 2, ['count' => '__N__'])).replace('__N__', selected.length)">
                {{ trans_choice('{0}No payouts selected|{1}:count payout selected|[2,*]:count payouts selected', count($selected), ['count' => count($selected)]) }}
            </p>
            {{-- Plain action — file download, the CSV streams from the server --}}
            <x-ui.button variant="plain" type="button" wire:click="exportBankCsv" wire:loading.attr="disabled"
                    x-bind:disabled="selected.length === 0" :disabled="count($selected) === 0"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-[var(--radius-control)] bg-emerald px-4 text-sm font-medium text-white hover:bg-emerald-deep active:bg-emerald-night disabled:cursor-not-allowed disabled:opacity-50 focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-2">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                {{ __('Export bank CSV') }}
            </x-ui.button>
        </div>
    @endif

    <x-ui.table.per-page />

    {{-- Table per design §6 --}}
    <x-ui.card class="overflow-x-auto">
        <x-ui.table-skeleton wire:loading wire:target="search, tab, sortBy, perPage, gotoPage, nextPage, previousPage" />
        <div wire:loading.remove wire:target="search, tab, sortBy, perPage, gotoPage, nextPage, previousPage">
        @if ($payouts->isEmpty())
            <x-ui.empty-state
                :title="$search !== '' ? __('No payouts match') : match ($tab) {
                    PayoutStatus::Approved->value => __('Nothing approved yet'),
                    PayoutStatus::Paid->value => __('No payouts paid yet'),
                    PayoutStatus::Rejected->value => __('No rejected payouts'),
                    default => __('No payout requests'),
                }"
                :message="__('Payout requests appear here the moment a seller asks for their available balance.')" />
        @else
            <table class="w-full min-w-[860px] text-[13px]">
                <thead class="sticky top-0 z-10 bg-surface">
                    <tr class="border-b border-line text-left text-ink-soft">
                        @if ($tab === PayoutStatus::Approved->value)
                            <th scope="col" class="w-10 px-3 py-6.5">
                                <input type="checkbox"
                                       data-ids="{{ json_encode($approvedIds) }}"
                                       x-bind:checked="selected.length > 0 && selected.length === JSON.parse($el.dataset.ids).length"
                                       x-on:change="selected = $event.target.checked ? JSON.parse($el.dataset.ids) : []"
                                       aria-label="{{ __('Select all approved payouts') }}"
                                       class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                            </th>
                        @endif
                        <x-ui.table.sortable field="payout" :sort="$sort" :direction="$direction">{{ __('Payout') }}</x-ui.table.sortable>
                        <th scope="col" class="px-3 py-6.5 font-medium">{{ __('Store') }}</th>
                        <x-ui.table.sortable field="amount" :sort="$sort" :direction="$direction" class="text-right">{{ __('Amount') }}</x-ui.table.sortable>
                        <th scope="col" class="px-3 py-6.5 font-medium">{{ __('Bank') }}</th>
                        <x-ui.table.sortable field="requested" default="asc" :sort="$sort" :direction="$direction">{{ __('Requested') }}</x-ui.table.sortable>
                        @if ($tab === PayoutStatus::Paid->value)
                            <th scope="col" class="px-3 py-6.5 font-medium">{{ __('Reference') }}</th>
                            <th scope="col" class="px-3 py-6.5 font-medium">{{ __('Paid at') }}</th>
                        @elseif ($tab === PayoutStatus::Rejected->value)
                            <th scope="col" class="px-3 py-6.5 font-medium">{{ __('Reason') }}</th>
                        @else
                            <th scope="col" class="px-3 py-6.5 text-right font-medium">{{ __('Actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payouts as $payout)
                        @php $bank = $payout->bank_snapshot ?? []; @endphp
                        <tr class="border-b border-line hover:bg-paper {{ ($rejectingId === $payout->id || $payingId === $payout->id) ? '' : 'last:border-b-0' }}" wire:key="payout-{{ $payout->id }}">
                            @if ($tab === PayoutStatus::Approved->value)
                                <td class="px-3 py-2">
                                    <input type="checkbox" x-model="selected" value="{{ $payout->id }}"
                                           aria-label="{{ __('Select :no', ['no' => $payout->payout_no]) }}"
                                           class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                                </td>
                            @endif
                            <td class="px-3 py-2 whitespace-nowrap font-mono font-medium">{{ $payout->payout_no }}</td>
                            <td class="px-3 py-2">{{ $payout->store->name }}</td>
                            <td class="px-3 py-2 text-right font-mono font-medium tabular-nums whitespace-nowrap">@money($payout->amount_sen)</td>
                            <td class="px-3 py-2">
                                <p class="font-medium">{{ $bank['bank_name'] ?? '—' }}</p>
                                <p class="text-[12px] text-ink-soft"><span class="font-mono">{{ $bank['account_number'] ?? '—' }}</span> · {{ $bank['account_name'] ?? '—' }}</p>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-ink-soft">{{ $payout->requested_at?->display()->format('j M Y, g:ia') ?? '—' }}</td>

                            @if ($tab === PayoutStatus::Requested->value)
                                <td class="px-3 py-2">
                                    <div class="flex justify-end gap-2">
                                        <x-ui.button variant="plain" type="button" wire:click="approve({{ $payout->id }})" wire:loading.attr="disabled"
                                                wire:confirm="{{ __('Approve :no? It becomes eligible for the next bank CSV run.', ['no' => $payout->payout_no]) }}"
                                                class="inline-flex min-h-11 items-center whitespace-nowrap rounded-[var(--radius-control)] border border-ink px-3 text-[13px] font-medium text-ink hover:bg-paper disabled:cursor-not-allowed disabled:opacity-50 focus-visible:ring-2 focus-visible:ring-emerald">
                                            {{ __('Approve') }}
                                        </x-ui.button>
                                        <x-ui.button variant="plain" type="button" wire:click="openReject({{ $payout->id }})"
                                                class="inline-flex min-h-11 items-center whitespace-nowrap rounded-[var(--radius-control)] border border-danger px-3 text-[13px] font-medium text-danger hover:bg-danger-tint focus-visible:ring-2 focus-visible:ring-emerald">
                                            {{ __('Reject') }}
                                        </x-ui.button>
                                    </div>
                                </td>
                            @elseif ($tab === PayoutStatus::Approved->value)
                                <td class="px-3 py-2">
                                    <div class="flex justify-end">
                                        <x-ui.button variant="plain" type="button" wire:click="openMarkPaid({{ $payout->id }})"
                                                class="inline-flex min-h-11 items-center whitespace-nowrap rounded-[var(--radius-control)] border border-ink px-3 text-[13px] font-medium text-ink hover:bg-paper focus-visible:ring-2 focus-visible:ring-emerald">
                                            {{ __('Mark paid') }}
                                        </x-ui.button>
                                    </div>
                                </td>
                            @elseif ($tab === PayoutStatus::Paid->value)
                                <td class="px-3 py-2 whitespace-nowrap font-mono">{{ $payout->reference ?? '—' }}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-ink-soft">{{ $payout->paid_at?->display()->format('j M Y, g:ia') ?? '—' }}</td>
                            @elseif ($tab === PayoutStatus::Rejected->value)
                                {{-- reference carries the rejection reason on this path --}}
                                <td class="px-3 py-2 text-ink-soft">{{ $payout->reference ?? '—' }}</td>
                            @endif
                        </tr>

                        {{-- Inline reject form --}}
                        @if ($rejectingId === $payout->id)
                            <tr class="border-b border-line last:border-b-0 bg-paper" wire:key="payout-reject-{{ $payout->id }}">
                                <td colspan="6" class="px-3 py-3">
                                    <div class="flex flex-wrap items-end gap-2">
                                        <div class="min-w-64 flex-1">
                                            <label for="reject-reason-{{ $payout->id }}" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Rejection reason') }}</label>
                                            <input id="reject-reason-{{ $payout->id }}" type="text" wire:model="rejectReason"
                                                   placeholder="{{ __('e.g. Bank details do not match the verified documents') }}"
                                                   class="block min-h-11 w-full rounded-[var(--radius-control)] border bg-surface px-3 text-[13px] text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('rejectReason') ? 'border-danger' : 'border-line-strong' }}">
                                            @error('rejectReason')
                                                <p class="mt-1 text-[13px] text-danger">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <x-ui.button variant="plain" type="button" wire:click="reject" wire:loading.attr="disabled"
                                                wire:confirm="{{ __('Reject :no? Funds return to the seller\'s available balance.', ['no' => $payout->payout_no]) }}"
                                                class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] border border-danger px-4 text-[13px] font-medium text-danger hover:bg-danger-tint disabled:cursor-not-allowed disabled:opacity-50 focus-visible:ring-2 focus-visible:ring-emerald">
                                            {{ __('Reject payout') }}
                                        </x-ui.button>
                                        <x-ui.button variant="plain" type="button" wire:click="$set('rejectingId', null)"
                                                class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-3 text-[13px] font-medium text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">
                                            {{ __('Cancel') }}
                                        </x-ui.button>
                                    </div>
                                </td>
                            </tr>
                        @endif

                        {{-- Inline mark-paid form --}}
                        @if ($payingId === $payout->id)
                            <tr class="border-b border-line last:border-b-0 bg-paper" wire:key="payout-paid-{{ $payout->id }}">
                                <td colspan="7" class="px-3 py-3">
                                    <div class="flex flex-wrap items-end gap-2">
                                        <div class="min-w-64 flex-1">
                                            <label for="paid-reference-{{ $payout->id }}" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Bank reference') }}</label>
                                            <input id="paid-reference-{{ $payout->id }}" type="text" wire:model="paidReference"
                                                   placeholder="{{ __('Transfer reference from the bank run') }}"
                                                   class="block min-h-11 w-full rounded-[var(--radius-control)] border bg-surface px-3 font-mono text-[13px] text-ink placeholder:font-sans placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('paidReference') ? 'border-danger' : 'border-line-strong' }}">
                                            @error('paidReference')
                                                <p class="mt-1 text-[13px] text-danger">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <x-ui.button variant="plain" type="button" wire:click="markPaid" wire:loading.attr="disabled"
                                                wire:confirm="{{ __('Mark :no as paid? This is the final state.', ['no' => $payout->payout_no]) }}"
                                                class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] border border-ink px-4 text-[13px] font-medium text-ink hover:bg-paper disabled:cursor-not-allowed disabled:opacity-50 focus-visible:ring-2 focus-visible:ring-emerald">
                                            {{ __('Mark paid') }}
                                        </x-ui.button>
                                        <x-ui.button variant="plain" type="button" wire:click="$set('payingId', null)"
                                                class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-3 text-[13px] font-medium text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">
                                            {{ __('Cancel') }}
                                        </x-ui.button>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        @endif
        </div>
    </x-ui.card>

    <x-ui.table.footer :rows="$payouts" />
</div>
