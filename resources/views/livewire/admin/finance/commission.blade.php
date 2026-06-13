<div class="space-y-4">

    {{-- Header --}}
    <x-ui.section-heading :title="__('Commission')" :subtitle="__('Hierarchy: store override → category chain upward → global default. Sub-orders snapshot their rate at checkout — changes here only affect future orders.')" as="h1" />

    <div class="grid gap-4 lg:grid-cols-2">

        {{-- Global rate --}}
        <x-ui.card class="p-4">
            <h2 class="text-sm font-semibold">{{ __('Global default rate') }}</h2>
            <p class="mt-1 text-[13px] text-ink-soft">{{ __('Applies when neither the store nor the category chain has an override.') }}</p>
            <form wire:submit="saveGlobalRate" class="mt-3 flex items-end gap-2">
                <div class="flex-1">
                    <label for="global-rate" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Rate (%)') }}</label>
                    <input id="global-rate" type="number" step="0.01" min="0" max="100" wire:model="globalRate"
                           class="block min-h-11 w-full rounded-[var(--radius-control)] border bg-surface px-3 text-sm tabular-nums text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('globalRate') ? 'border-danger' : 'border-line-strong' }}">
                </div>
                <button type="submit" wire:loading.attr="disabled"
                        class="inline-flex min-h-11 items-center justify-center rounded-[var(--radius-control)] bg-emerald px-4 text-sm font-semibold text-white hover:bg-emerald-deep active:bg-emerald-night disabled:cursor-not-allowed disabled:opacity-50 focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-2">
                    {{ __('Save rate') }}
                </button>
            </form>
            @error('globalRate')
                <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
            @enderror
        </x-ui.card>

        {{-- Effective-rate tester (docs/08 §F — living documentation) --}}
        <x-ui.card class="p-4">
            <h2 class="text-sm font-semibold">{{ __('Effective-rate tester') }}</h2>
            <p class="mt-1 text-[13px] text-ink-soft">{{ __('Pick a store and category to see the rate a new sub-order would snapshot — resolved by the real CommissionResolver.') }}</p>
            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                <div>
                    <label for="tester-store" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Store') }}</label>
                    <select id="tester-store" wire:model.live="testerStoreId"
                            class="block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                        <option value="">{{ __('Select a store') }}</option>
                        @foreach ($stores as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="tester-category" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Category') }}</label>
                    <select id="tester-category" wire:model.live="testerCategoryId"
                            class="block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                        <option value="">{{ __('No category') }}</option>
                        @foreach ($categories as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            @if ($tester !== null)
                <div class="mt-3 rounded-[var(--radius-card)] border border-line bg-paper p-3">
                    <p class="font-display text-3xl font-bold tabular-nums">{{ $tester['rate'] }}%</p>
                    <p class="mt-0.5 text-[13px] text-ink-soft">{{ $tester['source'] }}</p>
                </div>
            @else
                <p class="mt-3 text-[13px] text-ink-faint">{{ __('Select a store to resolve its effective rate.') }}</p>
            @endif
        </x-ui.card>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">

        {{-- Category overrides summary --}}
        <x-ui.card class="overflow-x-auto">
            <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
                <h2 class="text-sm font-semibold">{{ __('Category overrides') }}</h2>
                <a href="{{ route('admin.catalog.categories') }}" wire:navigate
                   class="inline-flex min-h-11 items-center text-[13px] font-medium text-emerald hover:text-emerald-deep focus-visible:ring-2 focus-visible:ring-emerald">
                    {{ __('Edit in Catalog → Categories') }}
                </a>
            </div>
            @if ($categoryOverrides->isEmpty())
                <p class="px-4 py-6 text-[13px] text-ink-soft">{{ __('No category carries its own rate — the chain falls through to the global default.') }}</p>
            @else
                <table class="w-full text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-ink-soft">
                            <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Category') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-right font-medium">{{ __('Rate') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($categoryOverrides as $category)
                            <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="category-override-{{ $category->id }}">
                                <td class="px-4 py-2">
                                    {{ $category->getTranslation('name', app()->getLocale()) }}
                                    @if ($category->parent)
                                        <span class="text-[12px] text-ink-faint">· {{ __('in :parent', ['parent' => $category->parent->getTranslation('name', app()->getLocale())]) }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right font-mono tabular-nums">{{ \App\Livewire\Admin\Finance\Commission::formatRate((float) $category->commission_rate) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-ui.card>

        {{-- Store overrides summary --}}
        <x-ui.card class="overflow-x-auto">
            <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
                <h2 class="text-sm font-semibold">{{ __('Store overrides') }}</h2>
                <a href="{{ route('admin.sellers.stores') }}" wire:navigate
                   class="inline-flex min-h-11 items-center text-[13px] font-medium text-emerald hover:text-emerald-deep focus-visible:ring-2 focus-visible:ring-emerald">
                    {{ __('Edit in Sellers → Stores') }}
                </a>
            </div>
            @if ($storeOverrides->isEmpty())
                <p class="px-4 py-6 text-[13px] text-ink-soft">{{ __('No store has a negotiated rate — every store follows its categories or the global default.') }}</p>
            @else
                <table class="w-full text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-ink-soft">
                            <th scope="col" class="px-4 py-2.5 font-medium">{{ __('Store') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-right font-medium">{{ __('Rate') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($storeOverrides as $store)
                            <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="store-override-{{ $store->id }}">
                                <td class="px-4 py-2">
                                    <a href="{{ route('admin.sellers.stores.show', $store) }}" wire:navigate
                                       class="text-ink underline-offset-2 hover:text-emerald hover:underline focus-visible:ring-2 focus-visible:ring-emerald">
                                        {{ $store->name }}
                                    </a>
                                </td>
                                <td class="px-4 py-2 text-right font-mono tabular-nums">{{ \App\Livewire\Admin\Finance\Commission::formatRate((float) $store->commission_rate) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-ui.card>
    </div>
</div>
