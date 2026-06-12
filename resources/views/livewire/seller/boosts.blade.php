<div class="space-y-4">

    {{-- Header --}}
    <div>
        <h1 class="font-display text-2xl font-bold">{{ __('Boosts') }}</h1>
        <p class="mt-1 max-w-prose text-[13px] text-ink-soft">
            {{ __('Boosted products lead the top of category and search results with a Sponsored label, and open the Popular now section on the home page.') }}
        </p>
    </div>

    {{-- Pricing / status strip --}}
    <div class="grid gap-3 sm:grid-cols-3">
        <x-ui.card class="p-4">
            <p class="text-[13px] text-ink-soft">{{ __('Price per day') }}</p>
            <p class="mt-1 font-display text-xl font-bold tabular-nums">@money($settings->price_sen_per_day)</p>
        </x-ui.card>
        <x-ui.card class="p-4">
            <p class="text-[13px] text-ink-soft">{{ __('Active boosts') }}</p>
            <p class="mt-1 font-display text-xl font-bold tabular-nums">{{ $activeCount }} / {{ $settings->max_active_per_store }}</p>
        </x-ui.card>
        <x-ui.card class="p-4">
            <p class="text-[13px] text-ink-soft">{{ __('Available balance') }}</p>
            <p class="mt-1 font-display text-xl font-bold tabular-nums">@money($availableSen)</p>
        </x-ui.card>
    </div>

    {{-- Boost a product --}}
    <x-ui.card class="p-5">
        <h2 class="font-display text-lg font-semibold">{{ __('Boost a product') }}</h2>
        <p class="mt-1 text-[13px] text-ink-soft">
            {{ __('The fee is paid up-front from your available earnings. Cancelling a boost stops the placement immediately — unused days are not refunded.') }}
        </p>

        @if (! $settings->enabled)
            <div class="mt-4 rounded-lg border border-line bg-paper px-3.5 py-2.5 text-[13px] text-ink-soft">
                {{ __('Boosts are switched off right now — check back soon.') }}
            </div>
        @elseif ($products->isEmpty())
            <div class="mt-4 rounded-lg border border-line bg-paper px-3.5 py-2.5 text-[13px] text-ink-soft">
                {{ __('You need at least one live product to start a boost.') }}
            </div>
        @else
            <div class="mt-4 flex flex-wrap items-end gap-3">
                <div class="min-w-56 flex-1">
                    <label for="boost-product" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Product') }}</label>
                    <select id="boost-product" wire:model.live="productId"
                            class="block min-h-11 w-full rounded-lg border bg-surface px-3 py-2 text-sm text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('productId') ? 'border-danger' : 'border-line-strong' }}">
                        <option value="">{{ __('Choose a live product') }}</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}">{{ $product->getTranslation('name', app()->getLocale()) }}</option>
                        @endforeach
                    </select>
                    @error('productId')<p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>@enderror
                </div>

                <div class="w-28">
                    <label for="boost-days" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Days') }}</label>
                    <input id="boost-days" type="number" min="1" max="30" wire:model.live="days" inputmode="numeric"
                           class="block min-h-11 w-full rounded-lg border bg-surface px-3 py-2 text-sm text-ink tabular-nums focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('days') ? 'border-danger' : 'border-line-strong' }}">
                    @error('days')<p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>@enderror
                </div>

                <div class="pb-2.5 text-sm text-ink-soft">
                    {{ __('Cost') }}
                    <span class="ml-1 font-bold text-ink tabular-nums">@money($costSen)</span>
                </div>

                <x-ui.button
                    wire:click="boost"
                    wire:confirm="{{ __('Start this boost? The fee is charged from your available earnings now and is not refunded if you cancel early.') }}"
                    wire:loading.attr="disabled"
                    wire:target="boost"
                >
                    {{ __('Boost now') }}
                </x-ui.button>
            </div>
        @endif
    </x-ui.card>

    {{-- Active & past boosts --}}
    <x-ui.card class="overflow-x-auto">
        @if ($boosts->isEmpty())
            <div class="px-6 py-16 text-center">
                <h2 class="font-display text-xl font-semibold">{{ __('No boosts yet') }}</h2>
                <p class="mt-1 text-sm text-ink-soft">{{ __('Boost a live product and it jumps to the top of category and search results.') }}</p>
            </div>
        @else
            <table class="w-full min-w-[720px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Product') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Window') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Cost') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Status') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($boosts as $boost)
                        @php
                            $pill = match ($boost->status) {
                                \App\Enums\BoostStatus::Active => 'sale',
                                \App\Enums\BoostStatus::Expired => 'neutral',
                                \App\Enums\BoostStatus::Cancelled => 'danger',
                            };
                        @endphp
                        <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="boost-{{ $boost->id }}">
                            <td class="max-w-72 px-3 py-2">
                                <span class="line-clamp-1 font-medium text-ink">
                                    {{ $boost->product?->getTranslation('name', app()->getLocale()) ?? __('Deleted product') }}
                                </span>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-[12px] text-ink-soft">
                                {{ $boost->starts_at->format('d M Y H:i') }} → {{ $boost->ends_at->format('d M Y H:i') }}
                            </td>
                            <td class="px-3 py-2 text-right font-semibold tabular-nums whitespace-nowrap">@money($boost->amount_sen)</td>
                            <td class="px-3 py-2"><x-ui.badge :variant="$pill">{{ $boost->status->label() }}</x-ui.badge></td>
                            <td class="px-3 py-2">
                                <div class="flex justify-end">
                                    @if ($boost->status === \App\Enums\BoostStatus::Active)
                                        <button type="button"
                                                wire:click="cancel({{ $boost->id }})"
                                                wire:confirm="{{ __('Cancel this boost? It stops appearing immediately and the remaining days are not refunded.') }}"
                                                class="inline-flex min-h-11 items-center rounded-lg px-2 font-medium text-danger hover:bg-danger-tint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                            {{ __('Cancel') }}
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>
</div>
