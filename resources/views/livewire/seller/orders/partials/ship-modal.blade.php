{{-- Arrange-shipment modal — shared by the order queue quick action and the detail page.
     Light surface per design §5; shadow allowed (true overlay). --}}
@if ($shippingSubOrderId !== null)
    <div
        class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
        role="dialog"
        aria-modal="true"
        aria-labelledby="ship-modal-title"
        x-data
        x-on:keydown.escape.window="$wire.closeShipModal()"
    >
        <div class="fixed inset-0 bg-ink/40" wire:click="closeShipModal" aria-hidden="true"></div>

        <div class="relative w-full max-w-md rounded-[var(--radius-card)] border border-line bg-surface p-5 shadow-pop">
            <h2 id="ship-modal-title" class="font-display text-xl font-semibold">{{ __('Arrange shipment') }}</h2>
            <p class="mt-1 text-[13px] text-ink-soft">{{ __('Pick the courier and enter the tracking number from the consignment note.') }}</p>

            <div class="mt-4 space-y-3">
                <div>
                    <label for="ship-courier" class="block text-[13px] font-medium text-ink">{{ __('Courier') }}</label>
                    <select
                        id="ship-courier"
                        wire:model.live="courier"
                        class="mt-1 block min-h-11 w-full rounded-lg border bg-surface px-3 py-2 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('courier') ? 'border-danger' : 'border-line-strong' }}"
                    >
                        <option value="">{{ __('Select a courier') }}</option>
                        @foreach ($couriers as $courierOption)
                            <option value="{{ $courierOption }}">{{ $courierOption }}</option>
                        @endforeach
                        <option value="other">{{ __('Other') }}</option>
                    </select>
                    @error('courier')
                        <p class="mt-1 text-[13px] text-danger">{{ $message }}</p>
                    @enderror
                </div>

                @if ($courier === 'other')
                    <div>
                        <label for="ship-courier-other" class="block text-[13px] font-medium text-ink">{{ __('Courier name') }}</label>
                        <input
                            id="ship-courier-other"
                            type="text"
                            wire:model="courierOther"
                            placeholder="{{ __('e.g. Skynet') }}"
                            class="mt-1 block min-h-11 w-full rounded-lg border bg-surface px-3 py-2 text-[13px] text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('courierOther') ? 'border-danger' : 'border-line-strong' }}"
                        >
                        @error('courierOther')
                            <p class="mt-1 text-[13px] text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                <div>
                    <label for="ship-tracking-no" class="block text-[13px] font-medium text-ink">{{ __('Tracking number') }}</label>
                    <input
                        id="ship-tracking-no"
                        type="text"
                        wire:model="trackingNo"
                        wire:keydown.enter="ship"
                        placeholder="MY0123456789"
                        autocomplete="off"
                        spellcheck="false"
                        class="mt-1 block min-h-11 w-full rounded-lg border bg-surface px-3 py-2 font-mono text-[13px] text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('trackingNo') ? 'border-danger' : 'border-line-strong' }}"
                    >
                    @error('trackingNo')
                        <p class="mt-1 text-[13px] text-danger">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <button
                    type="button"
                    wire:click="closeShipModal"
                    class="inline-flex min-h-11 items-center justify-center rounded-lg border border-ink px-4 text-sm font-semibold text-ink hover:bg-paper focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-2"
                >
                    {{ __('Cancel') }}
                </button>
                <button
                    type="button"
                    wire:click="ship"
                    wire:loading.attr="disabled"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-emerald px-4 text-sm font-semibold text-white hover:bg-emerald-deep active:bg-emerald-night disabled:cursor-not-allowed disabled:opacity-50 focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-2"
                >
                    <svg wire:loading wire:target="ship" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"/></svg>
                    {{ __('Mark as shipped') }}
                </button>
            </div>
        </div>
    </div>
@endif
