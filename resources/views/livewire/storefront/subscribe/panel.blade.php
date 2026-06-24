<div>
    @if ($show)
        <section class="mt-5 rounded-[var(--radius-card)] border border-line bg-paper p-4" aria-label="{{ __('Subscribe and save') }}">
            <div class="flex items-center gap-2">
                <svg class="size-5 text-emerald" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                <h2 class="text-sm font-semibold text-ink">{{ __('Subscribe & save :pct%', ['pct' => rtrim(rtrim(number_format($discountBp / 100, 2), '0'), '.')]) }}</h2>
            </div>
            <p class="mt-1 text-[13px] text-ink-soft">
                {{ __('Recurring delivery at') }}
                <span class="font-bold text-ink tnum">@price($subPriceSen)</span>.
                {{ __('Pay on delivery each time. Skip or cancel whenever.') }}
            </p>

            <div class="mt-3 flex flex-wrap items-end gap-2">
                <label class="min-w-0 flex-1">
                    <span class="sr-only">{{ __('Delivery frequency') }}</span>
                    <select wire:model="interval" class="block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-sm">
                        @foreach ($intervals as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="button" wire:click="subscribe" wire:loading.attr="disabled" wire:target="subscribe"
                        class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-[var(--radius-control)] border border-emerald bg-emerald-tint px-4 text-sm font-semibold text-emerald transition-colors hover:bg-emerald hover:text-white disabled:opacity-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-1">
                    {{ __('Subscribe') }}
                </button>
            </div>
        </section>
    @endif
</div>
