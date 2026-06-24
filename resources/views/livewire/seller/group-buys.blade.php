<div class="mx-auto w-full max-w-5xl px-4 py-6 lg:py-8">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="font-display text-2xl font-bold">{{ __('Group buys') }}</h1>
            <p class="mt-1 text-[13px] text-ink-soft">{{ __('Offer a lower price that unlocks when shoppers team up.') }}</p>
        </div>
        <button type="button" wire:click="create"
                class="inline-flex min-h-11 items-center justify-center rounded-[var(--radius-control)] bg-emerald px-4 text-sm font-semibold text-white hover:bg-emerald-deep focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-1">
            {{ __('Create deal') }}
        </button>
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-5 space-y-4 rounded-[var(--radius-card)] border border-line bg-surface p-5 shadow-soft">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="gb-product" class="text-[13px] font-medium">{{ __('Product') }}</label>
                    <select id="gb-product" wire:model.live="productId" class="mt-1 block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-sm">
                        <option value="">{{ __('Choose a product') }}</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}">{{ $product->getTranslation('name', 'en') }}</option>
                        @endforeach
                    </select>
                    @error('productId') <p class="mt-1 text-[13px] text-danger">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="gb-variant" class="text-[13px] font-medium">{{ __('Variant') }}</label>
                    <select id="gb-variant" wire:model="variantId" class="mt-1 block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-sm">
                        <option value="">{{ __('Choose a variant') }}</option>
                        @php($selected = $products->firstWhere('id', $productId))
                        @foreach ($selected?->variants ?? [] as $variant)
                            <option value="{{ $variant->id }}">{{ $variant->options_label ?: __('Default') }} — @money($variant->effectivePriceSen())</option>
                        @endforeach
                    </select>
                    @error('variantId') <p class="mt-1 text-[13px] text-danger">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="gb-price" class="text-[13px] font-medium">{{ __('Group price (RM)') }}</label>
                    <input id="gb-price" type="text" wire:model="groupPrice" inputmode="decimal" placeholder="0.00"
                           class="mt-1 block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-sm tnum">
                    @error('groupPrice') <p class="mt-1 text-[13px] text-danger">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="gb-target" class="text-[13px] font-medium">{{ __('People needed') }}</label>
                    <input id="gb-target" type="number" min="2" max="100" wire:model="targetSize"
                           class="mt-1 block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-sm tnum">
                    @error('targetSize') <p class="mt-1 text-[13px] text-danger">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="gb-window" class="text-[13px] font-medium">{{ __('Team window (hours)') }}</label>
                    <input id="gb-window" type="number" min="1" max="168" wire:model="windowHours"
                           class="mt-1 block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-sm tnum">
                    @error('windowHours') <p class="mt-1 text-[13px] text-danger">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="gb-ends" class="text-[13px] font-medium">{{ __('Deal ends') }}</label>
                    <input id="gb-ends" type="datetime-local" wire:model="endsAt"
                           class="mt-1 block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-sm">
                    @error('endsAt') <p class="mt-1 text-[13px] text-danger">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-[var(--radius-control)] bg-emerald px-5 text-sm font-semibold text-white hover:bg-emerald-deep">{{ __('Save deal') }}</button>
                <button type="button" wire:click="$set('showForm', false)" class="inline-flex min-h-11 items-center justify-center rounded-[var(--radius-control)] border border-line-strong bg-surface px-5 text-sm font-semibold text-ink hover:border-ink">{{ __('Cancel') }}</button>
            </div>
        </form>
    @endif

    <div class="mt-6 overflow-hidden rounded-[var(--radius-card)] border border-line bg-surface shadow-soft">
        @if ($deals->isEmpty())
            <p class="px-5 py-10 text-center text-sm text-ink-soft">{{ __('No group-buy deals yet.') }}</p>
        @else
            <ul class="divide-y divide-line">
                @foreach ($deals as $deal)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-ink">{{ $deal->product?->getTranslation('name', 'en') }}</p>
                            <p class="text-[13px] text-ink-soft">
                                {{ $deal->variant?->options_label ?: __('Default') }} ·
                                <span class="font-semibold text-ink">@money($deal->group_price_sen)</span> ·
                                {{ __(':n people', ['n' => $deal->target_size]) }} ·
                                {{ __(':n teams', ['n' => $deal->teams_count]) }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <x-ui.badge :variant="$deal->status === \App\Enums\GroupBuyStatus::Active ? 'success' : 'neutral'">{{ $deal->status->label() }}</x-ui.badge>
                            @if ($deal->status === \App\Enums\GroupBuyStatus::Active)
                                <button type="button" wire:click="end({{ $deal->id }})" class="text-[13px] font-semibold text-ink-soft hover:text-danger">{{ __('End') }}</button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
