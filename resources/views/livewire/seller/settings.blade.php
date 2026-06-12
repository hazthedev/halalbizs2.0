<div class="mx-auto max-w-3xl space-y-4">
    <h1 class="font-display text-[22px] font-bold leading-tight">{{ __('Shop settings') }}</h1>

    {{-- ===== Profile ===== --}}
    <x-ui.card class="p-4 lg:p-6">
        <h2 class="text-sm font-semibold">{{ __('Profile') }}</h2>

        <form wire:submit="saveProfile" class="mt-4 space-y-4">
            <div>
                <p class="mb-1.5 text-[13px] font-medium text-ink">{{ __('Shop name') }}</p>
                <p class="text-sm font-semibold">{{ $store->name }}</p>
                <p class="mt-1 text-[13px] text-ink-faint">{{ __('Contact support to rename your shop.') }}</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="logo" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Logo') }}</label>
                    @if ($store->getFirstMediaUrl('logo'))
                        <img src="{{ $store->getFirstMediaUrl('logo') }}" alt="{{ __(':store logo', ['store' => $store->name]) }}" class="mb-2 size-16 rounded-[10px] border border-line object-cover">
                    @endif
                    <input type="file" id="logo" wire:model="logo" accept=".jpg,.jpeg,.png,.webp"
                           class="block w-full text-sm text-ink-soft file:mr-3 file:min-h-11 file:cursor-pointer file:rounded-lg file:border file:border-ink file:bg-surface file:px-4 file:py-2 file:text-sm file:font-semibold file:text-ink hover:file:bg-paper">
                    <div wire:loading wire:target="logo" class="mt-1.5 text-[13px] text-ink-soft">{{ __('Uploading…') }}</div>
                    @error('logo')
                        <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
                    @else
                        <p class="mt-1.5 text-[13px] text-ink-faint">{{ __('Square image (1:1) works best.') }}</p>
                    @enderror
                </div>

                <div>
                    <label for="banner" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Banner') }}</label>
                    @if ($store->getFirstMediaUrl('banner'))
                        <img src="{{ $store->getFirstMediaUrl('banner') }}" alt="{{ __(':store banner', ['store' => $store->name]) }}" class="mb-2 h-16 w-full rounded-[10px] border border-line object-cover">
                    @endif
                    <input type="file" id="banner" wire:model="banner" accept=".jpg,.jpeg,.png,.webp"
                           class="block w-full text-sm text-ink-soft file:mr-3 file:min-h-11 file:cursor-pointer file:rounded-lg file:border file:border-ink file:bg-surface file:px-4 file:py-2 file:text-sm file:font-semibold file:text-ink hover:file:bg-paper">
                    <div wire:loading wire:target="banner" class="mt-1.5 text-[13px] text-ink-soft">{{ __('Uploading…') }}</div>
                    @error('banner')
                        <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
                    @else
                        <p class="mt-1.5 text-[13px] text-ink-faint">{{ __('Wide image, shown on your store page.') }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label for="description" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Description') }}</label>
                <textarea id="description" wire:model="description" rows="4" maxlength="2000"
                          class="block w-full rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('description') ? 'border-danger' : 'border-line-strong' }}"></textarea>
                @error('description')
                    <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="state" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('State') }}</label>
                <select id="state" wire:model="state"
                        class="block min-h-11 w-full rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('state') ? 'border-danger' : 'border-line-strong' }}">
                    <option value="">{{ __('Select a state') }}</option>
                    @foreach ($states as $stateOption)
                        <option value="{{ $stateOption }}">{{ $stateOption }}</option>
                    @endforeach
                </select>
                @error('state')
                    <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end border-t border-line pt-4">
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="saveProfile, logo, banner">{{ __('Save profile') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    {{-- ===== Holiday mode ===== --}}
    <x-ui.card class="p-4 lg:p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-sm font-semibold">{{ __('Holiday mode') }}</h2>
                <p class="mt-1 text-[13px] text-ink-soft">{{ __('Buyers can\'t place orders while holiday mode is on. Your products stay visible but can\'t be bought.') }}</p>
            </div>
            <label class="relative inline-flex min-h-11 cursor-pointer items-center">
                <input type="checkbox" wire:model.live="holidayMode" class="peer sr-only">
                <span class="h-6 w-11 rounded-full bg-line-strong transition-colors duration-150 peer-checked:bg-emerald peer-focus-visible:ring-2 peer-focus-visible:ring-emerald peer-focus-visible:ring-offset-2"></span>
                <span class="absolute left-0.5 top-1/2 size-5 -translate-y-1/2 rounded-full bg-surface shadow transition-transform duration-150 peer-checked:translate-x-5"></span>
                <span class="sr-only">{{ __('Holiday mode') }}</span>
            </label>
        </div>
        @if ($holidayMode)
            <p class="mt-3 rounded-lg bg-warn-tint p-3 text-[13px] text-warn">{{ __('Holiday mode is on — buyers can\'t place orders right now.') }}</p>
        @endif
    </x-ui.card>

    {{-- ===== Shipping ===== --}}
    <x-ui.card class="p-4 lg:p-6">
        <h2 class="text-sm font-semibold">{{ __('Shipping') }}</h2>

        <form wire:submit="saveShipping" class="mt-4 space-y-4">
            <fieldset>
                <legend class="mb-1.5 text-[13px] font-medium text-ink">{{ __('Shipping fee mode') }}</legend>
                <div class="flex flex-wrap gap-2">
                    <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border px-3.5 py-2 text-sm {{ $shippingMode === 'flat' ? 'border-emerald bg-emerald-tint font-medium' : 'border-line-strong' }}">
                        <input type="radio" wire:model.live="shippingMode" value="flat" class="size-4 accent-emerald">
                        {{ __('Flat fee for all of Malaysia') }}
                    </label>
                    <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border px-3.5 py-2 text-sm {{ $shippingMode === 'matrix' ? 'border-emerald bg-emerald-tint font-medium' : 'border-line-strong' }}">
                        <input type="radio" wire:model.live="shippingMode" value="matrix" class="size-4 accent-emerald">
                        {{ __('Per-state fees') }}
                    </label>
                </div>
            </fieldset>

            @if ($shippingMode === 'flat')
                <div class="max-w-xs">
                    <label for="flatFee" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Shipping fee (RM)') }}</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-sm text-ink-faint">RM</span>
                        <input type="text" id="flatFee" wire:model="flatFee" inputmode="decimal" placeholder="8.00"
                               class="block min-h-11 w-full rounded-lg border bg-surface py-2.5 pl-11 pr-3.5 text-right font-mono text-sm tabular-nums text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('flatFee') ? 'border-danger' : 'border-line-strong' }}">
                    </div>
                    @error('flatFee')
                        <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
                    @enderror
                </div>
            @else
                <div>
                    <div class="mb-3 flex flex-wrap items-end gap-2">
                        <div class="max-w-[180px]">
                            <label for="applyAll" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Apply to all (RM)') }}</label>
                            <input type="text" id="applyAll" wire:model="applyAll" inputmode="decimal" placeholder="8.00"
                                   class="block min-h-11 w-full rounded-lg border bg-surface px-3.5 py-2.5 text-right font-mono text-sm tabular-nums text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('applyAll') ? 'border-danger' : 'border-line-strong' }}">
                        </div>
                        <x-ui.button variant="secondary" wire:click="applyToAll" wire:loading.attr="disabled">{{ __('Apply to all') }}</x-ui.button>
                    </div>
                    @error('applyAll')
                        <p class="mb-2 text-[13px] text-danger">{{ $message }}</p>
                    @enderror

                    <div class="overflow-hidden rounded-lg border border-line">
                        <table class="w-full text-[13px]">
                            <thead>
                                <tr class="border-b border-line bg-paper text-left text-ink-soft">
                                    <th class="px-3 py-2 font-medium">{{ __('State') }}</th>
                                    <th class="w-40 px-3 py-2 text-right font-medium">{{ __('Fee (RM)') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($states as $index => $stateName)
                                    <tr class="border-b border-line last:border-0 hover:bg-paper">
                                        <td class="px-3 py-1.5">{{ $stateName }}</td>
                                        <td class="px-3 py-1.5">
                                            <input type="text" wire:model="matrix.{{ $index }}" inputmode="decimal" placeholder="0.00"
                                                   aria-label="{{ __('Fee for :state (RM)', ['state' => $stateName]) }}"
                                                   class="block min-h-9 w-full rounded-lg border bg-surface px-2.5 py-1.5 text-right font-mono text-[13px] tabular-nums text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has("matrix.$index") ? 'border-danger' : 'border-line-strong' }}">
                                            @error("matrix.$index")
                                                <p class="mt-1 text-[13px] text-danger">{{ $message }}</p>
                                            @enderror
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="max-w-xs">
                <label for="freeOver" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Free shipping over (RM, optional)') }}</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-sm text-ink-faint">RM</span>
                    <input type="text" id="freeOver" wire:model="freeOver" inputmode="decimal" placeholder="40.00"
                           class="block min-h-11 w-full rounded-lg border bg-surface py-2.5 pl-11 pr-3.5 text-right font-mono text-sm tabular-nums text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('freeOver') ? 'border-danger' : 'border-line-strong' }}">
                </div>
                @error('freeOver')
                    <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
                @else
                    <p class="mt-1.5 text-[13px] text-ink-faint">{{ __('Orders at or above this amount ship free. Leave empty to disable.') }}</p>
                @enderror
            </div>

            <div class="flex justify-end border-t border-line pt-4">
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="saveShipping">{{ __('Save shipping') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    {{-- ===== Bank details ===== --}}
    <x-ui.card class="p-4 lg:p-6">
        <h2 class="text-sm font-semibold">{{ __('Bank details') }}</h2>
        <p class="mt-1 text-[13px] text-ink-soft">{{ __('Used for payouts. Each payout snapshots these details at request time.') }}</p>

        <form wire:submit="saveBank" class="mt-4 space-y-4">
            <div>
                <label for="bankName" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Bank') }}</label>
                <select id="bankName" wire:model="bankName"
                        class="block min-h-11 w-full rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('bankName') ? 'border-danger' : 'border-line-strong' }}">
                    <option value="">{{ __('Select a bank') }}</option>
                    @foreach ($banks as $bank)
                        <option value="{{ $bank }}">{{ $bank }}</option>
                    @endforeach
                </select>
                @error('bankName')
                    <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
                @enderror
            </div>

            <x-ui.input
                :label="__('Account holder name')"
                name="accountName"
                wire:model="accountName"
                maxlength="120"
                :error="$errors->first('accountName')"
            />

            <x-ui.input
                :label="__('Account number')"
                name="accountNumber"
                wire:model="accountNumber"
                inputmode="numeric"
                class="font-mono"
                :error="$errors->first('accountNumber')"
                :hint="$errors->first('accountNumber') ? null : __('8–17 digits, no spaces or dashes.')"
            />

            <div class="flex justify-end border-t border-line pt-4">
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="saveBank">{{ __('Save bank details') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</div>
