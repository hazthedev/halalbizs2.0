<div class="space-y-4">

    {{-- Header --}}
    <div class="flex items-center justify-between gap-3">
        <x-ui.section-heading as="h1" :title="__('Orders')" />
        <button type="button" wire:click="exportCsv" class="inline-flex min-h-11 shrink-0 items-center rounded-lg border border-line-strong px-3 text-[13px] font-medium text-ink hover:border-emerald hover:text-emerald">{{ __('Export CSV') }}</button>
    </div>

    {{-- Status tabs — wire:poll.30s keeps the count chips fresh (sound-free badge bump, docs/07 §B).
         Returns tab: sub-orders with an open return request (docs/09 §D). --}}
    <nav wire:poll.30s class="flex gap-1 overflow-x-auto border-b border-line" aria-label="{{ __('Order status') }}">
        @foreach ($tabLabels as $key => $label)
            <button
                type="button"
                wire:click="$set('tab', '{{ $key }}')"
                wire:key="tab-{{ $key }}"
                aria-current="{{ $tab === $key ? 'page' : 'false' }}"
                class="inline-flex min-h-11 shrink-0 items-center gap-1.5 whitespace-nowrap border-b-2 px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $tab === $key ? 'border-ink font-semibold text-ink' : 'border-transparent font-medium text-ink-soft hover:text-ink' }}"
            >
                {{ $label }}
                @if ($counts[$key] > 0)
                    <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-emerald-tint px-1.5 py-0.5 text-[11px] font-semibold tabular-nums text-emerald">{{ $counts[$key] }}</span>
                @endif
            </button>
        @endforeach
    </nav>

    {{-- Table per design §6 — hairline rows, 13px, mono ids/amounts, sticky header --}}
    <x-ui.card class="overflow-x-auto">
        {{-- Row skeletons while the tab switches (targeted: the 30s poll must not flash them) --}}
        <x-ui.table-skeleton wire:loading wire:target="tab" />
        <div wire:loading.remove wire:target="tab">
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
                <thead class="sticky top-14 z-10 bg-surface">
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Sub-order') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Placed') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Buyer') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Items') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Total') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Status') }}</th>
                        @if ($tab === 'new')
                            <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Waiting') }}</th>
                        @endif
                        @if ($tab === 'returns')
                            <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Respond by') }}</th>
                        @endif
                        @if (in_array($tab, ['new', 'to_ship'], true))
                            <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($subOrders as $subOrder)
                        @php
                            $placedAt = $subOrder->order->placed_at ?? $subOrder->order->created_at;
                            $badgeVariant = match ($subOrder->status) {
                                \App\Enums\SubOrderStatus::PendingPayment => 'warn',
                                \App\Enums\SubOrderStatus::Completed => 'sale',
                                \App\Enums\SubOrderStatus::Cancelled,
                                \App\Enums\SubOrderStatus::ReturnRequested,
                                \App\Enums\SubOrderStatus::Returned,
                                \App\Enums\SubOrderStatus::Refunded => 'danger',
                                default => 'neutral',
                            };
                        @endphp
                        <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="sub-order-{{ $subOrder->id }}">
                            <td class="px-3 py-2 whitespace-nowrap">
                                <a href="{{ route('seller.orders.show', $subOrder) }}" wire:navigate
                                   class="inline-flex min-h-11 items-center font-mono font-medium text-ink underline-offset-2 hover:text-emerald hover:underline focus-visible:ring-2 focus-visible:ring-emerald">
                                    {{ $subOrder->sub_order_no }}
                                </a>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-ink-soft">{{ $placedAt->diffForHumans() }}</td>
                            <td class="px-3 py-2"><span class="line-clamp-1 max-w-44">{{ $subOrder->order->user->name }}</span></td>
                            <td class="px-3 py-2 text-right tabular-nums text-ink-soft">{{ $subOrder->items_count }}</td>
                            <td class="px-3 py-2 text-right font-mono font-semibold tabular-nums whitespace-nowrap">@money($subOrder->total_sen)</td>
                            <td class="px-3 py-2"><x-ui.badge :variant="$badgeVariant">{{ $subOrder->status->label() }}</x-ui.badge></td>
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
                                                class="inline-flex min-h-11 items-center whitespace-nowrap rounded-lg border border-ink px-3 text-[13px] font-semibold text-ink hover:bg-paper focus-visible:ring-2 focus-visible:ring-emerald">
                                            {{ __('Confirm & pack') }}
                                        </button>
                                    </div>
                                </td>
                            @elseif ($tab === 'to_ship')
                                <td class="px-3 py-2">
                                    <div class="flex justify-end">
                                        <button type="button" wire:click="openShipModal({{ $subOrder->id }})"
                                                class="inline-flex min-h-11 items-center whitespace-nowrap rounded-lg border border-ink px-3 text-[13px] font-semibold text-ink hover:bg-paper focus-visible:ring-2 focus-visible:ring-emerald">
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

    @if ($subOrders->hasPages())
        <div>{{ $subOrders->links() }}</div>
    @endif

    @include('livewire.seller.orders.partials.ship-modal')
</div>
