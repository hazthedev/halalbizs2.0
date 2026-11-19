@php use App\Enums\GatewayPaymentStatus; use App\Enums\PaymentMethod; @endphp

<div class="space-y-4">

    {{-- Header --}}
    <x-ui.section-heading :title="__('Payments')" :subtitle="__('Reconciliation grid — our payment rows against iPay88. Mismatched signatures are highlighted; stuck rows can be requeried.')" as="h1" />

    {{-- Filters --}}
    <x-ui.card class="flex flex-wrap items-center gap-3 p-3">
        <div class="min-w-0 flex-1">
            <label for="payments-search" class="sr-only">{{ __('Search payments') }}</label>
            <input id="payments-search" type="search" wire:model.live.debounce.300ms="search"
                   placeholder="{{ __('Search order no., ref no. or trans ID') }}"
                   class="block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald font-mono placeholder:font-sans placeholder:text-ink-faint">
        </div>
        <div>
            <label for="payments-status" class="sr-only">{{ __('Status') }}</label>
            <select id="payments-status" wire:model.live="status"
                    class="block min-h-11 rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                <option value="">{{ __('All statuses') }}</option>
                @foreach ($statuses as $statusCase)
                    <option value="{{ $statusCase->value }}">{{ $statusCase->label() }}</option>
                @endforeach
            </select>
        </div>
        <label for="payments-mismatches" class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-[13px] font-medium text-ink">
            <input id="payments-mismatches" type="checkbox" wire:model.live="mismatchesOnly"
                   class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
            {{ __('Mismatches only') }}
        </label>
        <label for="payments-needs-portal" class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-[13px] font-medium text-ink">
            <input id="payments-needs-portal" type="checkbox" wire:model.live="needsPortalRefund"
                   class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
            {{ __('Needs portal refund') }}
        </label>
        @if ($search !== '' || $status !== '' || $mismatchesOnly || $needsPortalRefund)
            <button type="button" wire:click="resetFilters"
                    class="inline-flex min-h-11 items-center text-[13px] font-medium text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">
                {{ __('Clear filters') }}
            </button>
        @endif
    </x-ui.card>

    {{-- Table per design §6 --}}
    <x-ui.card class="overflow-x-auto">
        {{-- Row skeletons while the filters refresh the grid (design §6) --}}
        <x-ui.table-skeleton wire:loading wire:target="search, status, mismatchesOnly, needsPortalRefund, resetFilters, sortBy, perPage" />
        <div wire:loading.remove wire:target="search, status, mismatchesOnly, needsPortalRefund, resetFilters, sortBy, perPage">
        @if ($payments->isEmpty())
            <x-ui.empty-state :title="__('No payments match')" :message="__('Payment attempts appear here the moment a buyer reaches the gateway.')" />
        @else
            <table class="w-full min-w-[1000px] text-[13px]">
                <thead class="sticky top-0 z-10 bg-surface">
                    <tr class="border-b border-line text-left text-ink-soft">
                        <x-ui.table.sortable field="ref" :sort="$sort" :direction="$direction" class="px-3 py-2.5 font-medium">{{ __('Ref no.') }}</x-ui.table.sortable>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Order') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Gateway') }}</th>
                        <x-ui.table.sortable field="amount" :sort="$sort" :direction="$direction" class="px-3 py-2.5 text-right font-medium">{{ __('Amount') }}</x-ui.table.sortable>
                        {{-- H-3: refunded_sen was written by RefundService and read by
                             nothing in the whole app, so a refund — including cash owed
                             on a cancelled-after-payment order — appeared on no
                             reconciliation surface. This grid is that surface. --}}
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Refunded') }}</th>
                        <x-ui.table.sortable field="status" :sort="$sort" :direction="$direction" class="px-3 py-2.5 font-medium">{{ __('Status') }}</x-ui.table.sortable>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Trans ID') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Signature') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Requery result') }}</th>
                        <x-ui.table.sortable field="paid" :sort="$sort" :direction="$direction" class="px-3 py-2.5 font-medium">{{ __('Paid at') }}</x-ui.table.sortable>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payments as $payment)
                        @php
                            $pillVariant = match ($payment->status) {
                                GatewayPaymentStatus::Pending => 'warn',
                                GatewayPaymentStatus::Success => 'sale',
                                GatewayPaymentStatus::Failed => 'danger',
                                GatewayPaymentStatus::Expired => 'neutral',
                            };
                            $canRequery = $payment->gateway === PaymentMethod::Ipay88
                                && in_array($payment->status, [GatewayPaymentStatus::Pending, GatewayPaymentStatus::Failed], true);
                        @endphp
                        {{-- signature_valid === false → danger-highlighted row (docs/08 §E) --}}
                        <tr class="border-b border-line last:border-b-0 {{ $payment->signature_valid === false ? 'bg-danger-tint' : 'hover:bg-paper' }}"
                            wire:key="payment-{{ $payment->id }}">
                            <td class="px-3 py-2 whitespace-nowrap font-mono font-medium">{{ $payment->ref_no }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <a href="{{ route('admin.orders.index', ['search' => $payment->order->order_no]) }}" wire:navigate
                                   class="inline-flex min-h-11 items-center font-mono text-ink underline-offset-2 hover:text-emerald hover:underline focus-visible:ring-2 focus-visible:ring-emerald">
                                    {{ $payment->order->order_no }}
                                </a>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-ink-soft">{{ $payment->gateway->label() }}</td>
                            <td class="px-3 py-2 text-right font-mono font-medium tabular-nums whitespace-nowrap">@money($payment->amount_sen)</td>
                            <td class="px-3 py-2 text-right font-mono tabular-nums whitespace-nowrap {{ (int) $payment->refunded_sen > 0 ? 'text-danger' : 'text-ink-soft' }}">
                                @if ((int) $payment->refunded_sen > 0)
                                    −@money($payment->refunded_sen)
                                    @if ($payment->refunded_at)
                                        <span class="block text-[11px] font-sans text-ink-soft">{{ $payment->refunded_at->display()->format('d M Y') }}</span>
                                    @endif
                                    {{-- The amount alone said "refunded" whether or not the
                                         gateway moved anything. false is the case someone
                                         must finish by hand; null is COD, where the ledger
                                         adjustment IS the refund and nothing is owed. --}}
                                    @if ($payment->gateway_refund_ok === false)
                                        <span class="mt-0.5 block text-[11px] font-sans font-medium text-danger">{{ __('Needs portal refund') }}</span>
                                    @elseif ($payment->gateway_refund_ok === true && in_array($payment->id, $settledInPortal, true))
                                        <span class="mt-0.5 block text-[11px] font-sans text-ink-soft">{{ __('Settled in portal') }}</span>
                                    @elseif ($payment->gateway_refund_ok === true)
                                        <span class="mt-0.5 block text-[11px] font-sans text-ink-soft">{{ __('Gateway confirmed') }}</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-2"><x-ui.badge :variant="$pillVariant">{{ $payment->status->label() }}</x-ui.badge></td>
                            <td class="px-3 py-2 whitespace-nowrap font-mono text-ink-soft">{{ $payment->ipay88_trans_id ?? '—' }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                @if ($payment->signature_valid === true)
                                    <span class="font-medium text-emerald" title="{{ __('Signature verified') }}">✓</span>
                                @elseif ($payment->signature_valid === false)
                                    <span class="font-medium text-danger">✗ {{ __('Mismatch') }}</span>
                                @else
                                    <span class="text-ink-faint">—</span>
                                @endif
                            </td>
                            <td class="max-w-44 truncate px-3 py-2 font-mono text-[12px] text-ink-soft" title="{{ $payment->requery_result }}">
                                {{ $payment->requery_result ?? '—' }}
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-ink-soft">{{ $payment->paid_at?->display()->format('j M Y, g:ia') ?? '—' }}</td>
                            <td class="px-3 py-2">
                                <div class="flex flex-col items-end gap-2">
                                    @if ($canRequery)
                                        <x-ui.button variant="plain" type="button" wire:click="requery({{ $payment->id }})"
                                                wire:loading.attr="disabled" wire:target="requery({{ $payment->id }})"
                                                class="inline-flex min-h-11 items-center whitespace-nowrap rounded-[var(--radius-control)] border border-ink px-3 text-[13px] font-medium text-ink hover:bg-paper disabled:cursor-not-allowed disabled:opacity-50 focus-visible:ring-2 focus-visible:ring-emerald">
                                            <span wire:loading.remove wire:target="requery({{ $payment->id }})">{{ __('Requery') }}</span>
                                            <span wire:loading wire:target="requery({{ $payment->id }})">{{ __('Requerying…') }}</span>
                                        </x-ui.button>
                                    @endif
                                    {{-- HB-G122: clears "Needs portal refund" once a human has
                                         refunded the money in the iPay88 portal. The confirm is
                                         in the page (Alpine), not a browser confirm(). --}}
                                    @if ($payment->gateway_refund_ok === false)
                                        <div data-settle-control x-data="{ ask: false, ref: '' }" class="flex flex-col items-end gap-2">
                                            <x-ui.button variant="secondary" type="button" x-show="! ask" x-on:click="ask = true" class="whitespace-nowrap">
                                                {{ __('Mark refund settled in portal') }}
                                            </x-ui.button>
                                            <div x-show="ask" x-cloak class="flex w-64 flex-col gap-2 text-left">
                                                <p class="text-[12px] text-ink">{{ __('Only confirm once the money has been refunded in the iPay88 merchant portal.') }}</p>
                                                <label for="settle-ref-{{ $payment->id }}" class="text-[12px] font-medium text-ink-soft">{{ __('Portal reference (optional)') }}</label>
                                                <input id="settle-ref-{{ $payment->id }}" type="text" maxlength="100" x-model="ref"
                                                       class="block min-h-11 rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                                <div class="flex justify-end gap-2">
                                                    <x-ui.button variant="ghost" type="button" x-on:click="ask = false; ref = ''">
                                                        {{ __('Cancel') }}
                                                    </x-ui.button>
                                                    <x-ui.button variant="primary" type="button" x-on:click="$wire.markRefundSettled({{ $payment->id }}, ref)"
                                                            target="markRefundSettled" class="whitespace-nowrap">
                                                        {{ __('Confirm settled') }}
                                                    </x-ui.button>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        </div>
    </x-ui.card>

    <x-ui.table.footer :rows="$payments" />
</div>
