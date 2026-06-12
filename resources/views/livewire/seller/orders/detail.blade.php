@php
    use App\Enums\SubOrderStatus;

    $placedAt = $subOrder->order->placed_at ?? $subOrder->order->created_at;
    $address = $subOrder->order->shipping_address ?? [];
    $badgeVariant = match ($subOrder->status) {
        SubOrderStatus::PendingPayment => 'warn',
        SubOrderStatus::Completed => 'sale',
        SubOrderStatus::Cancelled,
        SubOrderStatus::ReturnRequested,
        SubOrderStatus::Returned,
        SubOrderStatus::Refunded => 'danger',
        default => 'neutral',
    };
@endphp

<div class="space-y-4">

    {{-- Header --}}
    <div>
        <a href="{{ route('seller.orders.index') }}" wire:navigate
           class="inline-flex min-h-11 items-center gap-1.5 text-[13px] font-medium text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">
            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
            {{ __('Orders') }}
        </a>
        <div class="mt-1 flex flex-wrap items-center gap-3">
            <h1 class="font-mono text-xl font-semibold text-ink">{{ $subOrder->sub_order_no }}</h1>
            <x-ui.badge :variant="$badgeVariant">{{ $subOrder->status->label() }}</x-ui.badge>
        </div>
        <p class="mt-1 text-[13px] text-ink-soft">
            {{ __('Placed :date', ['date' => $placedAt->format('j M Y, g:ia')]) }}
            · {{ __('Buyer: :name', ['name' => $subOrder->order->user->name]) }}
        </p>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">

        {{-- Left: items + timeline --}}
        <div class="space-y-4 lg:col-span-2">

            {{-- Items (snapshots are sacred — never live product data) --}}
            <x-ui.card class="overflow-x-auto">
                <div class="border-b border-line px-4 py-3">
                    <h2 class="text-sm font-semibold">{{ __('Items') }}</h2>
                </div>
                <table class="w-full min-w-[520px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-ink-soft">
                            <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Product') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-right font-medium">{{ __('Unit price') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-right font-medium">{{ __('Qty') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-right font-medium">{{ __('Line total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($subOrder->items as $item)
                            <tr class="border-b border-line last:border-b-0" wire:key="item-{{ $item->id }}">
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-3">
                                        @if ($url = $item->product?->getFirstMediaUrl('images', 'thumb'))
                                            <img src="{{ $url }}" alt="{{ trim($item->product_name.' '.($item->variant_label ?? '')) }}"
                                                 class="size-10 shrink-0 rounded-lg border border-line bg-paper object-cover">
                                        @else
                                            <span class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-line bg-paper text-ink-faint">
                                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 19.5h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Z"/></svg>
                                            </span>
                                        @endif
                                        <div class="min-w-0">
                                            <p class="line-clamp-2 font-medium text-ink">{{ $item->product_name }}</p>
                                            @if ($item->variant_label)
                                                <p class="text-[12px] text-ink-soft">{{ $item->variant_label }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono tabular-nums whitespace-nowrap">@money($item->unit_price_sen)</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ $item->qty }}</td>
                                <td class="px-4 py-2.5 text-right font-mono font-semibold tabular-nums whitespace-nowrap">@money($item->line_total_sen)</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-ui.card>

            {{-- TODO(M4 follow-up): buyer's per-store note — CheckoutService::place() accepts
                 $sellerNotes but does not persist them yet (do NOT modify the service here).
                 Render the note card once checkout stores it. --}}

            {{-- Return request (docs/09 §D) — accept / dispute while requested,
                 confirm receipt once accepted. All status moves via the service. --}}
            @if ($returnRequest = $subOrder->returnRequest)
                <x-ui.card class="p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold">{{ __('Return request') }}</h2>
                        <x-return-status-pill :status="$returnRequest->status" />
                    </div>

                    <dl class="mt-3 space-y-1.5 text-[13px]">
                        <div class="flex gap-2">
                            <dt class="shrink-0 text-ink-soft">{{ __('Reason') }}:</dt>
                            <dd class="text-ink">{{ $returnRequest->reason?->label ?? '—' }}</dd>
                        </div>
                        @if ($returnRequest->description)
                            <div class="flex gap-2">
                                <dt class="shrink-0 text-ink-soft">{{ __('Details') }}:</dt>
                                <dd class="text-ink">{{ $returnRequest->description }}</dd>
                            </div>
                        @endif
                        @if ($returnRequest->seller_response)
                            <div class="flex gap-2">
                                <dt class="shrink-0 text-ink-soft">{{ __('Your dispute') }}:</dt>
                                <dd class="text-ink">{{ $returnRequest->seller_response }}</dd>
                            </div>
                        @endif
                        <div class="flex gap-2">
                            <dt class="shrink-0 text-ink-soft">{{ __('Requested') }}:</dt>
                            <dd class="text-ink">{{ $returnRequest->created_at->format('j M Y, g:ia') }}</dd>
                        </div>
                    </dl>

                    @if ($returnRequest->getMedia('photos')->isNotEmpty())
                        <ul class="mt-3 flex flex-wrap gap-2">
                            @foreach ($returnRequest->getMedia('photos') as $photo)
                                <li wire:key="return-photo-{{ $photo->id }}">
                                    <a href="{{ $photo->getUrl() }}" target="_blank" rel="noopener"
                                       class="block size-16 overflow-hidden rounded-lg border border-line bg-paper focus-visible:ring-2 focus-visible:ring-emerald">
                                        <img src="{{ $photo->getUrl() }}" alt="{{ __('Return photo :n', ['n' => $loop->iteration]) }}" class="size-full object-cover" loading="lazy">
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($subOrder->status === \App\Enums\SubOrderStatus::ReturnRequested && $returnRequest->status === \App\Enums\ReturnStatus::Requested)
                        <div class="mt-3 rounded-lg border {{ $returnRequest->respond_by->isPast() ? 'border-danger/40 bg-danger-tint/40' : 'border-warn/40 bg-warn-tint/40' }} p-3">
                            <p class="text-[13px] font-medium text-ink">
                                {{ __('Respond by :date (:relative) — unanswered requests escalate to admin automatically.', [
                                    'date' => $returnRequest->respond_by->format('j M Y, g:ia'),
                                    'relative' => $returnRequest->respond_by->diffForHumans(),
                                ]) }}
                            </p>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" wire:click="acceptReturn" wire:loading.attr="disabled"
                                    wire:confirm="{{ __('Accept this return? The buyer ships the item back to you, then you confirm receipt.') }}"
                                    class="inline-flex min-h-11 items-center justify-center rounded-lg bg-emerald px-4 text-sm font-semibold text-white hover:bg-emerald-deep active:bg-emerald-night disabled:cursor-not-allowed disabled:opacity-50 focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-2">
                                {{ __('Accept return') }}
                            </button>
                            <button type="button" wire:click="$set('disputing', true)"
                                    class="inline-flex min-h-11 items-center justify-center rounded-lg border border-danger px-4 text-sm font-semibold text-danger hover:bg-danger-tint focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-2">
                                {{ __('Dispute') }}
                            </button>
                        </div>

                        @if ($disputing)
                            <div class="mt-3 space-y-2 rounded-lg border border-danger/40 bg-danger-tint/40 p-3">
                                <label for="dispute-reason" class="block text-[13px] font-medium text-ink">{{ __('Why are you disputing this return?') }}</label>
                                <textarea id="dispute-reason" wire:model="disputeReason" rows="3"
                                          placeholder="{{ __('Explain what the buyer\'s photos or description get wrong.') }}"
                                          class="block w-full rounded-lg border bg-surface px-3 py-2 text-[13px] text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('disputeReason') ? 'border-danger' : 'border-line-strong' }}"></textarea>
                                @error('disputeReason')
                                    <p class="text-[13px] text-danger">{{ $message }}</p>
                                @enderror
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" wire:click="disputeReturn" wire:loading.attr="disabled"
                                            wire:confirm="{{ __('Dispute this return? It goes straight to the marketplace team for a decision.') }}"
                                            class="inline-flex min-h-11 items-center justify-center rounded-lg bg-danger px-4 text-sm font-semibold text-white hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50 focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-2">
                                        {{ __('Submit dispute') }}
                                    </button>
                                    <button type="button" wire:click="$set('disputing', false)"
                                            class="inline-flex min-h-11 items-center justify-center rounded-lg px-4 text-sm font-semibold text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">
                                        {{ __('Never mind') }}
                                    </button>
                                </div>
                            </div>
                        @endif
                    @elseif ($subOrder->status === \App\Enums\SubOrderStatus::ReturnRequested && $returnRequest->status === \App\Enums\ReturnStatus::Accepted)
                        <p class="mt-3 text-[13px] text-ink-soft">{{ __('Waiting for the buyer to ship the item back (manual for now). Confirm once it arrives.') }}</p>
                        <button type="button" wire:click="confirmItemReceived" wire:loading.attr="disabled"
                                wire:confirm="{{ __('Confirm the returned item arrived? The refund is then processed by the marketplace team.') }}"
                                class="mt-2 inline-flex min-h-11 items-center justify-center rounded-lg bg-emerald px-4 text-sm font-semibold text-white hover:bg-emerald-deep active:bg-emerald-night disabled:cursor-not-allowed disabled:opacity-50 focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-2">
                            {{ __('Item received') }}
                        </button>
                    @elseif (in_array($returnRequest->status, [\App\Enums\ReturnStatus::Disputed, \App\Enums\ReturnStatus::Escalated], true))
                        <p class="mt-3 text-[13px] text-ink-soft">{{ __('With the marketplace team for review — you will be notified of the outcome.') }}</p>
                    @elseif ($subOrder->status === \App\Enums\SubOrderStatus::Returned && $returnRequest->status === \App\Enums\ReturnStatus::Accepted)
                        <p class="mt-3 text-[13px] text-ink-soft">{{ __('Item received back — awaiting the refund from the marketplace team.') }}</p>
                    @endif
                </x-ui.card>
            @endif

            {{-- Status timeline (compact seller-side variant of the buyer timeline) --}}
            <x-ui.card class="p-4">
                <h2 class="text-sm font-semibold">{{ __('Timeline') }}</h2>
                <ol class="mt-3 space-y-3">
                    @foreach ($subOrder->statusHistories as $history)
                        <li class="flex gap-3" wire:key="history-{{ $history->id }}">
                            <span class="mt-1 flex flex-col items-center self-stretch">
                                <span class="size-2 shrink-0 rounded-full {{ $loop->last ? 'bg-emerald' : 'bg-line-strong' }}"></span>
                                @unless ($loop->last)
                                    <span class="mt-1 w-px flex-1 bg-line"></span>
                                @endunless
                            </span>
                            <div class="pb-1">
                                <p class="text-[13px] font-medium text-ink">{{ SubOrderStatus::from($history->to_status)->label() }}</p>
                                <p class="text-[12px] text-ink-soft">
                                    {{ $history->created_at->format('j M Y, g:ia') }} · {{ $history->actor_type->label() }}
                                </p>
                                @if ($history->note)
                                    <p class="mt-0.5 text-[12px] text-ink-soft">{{ $history->note }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </x-ui.card>
        </div>

        {{-- Right: actions, address, totals --}}
        <div class="space-y-4">

            {{-- Actions --}}
            <x-ui.card class="space-y-3 p-4">
                <h2 class="text-sm font-semibold">{{ __('Actions') }}</h2>

                @if ($subOrder->status === SubOrderStatus::Confirmed)
                    <button type="button" wire:click="confirmAndPack" wire:loading.attr="disabled"
                            class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-emerald px-4 text-sm font-semibold text-white hover:bg-emerald-deep active:bg-emerald-night disabled:cursor-not-allowed disabled:opacity-50 focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-2">
                        {{ __('Confirm & pack') }}
                    </button>

                    <div class="space-y-2 border-t border-line pt-3">
                        <label for="cancel-reason" class="block text-[13px] font-medium text-ink">{{ __('Cancellation reason') }}</label>
                        <select id="cancel-reason" wire:model="cancelReasonId"
                                class="block min-h-11 w-full rounded-lg border bg-surface px-3 py-2 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('cancelReasonId') ? 'border-danger' : 'border-line-strong' }}">
                            <option value="">{{ __('Select a reason') }}</option>
                            @foreach ($cancellationReasons as $reason)
                                <option value="{{ $reason->id }}">{{ $reason->getTranslation('label', app()->getLocale()) }}</option>
                            @endforeach
                        </select>
                        @error('cancelReasonId')
                            <p class="text-[13px] text-danger">{{ $message }}</p>
                        @enderror
                        <button type="button" wire:click="cancelOrder" wire:loading.attr="disabled"
                                wire:confirm="{{ __('Cancel this order? Items return to stock and the buyer is notified — this cannot be undone.') }}"
                                class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-danger px-4 text-sm font-semibold text-danger hover:bg-danger-tint disabled:cursor-not-allowed disabled:opacity-50 focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-2">
                            {{ __('Cancel order') }}
                        </button>
                    </div>

                @elseif ($subOrder->status === SubOrderStatus::Processing)
                    <button type="button" wire:click="openShipModal({{ $subOrder->id }})"
                            class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-emerald px-4 text-sm font-semibold text-white hover:bg-emerald-deep active:bg-emerald-night focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-2">
                        {{ __('Arrange shipment') }}
                    </button>

                @elseif ($subOrder->status === SubOrderStatus::Shipped)
                    <div class="rounded-lg border border-line bg-paper p-3">
                        <p class="text-[12px] font-medium text-ink-soft">{{ __('Tracking') }}</p>
                        <p class="mt-0.5 text-[13px] font-medium text-ink">{{ $subOrder->tracking_courier }}</p>
                        <p class="font-mono text-[13px] text-ink">{{ $subOrder->tracking_no }}</p>
                    </div>
                    <button type="button" wire:click="markDelivered" wire:loading.attr="disabled"
                            class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-emerald px-4 text-sm font-semibold text-white hover:bg-emerald-deep active:bg-emerald-night disabled:cursor-not-allowed disabled:opacity-50 focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-2">
                        {{ __('Mark delivered') }}
                    </button>

                @elseif (in_array($subOrder->status, [SubOrderStatus::Delivered, SubOrderStatus::Completed], true))
                    @if ($subOrder->tracking_no)
                        <div class="rounded-lg border border-line bg-paper p-3">
                            <p class="text-[12px] font-medium text-ink-soft">{{ __('Tracking') }}</p>
                            <p class="mt-0.5 text-[13px] font-medium text-ink">{{ $subOrder->tracking_courier }}</p>
                            <p class="font-mono text-[13px] text-ink">{{ $subOrder->tracking_no }}</p>
                        </div>
                    @endif
                    @if ($subOrder->status === SubOrderStatus::Delivered && $subOrder->auto_complete_at)
                        <p class="text-[13px] text-ink-soft">{{ __('Completes automatically on :date unless the buyer acts first.', ['date' => $subOrder->auto_complete_at->format('j M Y')]) }}</p>
                    @endif
                    @if ($subOrder->status === SubOrderStatus::Completed && $subOrder->completed_at)
                        <p class="text-[13px] text-ink-soft">{{ __('Completed on :date.', ['date' => $subOrder->completed_at->format('j M Y')]) }}</p>
                    @endif

                @elseif ($subOrder->status === SubOrderStatus::Cancelled)
                    <p class="text-[13px] text-ink-soft">
                        {{ __('Cancelled on :date.', ['date' => $subOrder->cancelled_at?->format('j M Y') ?? '—']) }}
                        @if ($subOrder->cancel_reason)
                            {{ __('Reason: :reason', ['reason' => $subOrder->cancel_reason]) }}
                        @endif
                    </p>
                @endif

                <div class="border-t border-line pt-3">
                    {{-- Plain anchor (file download — no wire:navigate) --}}
                    <a href="{{ route('seller.orders.packing-slip', $subOrder) }}"
                       class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg px-4 text-sm font-semibold text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659"/></svg>
                        {{ __('Print packing slip') }}
                    </a>
                </div>
            </x-ui.card>

            {{-- Address (order snapshot — sacred) --}}
            <x-ui.card class="p-4">
                <h2 class="text-sm font-semibold">{{ __('Ship to') }}</h2>
                <div class="mt-2 space-y-0.5 text-[13px]">
                    <p class="font-medium text-ink">{{ $address['recipient_name'] ?? '—' }}</p>
                    @if (! empty($address['phone']))
                        <p class="text-ink-soft">{{ $address['phone'] }}</p>
                    @endif
                    <p class="text-ink-soft">{{ $address['line1'] ?? '' }}</p>
                    @if (! empty($address['line2']))
                        <p class="text-ink-soft">{{ $address['line2'] }}</p>
                    @endif
                    <p class="text-ink-soft">{{ trim(($address['postcode'] ?? '').' '.($address['city'] ?? '')) }}</p>
                    <p class="text-ink-soft">{{ $address['state'] ?? '' }}</p>
                </div>
            </x-ui.card>

            {{-- Totals --}}
            <x-ui.card class="p-4">
                <h2 class="text-sm font-semibold">{{ __('Totals') }}</h2>
                <dl class="mt-3 space-y-1.5 text-[13px]">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-ink-soft">{{ __('Items subtotal') }}</dt>
                        <dd class="font-mono tabular-nums">@money($subOrder->items_subtotal_sen)</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-ink-soft">{{ __('Shipping fee') }}</dt>
                        <dd class="font-mono tabular-nums">@money($subOrder->shipping_fee_sen)</dd>
                    </div>
                    @if ($subOrder->shop_discount_sen > 0)
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-ink-soft">{{ __('Shop discount') }}</dt>
                            <dd class="font-mono tabular-nums">-@money($subOrder->shop_discount_sen)</dd>
                        </div>
                    @endif
                    <div class="flex items-center justify-between gap-3 border-t border-line pt-1.5">
                        <dt class="font-semibold text-ink">{{ __('Total') }}</dt>
                        <dd class="font-mono font-semibold tabular-nums">@money($subOrder->total_sen)</dd>
                    </div>
                </dl>
                <p class="mt-3 border-t border-line pt-2 text-[12px] text-ink-soft">
                    {{ __('Platform commission rate: :rate%', ['rate' => $commissionRate]) }}
                </p>
            </x-ui.card>
        </div>
    </div>

    @include('livewire.seller.orders.partials.ship-modal')
</div>
