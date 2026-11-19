<div class="space-y-4">

    {{-- Header --}}
    <x-ui.section-heading :title="__('Moderation')" as="h1" />

    @unless ($requireApproval)
        <div class="rounded-[var(--radius-card)] border border-line bg-surface px-4 py-3 text-[13px] text-ink-soft shadow-soft">
            {{ __('Product approval is off — new products go live immediately. Turn it on under System → Settings to route them through this queue.') }}
        </div>
    @endunless

    {{-- Filters --}}
    @php $fieldClass = 'block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald'; @endphp
    <x-ui.card class="p-3">
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-6">
            <div class="min-w-0 lg:col-span-2">
                <label for="mod-search" class="sr-only">{{ __('Search products') }}</label>
                <input id="mod-search" type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search product or store') }}" class="{{ $fieldClass }} placeholder:text-ink-faint">
            </div>
            <div>
                <label for="mod-store" class="sr-only">{{ __('Store') }}</label>
                <select id="mod-store" wire:model.live="store" class="{{ $fieldClass }}">
                    <option value="">{{ __('All stores') }}</option>
                    @foreach ($stores as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="mod-category" class="sr-only">{{ __('Category') }}</label>
                <select id="mod-category" wire:model.live="category" class="{{ $fieldClass }}">
                    <option value="">{{ __('All categories') }}</option>
                    @foreach ($categories as $categoryRow)
                        <option value="{{ $categoryRow->id }}">{{ $categoryRow->getTranslation('name', 'en') }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex min-w-0 items-center gap-2 lg:col-span-2">
                <label for="mod-from" class="sr-only">{{ __('Submitted from') }}</label>
                <input id="mod-from" type="date" wire:model.live="dateFrom" class="{{ $fieldClass }} min-w-0">
                <span class="text-[13px] text-ink-faint" aria-hidden="true">–</span>
                <label for="mod-to" class="sr-only">{{ __('Submitted to') }}</label>
                <input id="mod-to" type="date" wire:model.live="dateTo" class="{{ $fieldClass }} min-w-0">
            </div>
        </div>
        @if ($search !== '' || $store !== '' || $category !== '' || $dateFrom !== '' || $dateTo !== '')
            <button type="button" wire:click="resetFilters" class="mt-2 inline-flex min-h-11 items-center text-[13px] font-medium text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Clear filters') }}</button>
        @endif
    </x-ui.card>

    {{-- Bulk actions --}}
    <x-ui.table.bulk-bar :selected="$selected">
        <x-ui.button wire:click="bulkApprove">{{ __('Approve selected') }}</x-ui.button>
        <x-ui.button variant="secondary" wire:click="startBulkReject">{{ __('Reject selected') }}</x-ui.button>
        <x-ui.button variant="danger" wire:click="bulkBan"
                wire:confirm="{{ __('Ban the selected products? They disappear from the storefront and the sellers are notified.') }}">
            {{ __('Ban selected') }}
        </x-ui.button>
    </x-ui.table.bulk-bar>

    {{-- Pending queue --}}
    <x-ui.card class="overflow-x-auto">
        <x-ui.table-skeleton wire:loading wire:target="search, store, category, dateFrom, dateTo, resetFilters, sortBy, perPage" />
        <div wire:loading.remove wire:target="search, store, category, dateFrom, dateTo, resetFilters, sortBy, perPage">
        @if ($pending->isEmpty())
            <x-ui.empty-state :title="__('Queue clear')" :message="__('Products waiting for review appear here the moment sellers submit them.')" />
        @else
            <table class="w-full min-w-[880px] text-[13px]">
                <thead class="sticky top-0 z-10 bg-surface">
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="w-10 px-3 py-2.5">
                            <input type="checkbox" wire:model.live="selectPage" aria-label="{{ __('Select all on this page') }}"
                                   class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                        </th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Product') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Store') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Category') }}</th>
                        <x-ui.table.sortable field="price" :sort="$sort" :direction="$direction" class="px-3 py-2.5 text-right font-medium">{{ __('Price') }}</x-ui.table.sortable>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Status') }}</th>
                        <x-ui.table.sortable field="submitted" :sort="$sort" :direction="$direction">{{ __('Submitted') }}</x-ui.table.sortable>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pending as $product)
                        <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="pending-{{ $product->id }}">
                            <td class="px-3 py-2">
                                <input type="checkbox" wire:model.live="selected" value="{{ $product->id }}"
                                       aria-label="{{ __('Select :name', ['name' => $product->getTranslation('name', 'en')]) }}"
                                       class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                            </td>
                            <td class="px-3 py-2">
                                @include('livewire.admin.catalog.partials.moderation-product-cell', ['product' => $product])
                            </td>
                            <td class="px-3 py-2 text-ink-soft">{{ $product->store?->name }}</td>
                            <td class="px-3 py-2 text-ink-soft">{{ $product->category?->getTranslation('name', 'en') }}</td>
                            <td class="px-3 py-2 text-right font-medium tabular-nums whitespace-nowrap">
                                @if ($product->variants->isNotEmpty())
                                    @money($product->minPriceSen())@if ($product->maxPriceSen() !== $product->minPriceSen()) – @money($product->maxPriceSen())@endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-2"><x-ui.badge variant="warn">{{ $product->status->label() }}</x-ui.badge></td>
                            <td class="px-3 py-2 whitespace-nowrap text-[12px] text-ink-soft">{{ $product->updated_at->diffForHumans() }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" wire:click="approve({{ $product->id }})" wire:loading.attr="disabled"
                                            class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-2 font-medium text-emerald hover:text-emerald-deep focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Approve') }}</button>
                                    <button type="button" wire:click="startReject({{ $product->id }})"
                                            class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-2 font-medium text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Reject') }}</button>
                                    <button type="button" wire:click="ban({{ $product->id }})"
                                            wire:confirm="{{ __('Ban ":name"? It disappears from the storefront and the seller is notified.', ['name' => $product->getTranslation('name', 'en')]) }}"
                                            class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-2 font-medium text-danger hover:bg-danger-tint focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Ban') }}</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        </div>
    </x-ui.card>

    {{-- Empty queue: the empty state says it; "Showing 0 to 0 of 0" under it is noise. --}}
    @if ($pending->total() > 0)
        <x-ui.table.footer :rows="$pending" />
    @endif

    {{-- Banned reference list --}}
    <div class="space-y-2 pt-2">
        <x-ui.section-heading :title="__('Banned products')" />
        <x-ui.card class="overflow-x-auto">
            @if ($banned->isEmpty())
                <x-ui.empty-state :title="__('No banned products')" :message="__('Products you ban from the queue above are listed here for reference.')" />
            @else
                <table class="w-full min-w-[720px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-ink-soft">
                            <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Product') }}</th>
                            <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Store') }}</th>
                            <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Category') }}</th>
                            <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Price') }}</th>
                            <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Status') }}</th>
                            <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Banned') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($banned as $product)
                            <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="banned-{{ $product->id }}">
                                <td class="px-3 py-2">
                                    @include('livewire.admin.catalog.partials.moderation-product-cell', ['product' => $product])
                                </td>
                                <td class="px-3 py-2 text-ink-soft">{{ $product->store?->name }}</td>
                                <td class="px-3 py-2 text-ink-soft">{{ $product->category?->getTranslation('name', 'en') }}</td>
                                <td class="px-3 py-2 text-right font-medium tabular-nums whitespace-nowrap">
                                    @if ($product->variants->isNotEmpty())
                                        @money($product->minPriceSen())@if ($product->maxPriceSen() !== $product->minPriceSen()) – @money($product->maxPriceSen())@endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-2"><x-ui.badge variant="danger">{{ $product->status->label() }}</x-ui.badge></td>
                                <td class="px-3 py-2 whitespace-nowrap text-[12px] text-ink-soft">{{ $product->updated_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-ui.card>
    </div>

    {{-- Reject modal --}}
    @if ($rejectOpen)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-emerald-night/40 p-4 sm:p-8" wire:click.self="cancelReject">
            <x-ui.card class="w-full max-w-lg shadow-pop">
                <form wire:submit="confirmReject">
                    <div class="border-b border-line px-5 py-4">
                        <h2 class="font-display text-lg font-medium">
                            {{ trans_choice('{1}Reject product|[2,*]Reject :count products', count($rejectIds), ['count' => count($rejectIds)]) }}
                        </h2>
                    </div>
                    <div class="space-y-2 px-5 py-4">
                        <label for="reject-reason" class="block text-[13px] font-medium text-ink">{{ __('Reason for the seller') }}</label>
                        <textarea id="reject-reason" wire:model="rejectReason" rows="3" required
                                  placeholder="{{ __('e.g. Images are blurry — re-shoot on a plain background.') }}"
                                  class="block w-full rounded-[var(--radius-control)] border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('rejectReason') ? 'border-danger' : 'border-line-strong' }}"></textarea>
                        @error('rejectReason')<p class="text-[13px] text-danger">{{ $message }}</p>@enderror
                        <p class="text-[13px] text-ink-faint">{{ __('The product moves back to drafts and the seller gets this reason by email and in-app.') }}</p>
                    </div>
                    <div class="flex items-center justify-end gap-2 border-t border-line px-5 py-4">
                        <button type="button" wire:click="cancelReject" class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-3 text-[13px] font-medium text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Cancel') }}</button>
                        <button type="submit" wire:loading.attr="disabled"
                                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-[var(--radius-control)] bg-danger px-4 py-2.5 text-sm font-medium text-white hover:opacity-90 focus-visible:ring-2 focus-visible:ring-emerald disabled:cursor-not-allowed disabled:opacity-50">
                            {{ __('Reject and notify') }}
                        </button>
                    </div>
                </form>
            </x-ui.card>
        </div>
    @endif
</div>
