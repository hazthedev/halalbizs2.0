@php use App\Enums\GatewayPaymentStatus; use App\Enums\PaymentMethod; @endphp

<div class="space-y-4">

    {{-- Header --}}
    <h1 class="font-display text-2xl font-bold">{{ __('Payments') }}</h1>
    <p class="text-[13px] text-ink-soft">{{ __('Reconciliation grid — our payment rows against iPay88. Mismatched signatures are highlighted; stuck rows can be requeried.') }}</p>

    {{-- Filters --}}
    <x-ui.card class="flex flex-wrap items-center gap-3 p-3">
        <div>
            <label for="payments-status" class="sr-only">{{ __('Status') }}</label>
            <select id="payments-status" wire:model.live="status"
                    class="block min-h-11 rounded-lg border border-line-strong bg-surface px-3 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
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
    </x-ui.card>

    {{-- Table per design §6 --}}
    <x-ui.card class="overflow-x-auto">
        {{-- Row skeletons while the filters refresh the grid (design §6) --}}
        <x-ui.table-skeleton wire:loading wire:target="status, mismatchesOnly" />
        <div wire:loading.remove wire:target="status, mismatchesOnly">
        @if ($payments->isEmpty())
            <div class="px-6 py-16 text-center">
                <h2 class="font-display text-xl font-semibold">{{ __('No payments match') }}</h2>
                <p class="mt-1 text-sm text-ink-soft">{{ __('Payment attempts appear here the moment a buyer reaches the gateway.') }}</p>
            </div>
        @else
            <table class="w-full min-w-[1000px] text-[13px]">
                <thead class="sticky top-14 z-10 bg-surface">
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Ref no.') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Order') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Gateway') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Amount') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Status') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Trans ID') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Signature') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Requery result') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Paid at') }}</th>
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
                            <td class="px-3 py-2 text-right font-mono font-semibold tabular-nums whitespace-nowrap">@money($payment->amount_sen)</td>
                            <td class="px-3 py-2"><x-ui.badge :variant="$pillVariant">{{ $payment->status->label() }}</x-ui.badge></td>
                            <td class="px-3 py-2 whitespace-nowrap font-mono text-ink-soft">{{ $payment->ipay88_trans_id ?? '—' }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                @if ($payment->signature_valid === true)
                                    <span class="font-semibold text-emerald" title="{{ __('Signature verified') }}">✓</span>
                                @elseif ($payment->signature_valid === false)
                                    <span class="font-semibold text-danger">✗ {{ __('Mismatch') }}</span>
                                @else
                                    <span class="text-ink-faint">—</span>
                                @endif
                            </td>
                            <td class="max-w-44 truncate px-3 py-2 font-mono text-[12px] text-ink-soft" title="{{ $payment->requery_result }}">
                                {{ $payment->requery_result ?? '—' }}
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-ink-soft">{{ $payment->paid_at?->format('j M Y, g:ia') ?? '—' }}</td>
                            <td class="px-3 py-2">
                                <div class="flex justify-end">
                                    @if ($canRequery)
                                        <button type="button" wire:click="requery({{ $payment->id }})"
                                                wire:loading.attr="disabled" wire:target="requery({{ $payment->id }})"
                                                class="inline-flex min-h-11 items-center whitespace-nowrap rounded-lg border border-ink px-3 text-[13px] font-semibold text-ink hover:bg-paper disabled:cursor-not-allowed disabled:opacity-50 focus-visible:ring-2 focus-visible:ring-emerald">
                                            <span wire:loading.remove wire:target="requery({{ $payment->id }})">{{ __('Requery') }}</span>
                                            <span wire:loading wire:target="requery({{ $payment->id }})">{{ __('Requerying…') }}</span>
                                        </button>
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

    @if ($payments->hasPages())
        <div>{{ $payments->links() }}</div>
    @endif
</div>
