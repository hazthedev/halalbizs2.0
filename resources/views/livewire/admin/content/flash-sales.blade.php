<div class="mx-auto max-w-5xl px-4 py-6">
    <h1 class="font-display text-2xl font-bold text-ink">{{ __('Flash sales') }}</h1>

    {{-- Create a sale --}}
    <form wire:submit="createSale" class="mt-4 grid gap-3 rounded-xl border border-line bg-surface p-4 shadow-soft sm:grid-cols-4">
        <div class="sm:col-span-2">
            <label class="block text-[13px] font-medium text-ink">{{ __('Title') }}</label>
            <input type="text" wire:model="title" placeholder="11.11 Mega Sale"
                   class="mt-1 min-h-11 w-full rounded-lg border border-line-strong px-3 text-sm">
            @error('title')<p class="mt-1 text-[13px] text-danger">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-[13px] font-medium text-ink">{{ __('Starts') }}</label>
            <input type="datetime-local" wire:model="startsAt" class="mt-1 min-h-11 w-full rounded-lg border border-line-strong px-3 text-sm">
            @error('startsAt')<p class="mt-1 text-[13px] text-danger">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-[13px] font-medium text-ink">{{ __('Ends') }}</label>
            <input type="datetime-local" wire:model="endsAt" class="mt-1 min-h-11 w-full rounded-lg border border-line-strong px-3 text-sm">
            @error('endsAt')<p class="mt-1 text-[13px] text-danger">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-4">
            <button type="submit" class="inline-flex min-h-11 items-center rounded-lg bg-emerald px-4 text-sm font-semibold text-white">
                {{ __('Create flash sale') }}
            </button>
        </div>
    </form>

    {{-- Existing sales --}}
    <div class="mt-6 space-y-4">
        @forelse ($sales as $sale)
            <div class="rounded-xl border border-line bg-surface p-4 shadow-soft">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="font-semibold text-ink">{{ $sale->title }}
                            <span class="ml-2 rounded-full px-2 py-0.5 text-[12px] {{ $sale->isLive() ? 'bg-emerald-tint text-emerald' : 'bg-canvas-deep text-ink-soft' }}">
                                {{ $sale->isLive() ? __('Live') : ($sale->is_active ? __('Scheduled') : __('Off')) }}
                            </span>
                        </h2>
                        <p class="text-[13px] text-ink-soft">{{ $sale->starts_at->format('d M H:i') }} → {{ $sale->ends_at->format('d M H:i') }}</p>
                    </div>
                    <div class="flex gap-2">
                        <button wire:click="openAddItem({{ $sale->id }})" class="rounded-lg border border-line-strong px-3 py-1.5 text-[13px] font-medium">{{ __('Add deal') }}</button>
                        <button wire:click="toggleActive({{ $sale->id }})" class="rounded-lg border border-line-strong px-3 py-1.5 text-[13px] font-medium">{{ $sale->is_active ? __('Disable') : __('Enable') }}</button>
                        <button wire:click="deleteSale({{ $sale->id }})" wire:confirm="{{ __('Remove this flash sale?') }}" class="rounded-lg border border-danger/40 px-3 py-1.5 text-[13px] font-medium text-danger">{{ __('Delete') }}</button>
                    </div>
                </div>

                @if ($addingToSaleId === $sale->id)
                    <form wire:submit="addItem" class="mt-3 grid gap-2 rounded-lg bg-canvas p-3 sm:grid-cols-5">
                        <select wire:model="itemVariantId" class="min-h-11 rounded-lg border border-line-strong px-2 text-sm sm:col-span-2">
                            <option value="">{{ __('Pick a variant') }}</option>
                            @foreach ($variantPick as $v)
                                <option value="{{ $v->id }}">#{{ $v->id }} · {{ Str::limit($v->product?->getTranslation('name', 'en'), 30) }} {{ $v->options_label }}</option>
                            @endforeach
                        </select>
                        <input type="text" wire:model="itemPromo" placeholder="{{ __('Promo RM') }}" class="min-h-11 rounded-lg border border-line-strong px-2 text-sm">
                        <input type="number" wire:model="itemAllocated" min="1" placeholder="{{ __('Qty') }}" class="min-h-11 rounded-lg border border-line-strong px-2 text-sm">
                        <input type="number" wire:model="itemPerBuyer" min="1" placeholder="{{ __('Per buyer') }}" class="min-h-11 rounded-lg border border-line-strong px-2 text-sm">
                        <button type="submit" class="min-h-11 rounded-lg bg-emerald px-3 text-sm font-semibold text-white sm:col-span-5">{{ __('Add deal') }}</button>
                        @error('itemVariantId')<p class="text-[13px] text-danger sm:col-span-5">{{ $message }}</p>@enderror
                    </form>
                @endif

                @if ($sale->items->isNotEmpty())
                    <table class="mt-3 w-full text-sm">
                        <thead><tr class="text-left text-[12px] uppercase text-ink-soft">
                            <th class="py-1">{{ __('Product') }}</th><th>{{ __('Promo') }}</th><th>{{ __('Claimed') }}</th><th>{{ __('Per buyer') }}</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($sale->items as $item)
                                <tr class="border-t border-line">
                                    <td class="py-1.5">{{ $item->variant?->product?->getTranslation('name', 'en') }} <span class="text-ink-faint">{{ $item->variant?->options_label }}</span></td>
                                    <td class="tnum">@money($item->promo_price_sen)</td>
                                    <td class="tnum">{{ $item->sold_qty }} / {{ $item->allocated_qty }}</td>
                                    <td class="tnum">{{ $item->per_buyer_limit }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @empty
            <p class="text-sm text-ink-soft">{{ __('No flash sales yet.') }}</p>
        @endforelse
    </div>
</div>
