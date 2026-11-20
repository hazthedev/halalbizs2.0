<div class="space-y-4">
    <x-ui.section-heading as="h1" :title="$editingId !== null ? __('Edit voucher') : __('New shop voucher')" />
        <x-ui.card class="p-4">
            <form wire:submit="save" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input :label="__('Code')" wire:model="code" placeholder="SHOP10" :error="$errors->first('code')" :hint="__('Stored in UPPERCASE — what buyers type at checkout. Unique within your shop.')" input-class="font-mono" />
                    <div>
                        <label for="voucher-type" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Type') }}</label>
                        <select id="voucher-type" wire:model.live="type"
                                class="block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 py-2 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
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
                    <x-ui.button variant="ghost" :href="route('seller.vouchers.index')">{{ __('Cancel') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>
</div>
