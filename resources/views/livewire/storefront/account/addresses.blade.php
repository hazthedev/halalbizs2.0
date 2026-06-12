<x-account-shell active="addresses" :title="__('Addresses')">
    <div class="space-y-4">
        @unless ($showForm)
            <div class="flex justify-end">
                <x-ui.button type="button" wire:click="create">{{ __('Add address') }}</x-ui.button>
            </div>
        @endunless

        {{-- Inline add / edit form --}}
        @if ($showForm)
            <x-ui.card class="p-6">
                <h2 class="font-display text-xl font-semibold">{{ $editingId ? __('Edit address') : __('Add address') }}</h2>

                <form wire:submit="save" class="mt-5 space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input
                            :label="__('Label (optional)')"
                            name="label"
                            wire:model="label"
                            :placeholder="__('Home, Office…')"
                            :error="$errors->first('label')"
                        />
                        <x-ui.input
                            :label="__('Recipient name')"
                            name="recipient_name"
                            wire:model="recipient_name"
                            autocomplete="name"
                            required
                            :error="$errors->first('recipient_name')"
                        />
                    </div>

                    <x-ui.input
                        :label="__('Phone')"
                        name="phone"
                        type="tel"
                        wire:model="phone"
                        autocomplete="tel"
                        placeholder="012-345 6789"
                        required
                        :error="$errors->first('phone')"
                    />

                    <x-ui.input
                        :label="__('Address line 1')"
                        name="line1"
                        wire:model="line1"
                        autocomplete="address-line1"
                        required
                        :error="$errors->first('line1')"
                    />

                    <x-ui.input
                        :label="__('Address line 2 (optional)')"
                        name="line2"
                        wire:model="line2"
                        autocomplete="address-line2"
                        :error="$errors->first('line2')"
                    />

                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-ui.input
                            :label="__('Postcode')"
                            name="postcode"
                            wire:model="postcode"
                            inputmode="numeric"
                            autocomplete="postal-code"
                            placeholder="50450"
                            required
                            :error="$errors->first('postcode')"
                        />
                        <x-ui.input
                            :label="__('City')"
                            name="city"
                            wire:model="city"
                            autocomplete="address-level2"
                            required
                            :error="$errors->first('city')"
                        />
                        <div>
                            <label for="state" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('State') }}</label>
                            <select id="state" wire:model="state" required
                                    class="block min-h-11 w-full rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('state') ? 'border-danger' : 'border-line-strong' }}">
                                <option value="">{{ __('Select state') }}</option>
                                @foreach ($states as $stateOption)
                                    <option value="{{ $stateOption }}">{{ $stateOption }}</option>
                                @endforeach
                            </select>
                            @error('state')
                                <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <p class="text-[13px] text-ink-faint">{{ __('Country: Malaysia — we only ship within Malaysia for now.') }}</p>

                    <div class="flex gap-3 pt-1">
                        <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                            {{ $editingId ? __('Save address') : __('Add address') }}
                        </x-ui.button>
                        <x-ui.button type="button" variant="ghost" wire:click="cancel">{{ __('Cancel') }}</x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        @endif

        {{-- Address list --}}
        @forelse ($addresses as $address)
            <x-ui.card class="p-5" wire:key="address-{{ $address->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-semibold text-ink">{{ $address->recipient_name }}</p>
                            <span class="text-[13px] text-ink-soft tnum">{{ $address->phone }}</span>
                            @if ($address->label)
                                <x-ui.badge variant="neutral">{{ $address->label }}</x-ui.badge>
                            @endif
                            @if ($address->is_default)
                                <x-ui.badge variant="neutral">{{ __('Default') }}</x-ui.badge>
                            @endif
                        </div>
                        <p class="mt-1.5 text-sm text-ink-soft">
                            {{ $address->line1 }}@if ($address->line2), {{ $address->line2 }}@endif
                        </p>
                        <p class="text-sm text-ink-soft">
                            <span class="tnum">{{ $address->postcode }}</span> {{ $address->city }}, {{ $address->state }}
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-1">
                        @unless ($address->is_default)
                            <button type="button" wire:click="setDefault({{ $address->id }})"
                                    class="flex min-h-11 items-center rounded-lg px-3 text-[13px] font-medium text-ink-soft hover:text-ink">
                                {{ __('Set as default') }}
                            </button>
                        @endunless
                        <button type="button" wire:click="edit({{ $address->id }})"
                                class="flex min-h-11 items-center rounded-lg px-3 text-[13px] font-medium text-ink-soft hover:text-ink">
                            {{ __('Edit') }}
                        </button>
                        <button type="button" wire:click="delete({{ $address->id }})"
                                wire:confirm="{{ __('Delete this address?') }}"
                                class="flex min-h-11 items-center rounded-lg px-3 text-[13px] font-medium text-danger hover:bg-danger-tint">
                            {{ __('Delete') }}
                        </button>
                    </div>
                </div>
            </x-ui.card>
        @empty
            @unless ($showForm)
                <x-ui.card class="px-6 py-16 text-center">
                    <p class="font-display text-xl font-semibold">{{ __('No addresses yet') }}</p>
                    <p class="mt-1 text-sm text-ink-soft">{{ __('Add a delivery address and checkout will fill itself in.') }}</p>
                </x-ui.card>
            @endunless
        @endforelse
    </div>
</x-account-shell>
