<div class="space-y-4">

    {{-- Header --}}
    <x-ui.section-heading as="h1" :title="__('Products')">
        <x-slot:actions>
            <x-ui.button :href="route('seller.products.create')">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                {{ __('Add product') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.section-heading>

    {{-- Filters --}}
    <x-ui.card class="p-3">
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative min-w-48 flex-1">
                <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/></svg>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Search name or SKU') }}"
                    aria-label="{{ __('Search name or SKU') }}"
                    class="block min-h-11 w-full rounded-lg border border-line-strong bg-surface py-2 pl-9 pr-3 text-[13px] text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald"
                >
            </div>

            <select wire:model.live="status" aria-label="{{ __('Filter by status') }}"
                    class="min-h-11 rounded-lg border border-line-strong bg-surface px-3 py-2 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                <option value="">{{ __('All statuses') }}</option>
                @foreach ($statuses as $statusCase)
                    <option value="{{ $statusCase->value }}">{{ $statusCase->label() }}</option>
                @endforeach
            </select>

            <select wire:model.live="category" aria-label="{{ __('Filter by category') }}"
                    class="min-h-11 max-w-56 rounded-lg border border-line-strong bg-surface px-3 py-2 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                <option value="">{{ __('All categories') }}</option>
                @foreach ($categoryOptions as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>

            <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border border-line-strong bg-surface px-3 py-2 text-[13px] font-medium text-ink">
                <input type="checkbox" wire:model.live="lowStock" class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                {{ __('Low stock') }}
            </label>
        </div>
    </x-ui.card>

    {{-- Bulk actions --}}
    @if (count($selected) > 0)
        <div class="flex items-center gap-3 rounded-[var(--radius-card)] border border-line bg-surface px-4 py-2 shadow-soft">
            <span class="text-[13px] font-medium text-ink">{{ trans_choice('{1}:count selected|[2,*]:count selected', count($selected), ['count' => count($selected)]) }}</span>
            <button type="button" wire:click="bulkDelist"
                    class="inline-flex min-h-11 items-center rounded-lg border border-ink px-3 text-[13px] font-semibold text-ink hover:bg-paper">
                {{ __('Delist selected') }}
            </button>
            <button type="button" wire:click="bulkDelete"
                    wire:confirm="{{ __('Delete the selected drafts? Only drafts are deleted — this cannot be undone.') }}"
                    class="inline-flex min-h-11 items-center rounded-lg border border-danger px-3 text-[13px] font-semibold text-danger hover:bg-danger-tint">
                {{ __('Delete drafts') }}
            </button>
        </div>
    @endif

    {{-- Datagrid --}}
    <x-ui.card class="overflow-x-auto">
        {{-- Row skeletons while search/filters refresh the grid (design §6) --}}
        <x-ui.table-skeleton wire:loading wire:target="search, status, category, lowStock" />
        <div wire:loading.remove wire:target="search, status, category, lowStock">
        @if ($products->isEmpty())
            @if ($search !== '' || $status !== '' || $category !== null || $lowStock)
                <x-ui.empty-state :title="__('No products match')" :message="__('Try removing a filter or searching for something else.')" />
            @else
                <x-ui.empty-state :title="__('Nothing on the shelves yet')" :message="__('Your products appear here once you add them.')">
                    <x-ui.button :href="route('seller.products.create')">{{ __('Add your first product') }}</x-ui.button>
                </x-ui.empty-state>
            @endif
        @else
            <table class="w-full min-w-[820px] text-[13px]">
                <thead class="sticky top-14 z-10 bg-surface">
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="w-10 px-3 py-2.5">
                            <input type="checkbox" wire:model.live="selectPage" aria-label="{{ __('Select all on this page') }}"
                                   class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                        </th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Product') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Variants') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Price') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Stock') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Sold') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Status') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Updated') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        @php
                            $stockTotal = $product->variants->sum('stock');
                            $minSen = $product->variants->isNotEmpty() ? $product->minPriceSen() : 0;
                            $maxSen = $product->variants->isNotEmpty() ? $product->maxPriceSen() : 0;
                            $pill = match ($product->status) {
                                \App\Enums\ProductStatus::Draft, \App\Enums\ProductStatus::Delisted => 'neutral',
                                \App\Enums\ProductStatus::PendingReview => 'warn',
                                \App\Enums\ProductStatus::Live => 'sale',
                                \App\Enums\ProductStatus::Banned => 'danger',
                            };
                        @endphp
                        <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="product-{{ $product->id }}">
                            <td class="px-3 py-2">
                                <input type="checkbox" wire:model.live="selected" value="{{ $product->id }}"
                                       aria-label="{{ __('Select :name', ['name' => $product->getTranslation('name', 'en')]) }}"
                                       class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                            </td>
                            <td class="px-3 py-2">
                                <a href="{{ route('seller.products.edit', $product) }}" wire:navigate class="flex items-center gap-3">
                                    @if ($url = $product->getFirstMediaUrl('images', 'thumb'))
                                        <img src="{{ $url }}" alt="{{ $product->getTranslation('name', 'en') }}" class="size-10 shrink-0 rounded-lg border border-line bg-paper object-cover">
                                    @else
                                        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-line bg-paper text-ink-faint">
                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 19.5h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Z"/></svg>
                                        </span>
                                    @endif
                                    <span class="line-clamp-2 max-w-72 font-medium text-ink">{{ $product->getTranslation('name', 'en') }}</span>
                                </a>
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums text-ink-soft">{{ $product->variants_count }}</td>
                            <td class="px-3 py-2 text-right font-semibold tabular-nums whitespace-nowrap">
                                @money($minSen)@if ($maxSen !== $minSen) – @money($maxSen)@endif
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums {{ $stockTotal < $lowStockThreshold ? 'font-semibold text-warn' : '' }}">{{ $stockTotal }}</td>
                            <td class="px-3 py-2 text-right tabular-nums text-ink-soft">{{ $product->sold_count }}</td>
                            <td class="px-3 py-2"><x-ui.badge :variant="$pill">{{ $product->status->label() }}</x-ui.badge></td>
                            <td class="px-3 py-2 whitespace-nowrap text-[12px] text-ink-soft">{{ $product->updated_at->diffForHumans() }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('seller.products.edit', $product) }}" wire:navigate
                                       class="inline-flex min-h-11 items-center rounded-lg px-2 font-medium text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Edit') }}</a>
                                    <button type="button" wire:click="duplicate({{ $product->id }})" wire:loading.attr="disabled"
                                            class="inline-flex min-h-11 items-center rounded-lg px-2 font-medium text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Duplicate') }}</button>
                                    @if ($product->status === \App\Enums\ProductStatus::Live)
                                        <a href="{{ route('seller.boosts', ['product' => $product->id]) }}" wire:navigate
                                           class="inline-flex min-h-11 items-center rounded-lg px-2 font-medium text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Boost') }}</a>
                                        <button type="button" wire:click="delist({{ $product->id }})"
                                                class="inline-flex min-h-11 items-center rounded-lg px-2 font-medium text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Delist') }}</button>
                                    @elseif ($product->status === \App\Enums\ProductStatus::Delisted)
                                        <button type="button" wire:click="relist({{ $product->id }})"
                                                class="inline-flex min-h-11 items-center rounded-lg px-2 font-medium text-emerald hover:text-emerald-deep focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Relist') }}</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        </div>
    </x-ui.card>

    @if ($products->hasPages())
        <div>{{ $products->links() }}</div>
    @endif
</div>
