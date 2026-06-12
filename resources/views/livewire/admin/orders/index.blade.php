<div class="space-y-4">

    {{-- Header --}}
    <h1 class="font-display text-2xl font-bold">{{ __('Orders') }}</h1>

    {{-- Filters (docs/08 §E: status, store, method, date; search order_no — mono) --}}
    <x-ui.card class="p-3">
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-6">
            <div class="lg:col-span-2">
                <label for="orders-search" class="sr-only">{{ __('Search orders') }}</label>
                <input
                    id="orders-search"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Search order no. or sub-order no.') }}"
                    class="block min-h-11 w-full rounded-lg border border-line-strong bg-surface px-3 font-mono text-[13px] text-ink placeholder:font-sans placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald"
                >
            </div>
            <div>
                <label for="orders-status" class="sr-only">{{ __('Status') }}</label>
                <select id="orders-status" wire:model.live="status"
                        class="block min-h-11 w-full rounded-lg border border-line-strong bg-surface px-3 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach ($statuses as $statusCase)
                        <option value="{{ $statusCase->value }}">{{ $statusCase->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="orders-store" class="sr-only">{{ __('Store') }}</label>
                <select id="orders-store" wire:model.live="store"
                        class="block min-h-11 w-full rounded-lg border border-line-strong bg-surface px-3 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                    <option value="">{{ __('All stores') }}</option>
                    @foreach ($stores as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="orders-method" class="sr-only">{{ __('Payment method') }}</label>
                <select id="orders-method" wire:model.live="method"
                        class="block min-h-11 w-full rounded-lg border border-line-strong bg-surface px-3 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                    <option value="">{{ __('All methods') }}</option>
                    @foreach ($methods as $methodCase)
                        <option value="{{ $methodCase->value }}">{{ $methodCase->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <label for="orders-date-from" class="sr-only">{{ __('From date') }}</label>
                <input id="orders-date-from" type="date" wire:model.live="dateFrom"
                       class="block min-h-11 w-full rounded-lg border border-line-strong bg-surface px-3 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                <span class="text-[13px] text-ink-faint" aria-hidden="true">–</span>
                <label for="orders-date-to" class="sr-only">{{ __('To date') }}</label>
                <input id="orders-date-to" type="date" wire:model.live="dateTo"
                       class="block min-h-11 w-full rounded-lg border border-line-strong bg-surface px-3 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
            </div>
        </div>
        @if ($search !== '' || $status !== '' || $store !== '' || $method !== '' || $dateFrom !== '' || $dateTo !== '')
            <button type="button" wire:click="resetFilters"
                    class="mt-2 inline-flex min-h-11 items-center text-[13px] font-medium text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">
                {{ __('Clear filters') }}
            </button>
        @endif
    </x-ui.card>

    {{-- Table per design §6 — hairline rows, 13px, mono ids, right-aligned tabular numbers --}}
    <x-ui.card class="overflow-x-auto">
        @if ($subOrders->isEmpty())
            <div class="px-6 py-16 text-center">
                <h2 class="font-display text-xl font-semibold">{{ __('No sub-orders match') }}</h2>
                <p class="mt-1 text-sm text-ink-soft">{{ __('Adjust the filters or clear them to see every order on the marketplace.') }}</p>
            </div>
        @else
            <table class="w-full min-w-[860px] text-[13px]">
                <thead class="sticky top-14 z-10 bg-surface">
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Sub-order') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Order') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Store') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Buyer') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Placed') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Total') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($subOrders as $subOrder)
                        @php $placedAt = $subOrder->order->placed_at ?? $subOrder->order->created_at; @endphp
                        <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="sub-order-{{ $subOrder->id }}">
                            <td class="px-3 py-2 whitespace-nowrap">
                                <a href="{{ route('admin.orders.show', $subOrder) }}" wire:navigate
                                   class="inline-flex min-h-11 items-center font-mono font-medium text-ink underline-offset-2 hover:text-emerald hover:underline focus-visible:ring-2 focus-visible:ring-emerald">
                                    {{ $subOrder->sub_order_no }}
                                </a>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap font-mono text-ink-soft">{{ $subOrder->order->order_no }}</td>
                            <td class="px-3 py-2"><span class="line-clamp-1 max-w-44">{{ $subOrder->store->name }}</span></td>
                            <td class="px-3 py-2"><span class="line-clamp-1 max-w-44">{{ $subOrder->order->user->name }}</span></td>
                            <td class="px-3 py-2 whitespace-nowrap text-ink-soft">{{ $placedAt->format('j M Y, g:ia') }}</td>
                            <td class="px-3 py-2 text-right font-mono font-semibold tabular-nums whitespace-nowrap">@money($subOrder->total_sen)</td>
                            <td class="px-3 py-2"><x-order-status-pill :status="$subOrder->status" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    @if ($subOrders->hasPages())
        <div>{{ $subOrders->links() }}</div>
    @endif
</div>
