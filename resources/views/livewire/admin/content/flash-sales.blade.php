<div class="space-y-4">
    <x-ui.section-heading :title="__('Flash sales')" as="h1" />

    {{-- Create a sale --}}
    <x-ui.card class="p-4"><form wire:submit="createSale" class="grid gap-3 sm:grid-cols-4">
        <div class="sm:col-span-2">
            <label class="block text-[13px] font-medium text-ink">{{ __('Title') }}</label>
            <input type="text" wire:model="title" placeholder="11.11 Mega Sale"
                   class="mt-1 min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong px-3 text-sm">
            @error('title')<p class="mt-1 text-[13px] text-danger">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-[13px] font-medium text-ink">{{ __('Starts') }}</label>
            <input type="datetime-local" wire:model="startsAt" class="mt-1 min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong px-3 text-sm">
            @error('startsAt')<p class="mt-1 text-[13px] text-danger">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-[13px] font-medium text-ink">{{ __('Ends') }}</label>
            <input type="datetime-local" wire:model="endsAt" class="mt-1 min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong px-3 text-sm">
            @error('endsAt')<p class="mt-1 text-[13px] text-danger">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-4">
            <x-ui.button variant="plain" type="submit" class="inline-flex min-h-11 items-center gap-2 rounded-[var(--radius-control)] bg-emerald px-4 text-sm font-medium text-white" target="createSale" icon="plus">
                {{ __('Create flash sale') }}
            </x-ui.button>
        </div>
    </form></x-ui.card>

    {{-- Existing sales --}}
    <x-ui.table.search :placeholder="__('Search flash sale title')" />

    <x-ui.table.per-page />

    <x-ui.card class="overflow-x-auto">
        @if ($sales->isEmpty())
            <x-ui.empty-state :title="$search !== '' ? __('No flash sales match') : __('No flash sales yet.')" :message="$search !== '' ? __('Try searching for something else.') : __('Create one above, then add deals to it.')" />
        @else
            <table class="w-full min-w-[760px] text-[13px]">
                <thead class="sticky top-0 z-10 bg-surface">
                    <tr class="border-b border-line text-left text-ink-soft">
                        <x-ui.table.sortable field="title" :sort="$sort" :direction="$direction" class="px-3 py-5 font-medium">{{ __('Title') }}</x-ui.table.sortable>
                        <x-ui.table.sortable field="starts" default="desc" :sort="$sort" :direction="$direction" class="px-3 py-5 font-medium">{{ __('Starts') }}</x-ui.table.sortable>
                        <x-ui.table.sortable field="ends" :sort="$sort" :direction="$direction" class="px-3 py-5 font-medium">{{ __('Ends') }}</x-ui.table.sortable>
                        <th scope="col" class="px-3 py-5 font-medium">{{ __('Status') }}</th>
                        <th scope="col" class="px-3 py-5 text-right font-medium">{{ __('Deals') }}</th>
                        <th scope="col" class="px-3 py-5 text-right font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                @foreach ($sales as $sale)
                    <tbody wire:key="sale-{{ $sale->id }}" class="border-b border-line last:border-b-0">
                        <tr class="hover:bg-paper">
                            <td class="px-3 py-2 font-medium text-ink">{{ $sale->title }}</td>
                            <td class="px-3 py-2 whitespace-nowrap text-ink-soft">{{ $sale->starts_at->display()->format('d M H:i') }}</td>
                            <td class="px-3 py-2 whitespace-nowrap text-ink-soft">{{ $sale->ends_at->display()->format('d M H:i') }}</td>
                            <td class="px-3 py-2">
                                <span class="rounded-full px-2 py-0.5 text-[12px] {{ $sale->isLive() ? 'bg-emerald-tint text-emerald' : 'bg-canvas-deep text-ink-soft' }}">
                                    {{ $sale->isLive() ? __('Live') : ($sale->is_active ? __('Scheduled') : __('Off')) }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $sale->items->count() }}</td>
                            <td class="px-3 py-2">
                                <div class="flex justify-end gap-2">
                                    <x-ui.button variant="plain" wire:click="openAddItem({{ $sale->id }})" class="rounded-[var(--radius-control)] border border-line-strong px-3 py-1.5 text-[13px] font-medium">{{ __('Add deal') }}</x-ui.button>
                                    <x-ui.button variant="plain" wire:click="toggleActive({{ $sale->id }})" class="rounded-[var(--radius-control)] border border-line-strong px-3 py-1.5 text-[13px] font-medium">{{ $sale->is_active ? __('Disable') : __('Enable') }}</x-ui.button>
                                    <x-ui.button variant="plain" wire:click="deleteSale({{ $sale->id }})" wire:confirm="{{ __('Remove this flash sale?') }}" class="rounded-[var(--radius-control)] border border-danger/40 px-3 py-1.5 text-[13px] font-medium text-danger">{{ __('Delete') }}</x-ui.button>
                                </div>
                            </td>
                        </tr>
                        @if ($addingToSaleId === $sale->id || $sale->items->isNotEmpty())
                            <tr>
                                <td colspan="6" class="px-3 pb-3">
                                    @if ($addingToSaleId === $sale->id)
                                        <form wire:submit="addItem" class="grid gap-2 rounded-[var(--radius-control)] bg-canvas p-3 sm:grid-cols-5">
                                            <select wire:model="itemVariantId" class="min-h-11 rounded-[var(--radius-control)] border border-line-strong px-2 text-sm sm:col-span-2">
                                                <option value="">{{ __('Pick a variant') }}</option>
                                                @foreach ($variantPick as $v)
                                                    <option value="{{ $v->id }}">#{{ $v->id }} · {{ Str::limit($v->product?->getTranslation('name', 'en'), 30) }} {{ $v->options_label }}</option>
                                                @endforeach
                                            </select>
                                            <input type="text" wire:model="itemPromo" placeholder="{{ __('Promo RM') }}" class="min-h-11 rounded-[var(--radius-control)] border border-line-strong px-2 text-sm">
                                            <input type="number" wire:model="itemAllocated" min="1" placeholder="{{ __('Qty') }}" class="min-h-11 rounded-[var(--radius-control)] border border-line-strong px-2 text-sm">
                                            <input type="number" wire:model="itemPerBuyer" min="1" placeholder="{{ __('Per buyer') }}" class="min-h-11 rounded-[var(--radius-control)] border border-line-strong px-2 text-sm">
                                            <x-ui.button variant="plain" type="submit" class="min-h-11 rounded-[var(--radius-control)] bg-emerald px-3 text-sm font-medium text-white sm:col-span-5" target="addItem">{{ __('Add deal') }}</x-ui.button>
                                            @error('itemVariantId')<p class="text-[13px] text-danger sm:col-span-5">{{ $message }}</p>@enderror
                                        </form>
                                    @endif
                                    @if ($sale->items->isNotEmpty())
                                        <table class="mt-2 w-full text-[13px]">
                                            <thead><tr class="text-left text-ink-soft">
                                                <th scope="col" class="py-1 font-medium">{{ __('Product') }}</th>
                                                <th scope="col" class="py-1 font-medium">{{ __('Promo') }}</th>
                                                <th scope="col" class="py-1 font-medium">{{ __('Claimed') }}</th>
                                                <th scope="col" class="py-1 font-medium">{{ __('Per buyer') }}</th>
                                            </tr></thead>
                                            <tbody>
                                                @foreach ($sale->items as $item)
                                                    <tr class="border-t border-line" wire:key="item-{{ $item->id }}">
                                                        <td class="py-1.5">{{ $item->variant?->product?->getTranslation('name', 'en') }} <span class="text-ink-faint">{{ $item->variant?->options_label }}</span></td>
                                                        <td class="font-mono tabular-nums">@money($item->promo_price_sen)</td>
                                                        <td class="font-mono tabular-nums">{{ $item->sold_qty }} / {{ $item->allocated_qty }}</td>
                                                        <td class="font-mono tabular-nums">{{ $item->per_buyer_limit }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    </tbody>
                @endforeach
            </table>
        @endif
    </x-ui.card>

    <x-ui.table.footer :rows="$sales" />
</div>
