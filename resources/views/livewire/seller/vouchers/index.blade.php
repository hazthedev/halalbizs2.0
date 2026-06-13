<div class="space-y-4">

    <x-ui.section-heading as="h1" :title="__('Shop vouchers')">
        @unless ($showForm)
            <x-slot:actions>
                <x-ui.button wire:click="create">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    {{ __('Add voucher') }}
                </x-ui.button>
            </x-slot:actions>
        @endunless
    </x-ui.section-heading>

    <div class="rounded-[var(--radius-card)] border border-line bg-surface px-4 py-3 text-[13px] text-ink-soft shadow-soft">
        {{ __('Shop vouchers apply to your items only — buyers can stack one with a platform voucher at checkout.') }}
    </div>

    {{-- Create / edit form --}}
    @if ($showForm)
        <x-ui.card class="p-4">
            <form wire:submit="save" class="space-y-4">
                <h2 class="font-display text-lg font-semibold">
                    {{ $editingId !== null ? __('Edit voucher') : __('New shop voucher') }}
                </h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input :label="__('Code')" wire:model="code" placeholder="SHOP10" :error="$errors->first('code')" :hint="__('Stored in UPPERCASE — what buyers type at checkout. Unique within your shop.')" class="font-mono" />
                    <div>
                        <label for="voucher-type" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Type') }}</label>
                        <select id="voucher-type" wire:model.live="type"
                                class="block min-h-11 w-full rounded-lg border border-line-strong bg-surface px-3 py-2 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                            @foreach ($types as $typeCase)
                                <option value="{{ $typeCase->value }}">{{ $typeCase->label() }}</option>
                            @endforeach
                        </select>
                        @error('type')<p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    @if ($type === 'fixed')
                        <x-ui.input :label="__('Discount amount (RM)')" wire:model="value" inputmode="decimal" placeholder="5.00" :error="$errors->first('value')" />
                    @elseif ($type === 'percent')
                        <x-ui.input :label="__('Discount (%)')" wire:model="percent" inputmode="decimal" placeholder="10" :error="$errors->first('percent')" />
                        <x-ui.input :label="__('Maximum discount (RM)')" wire:model="maxDiscount" inputmode="decimal" placeholder="20.00" :error="$errors->first('maxDiscount')" :hint="__('Optional cap — blank means uncapped.')" />
                    @else
                        <p class="self-center text-[13px] text-ink-faint sm:col-span-2">{{ __('Free shipping vouchers waive your shop’s shipping fee — no amount needed.') }}</p>
                    @endif
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.input :label="__('Minimum spend (RM)')" wire:model="minSpend" inputmode="decimal" placeholder="50.00" :error="$errors->first('minSpend')" :hint="__('Counts your shop’s items only — blank means no minimum.')" />
                    <x-ui.input :label="__('Quota')" type="number" min="1" wire:model="quota" :error="$errors->first('quota')" :hint="__('Total redemptions — blank means unlimited.')" />
                    <x-ui.input :label="__('Per-user limit')" type="number" min="1" wire:model="perUserLimit" :error="$errors->first('perUserLimit')" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input :label="__('Starts at')" type="datetime-local" wire:model="startsAt" :error="$errors->first('startsAt')" />
                    <x-ui.input :label="__('Ends at')" type="datetime-local" wire:model="endsAt" :error="$errors->first('endsAt')" />
                </div>

                <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-[13px] font-medium text-ink">
                    <input type="checkbox" wire:model="isActive" class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                    {{ __('Active') }}
                </label>

                <div class="flex items-center gap-2">
                    <x-ui.button type="submit" wire:loading.attr="disabled">{{ $editingId !== null ? __('Save voucher') : __('Create voucher') }}</x-ui.button>
                    <x-ui.button variant="ghost" wire:click="cancel">{{ __('Cancel') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif

    {{-- List --}}
    <x-ui.card class="overflow-x-auto">
        @if ($vouchers->isEmpty())
            <x-ui.empty-state :title="__('No shop vouchers yet')" :message="__('Create one above — buyers pick it at checkout when your items are in their order.')" />
        @else
            <table class="w-full min-w-[860px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Code') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Type') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Value') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Min spend') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Window') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Used / quota') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Active') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vouchers as $voucher)
                        <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="voucher-{{ $voucher->id }}">
                            <td class="px-3 py-2 font-mono font-medium">{{ $voucher->code }}</td>
                            <td class="px-3 py-2">{{ $voucher->type->label() }}</td>
                            <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">
                                @switch($voucher->type)
                                    @case(\App\Enums\VoucherType::Fixed) @money($voucher->value_sen) @break
                                    @case(\App\Enums\VoucherType::Percent)
                                        {{ rtrim(rtrim((string) $voucher->percent, '0'), '.') }}%@if ($voucher->max_discount_sen !== null) <span class="text-ink-soft">({{ __('cap') }} @money($voucher->max_discount_sen))</span>@endif
                                        @break
                                    @default —
                                @endswitch
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">
                                @if ($voucher->min_spend_sen > 0) @money($voucher->min_spend_sen) @else — @endif
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-[12px] text-ink-soft">
                                {{ $voucher->starts_at->format('d M Y') }} → {{ $voucher->ends_at->format('d M Y') }}
                            </td>
                            <td class="px-3 py-2 text-right font-mono tabular-nums">
                                {{ $voucher->used_count }}<span class="text-ink-faint">/{{ $voucher->quota ?? '∞' }}</span>
                            </td>
                            <td class="px-3 py-2">
                                <button type="button" role="switch" aria-checked="{{ $voucher->is_active ? 'true' : 'false' }}"
                                        wire:click="toggleActive({{ $voucher->id }})"
                                        aria-label="{{ __('Toggle :code', ['code' => $voucher->code]) }}"
                                        class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                    <span class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-150 {{ $voucher->is_active ? 'bg-emerald' : 'bg-line-strong' }}">
                                        <span class="inline-block size-4 rounded-full bg-white transition-transform duration-150 {{ $voucher->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                    </span>
                                </button>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" wire:click="edit({{ $voucher->id }})"
                                            class="inline-flex min-h-11 items-center rounded-lg px-2 font-medium text-ink-soft hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Edit') }}</button>
                                    <button type="button" wire:click="delete({{ $voucher->id }})"
                                            wire:confirm="{{ __('Delete voucher :code? This cannot be undone.', ['code' => $voucher->code]) }}"
                                            class="inline-flex min-h-11 items-center rounded-lg px-2 font-medium text-danger hover:bg-danger-tint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Delete') }}</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>
</div>
