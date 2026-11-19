<div class="space-y-4">

    {{-- Header --}}
    <div class="flex items-center justify-between gap-3">
        <x-ui.section-heading as="h1" :title="__('Orders')" />
        <button type="button" wire:click="exportCsv" class="inline-flex min-h-11 shrink-0 items-center rounded-[var(--radius-control)] border border-line-strong px-3 text-[13px] font-medium text-ink hover:border-emerald hover:text-emerald">{{ __('Export CSV') }}</button>
    </div>

    {{-- Status tabs — wire:poll.30s keeps the count chips fresh (sound-free badge bump, docs/07 §B).
         Returns tab: sub-orders with an open return request (docs/09 §D). --}}
    <nav wire:poll.30s class="flex gap-1 overflow-x-auto border-b border-line" aria-label="{{ __('Order status') }}">
        @foreach ($tabLabels as $key => $label)
            <x-ui.tab wire:click="$set('tab', '{{ $key }}')" wire:key="tab-{{ $key }}" :active="$tab === $key" :count="$counts[$key]">{{ $label }}</x-ui.tab>
        @endforeach
    </nav>

    <x-ui.card class="p-3">
        <div class="flex flex-wrap items-center gap-2">
            <input type="search" wire:model.live.debounce.300ms="search" aria-label="{{ __('table.search_orders') }}" placeholder="{{ __('table.search_orders') }}" class="min-h-11 min-w-48 flex-1 rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-[13px] text-ink placeholder:text-ink-soft focus-visible:ring-2 focus-visible:ring-emerald">
            <select wire:model.live="method" aria-label="{{ __('Payment method') }}" class="min-h-11 rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-[13px] text-ink focus-visible:ring-2 focus-visible:ring-emerald">
                <option value="">{{ __('All methods') }}</option>
                @foreach ($methods as $methodCase)
                    <option value="{{ $methodCase->value }}">{{ $methodCase->label() }}</option>
                @endforeach
            </select>
            <label class="text-[13px] text-ink-soft">{{ __('From date') }} <input type="date" wire:model.live="dateFrom" class="min-h-11 rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-ink focus-visible:ring-2 focus-visible:ring-emerald"></label>
            <label class="text-[13px] text-ink-soft">{{ __('To date') }} <input type="date" wire:model.live="dateTo" class="min-h-11 rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-ink focus-visible:ring-2 focus-visible:ring-emerald"></label>
        </div>
    </x-ui.card>

    <x-ui.table.bulk-bar :selected="$selected">
        <x-ui.button wire:click="bulkConfirmAndPack" wire:loading.attr="disabled">{{ __('table.pack_selected') }}</x-ui.button>
    </x-ui.table.bulk-bar>

    {{-- Table per design §6 — hairline rows, 13px, mono ids/amounts, sticky header --}}
    <x-ui.table.per-page />

    <x-ui.card class="overflow-x-auto">
        {{-- Row skeletons while the tab switches (targeted: the 30s poll must not flash them) --}}
        <x-ui.table-skeleton wire:loading wire:target="tab, search, method, dateFrom, dateTo, sortBy, perPage" />
        <div wire:loading.remove wire:target="tab, search, method, dateFrom, dateTo, sortBy, perPage">
        @if ($subOrders->isEmpty())
            @php
                [$emptyTitle, $emptyBody] = match ($tab) {
                    'to_ship' => [__('No orders to ship'), __('Orders land here the moment you confirm and pack them.')],
                    'shipped' => [__('Nothing in transit'), __('Orders you hand to a courier appear here with their tracking numbers.')],
                    'delivered' => [__('Nothing delivered yet'), __('Shipped orders move here once they reach the buyer.')],
                    'completed' => [__('No completed orders yet'), __('Orders complete when the buyer confirms receipt.')],
                    'returns' => [__('No return requests'), __('When a buyer requests a return you have a deadline to respond — those orders land here.')],
                    'cancelled' => [__('No cancelled orders'), __('Cancellations by you or the buyer appear here.')],
                    default => [__('No new orders right now'), __('New orders appear here the moment a buyer pays.')],
                };
            @endphp
            <x-ui.empty-state :title="$emptyTitle" :message="$emptyBody" />
        @else
            <table class="w-full min-w-[760px] text-[13px]">
                <thead class="sticky top-0 z-10 bg-surface">
                    <tr class="border-b border-line text-left text-ink-soft">
                        @if ($tab === 'new')
                            <th scope="col" class="w-10 px-3 py-5"><input type="checkbox" wire:model.live="selectPage" aria-label="{{ __('Select all on this page') }}" class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald"></th>
                        @endif
                        <th scope="col" class="px-3 py-5 font-medium">{{ __('Sub-order') }}</th>
                        <x-ui.table.sortable field="placed" default="desc" :sort="$sort" :direction="$direction" class="px-3 py-5 font-medium">{{ __('Placed') }}</x-ui.table.sortable>
                        <th scope="col" class="px-3 py-5 font-medium">{{ __('Buyer') }}</th>
                        <th scope="col" class="px-3 py-5 text-right font-medium">{{ __('Items') }}</th>
                        <x-ui.table.sortable field="total" :sort="$sort" :direction="$direction" class="px-3 py-5 text-right font-medium">{{ __('Total') }}</x-ui.table.sortable>
                        <th scope="col" class="px-3 py-5 font-medium">{{ __('Status') }}</th>
                        @if ($tab === 'new')
                            <th scope="col" class="px-3 py-5 font-medium">{{ __('Waiting') }}</th>
                        @endif
                        @if ($tab === 'returns')
                            <th scope="col" class="px-3 py-5 font-medium">{{ __('Respond by') }}</th>
                        @endif
                        @if (in_array($tab, ['new', 'to_ship'], true))
                            <th scope="col" class="px-3 py-5 text-right font-medium">{{ __('Actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($subOrders as $subOrder)
                        @php
                            $placedAt = $subOrder->order->placed_at ?? $subOrder->order->created_at;
                        @endphp
                        <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="sub-order-{{ $subOrder->id }}">
                            @if ($tab === 'new')
                                <td class="px-3 py-2"><input type="checkbox" wire:model.live="selected" value="{{ $subOrder->id }}" aria-label="{{ __('Select :name', ['name' => $subOrder->sub_order_no]) }}" class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald"></td>
                            @endif
                            <td class="px-3 py-2 whitespace-nowrap">
                                <a href="{{ route('seller.orders.show', $subOrder) }}" wire:navigate
                                   class="inline-flex min-h-11 items-center font-mono font-medium text-ink underline-offset-2 hover:text-emerald hover:underline focus-visible:ring-2 focus-visible:ring-emerald">
                                    {{ $subOrder->sub_order_no }}
                                </a>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-ink-soft">{{ $placedAt->diffForHumans() }}</td>
                            <td class="px-3 py-2"><span class="line-clamp-1 max-w-44">{{ $subOrder->order->user->name }}</span></td>
                            <td class="px-3 py-2 text-right tabular-nums text-ink-soft">{{ $subOrder->items_count }}</td>
                            <td class="px-3 py-2 text-right font-mono font-medium tabular-nums whitespace-nowrap">@money($subOrder->total_sen)</td>
                            <td class="px-3 py-2"><x-order-status-pill :status="$subOrder->status" /></td>
                            @if ($tab === 'new')
                                @php $hoursWaiting = (int) $placedAt->diffInHours(now()); @endphp
                                <td class="px-3 py-2 whitespace-nowrap">
                                    {{-- Act-fast indicator: hours since the order was placed, warn past :hours h --}}
                                    <x-ui.badge :variant="$hoursWaiting > $actFastHours ? 'warn' : 'neutral'">
                                        {{ trans_choice('{0}Just now|{1}:count hr|[2,*]:count hrs', $hoursWaiting, ['count' => $hoursWaiting]) }}
                                    </x-ui.badge>
                                </td>
                            @endif
                            @if ($tab === 'returns')
                                <td class="px-3 py-2 whitespace-nowrap">
                                    {{-- Deadline pill: overdue requests escalate to admin on the hourly job --}}
                                    @if ($subOrder->returnRequest?->status === \App\Enums\ReturnStatus::Requested)
                                        <x-ui.badge :variant="$subOrder->returnRequest->respond_by->isPast() ? 'danger' : 'warn'">
                                            {{ $subOrder->returnRequest->respond_by->diffForHumans() }}
                                        </x-ui.badge>
                                    @elseif ($subOrder->returnRequest)
                                        <x-return-status-pill :status="$subOrder->returnRequest->status" />
                                    @else
                                        <span class="text-ink-faint">—</span>
                                    @endif
                                </td>
                            @endif
                            @if ($tab === 'new')
                                <td class="px-3 py-2">
                                    <div class="flex justify-end">
                                        <button type="button" wire:click="confirmAndPack({{ $subOrder->id }})" wire:loading.attr="disabled"
                                                class="inline-flex min-h-11 items-center whitespace-nowrap rounded-[var(--radius-control)] border border-ink px-3 text-[13px] font-medium text-ink hover:bg-paper focus-visible:ring-2 focus-visible:ring-emerald">
                                            {{ __('Confirm & pack') }}
                                        </button>
                                    </div>
                                </td>
                            @elseif ($tab === 'to_ship')
                                <td class="px-3 py-2">
                                    <div class="flex justify-end">
                                        <button type="button" wire:click="openShipModal({{ $subOrder->id }})"
                                                class="inline-flex min-h-11 items-center whitespace-nowrap rounded-[var(--radius-control)] border border-ink px-3 text-[13px] font-medium text-ink hover:bg-paper focus-visible:ring-2 focus-visible:ring-emerald">
                                            {{ __('Arrange shipment') }}
                                        </button>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        </div>
    </x-ui.card>

    <x-ui.table.footer :rows="$subOrders" />

    @include('livewire.seller.orders.partials.ship-modal')
</div>
