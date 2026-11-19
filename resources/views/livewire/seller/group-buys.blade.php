<div class="space-y-4">

    <x-ui.section-heading as="h1" :title="__('Group buys')" :subtitle="__('Offer a lower price that unlocks when shoppers team up.')">
        <x-slot:actions>
            <x-ui.button icon="plus" :href="route('seller.group-buys.create')">
                {{ __('Create deal') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.section-heading>

    @if (session('status'))
        <p role="status" class="text-sm text-emerald">{{ session('status') }}</p>
    @endif

    <x-ui.card class="p-3">
        <div class="relative min-w-48">
            <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/></svg>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search product') }}" aria-label="{{ __('Search product') }}"
                   class="block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface py-2 pl-9 pr-3 text-[13px] text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
        </div>
    </x-ui.card>

    <x-ui.table.bulk-bar :selected="$selected">
        <x-ui.button variant="plain" type="button" wire:click="bulkEnd"
                class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] border border-ink text-ink hover:bg-paper px-3 text-[13px] font-medium " wire:loading.attr="disabled" wire:target="bulkEnd">
            {{ __('End selected') }}
        </x-ui.button>
    </x-ui.table.bulk-bar>

    <x-ui.table.per-page />

    <x-ui.card class="overflow-x-auto">
        <x-ui.table-skeleton wire:loading wire:target="search, sortBy, perPage, gotoPage, nextPage, previousPage" />
        <div wire:loading.remove wire:target="search, sortBy, perPage, gotoPage, nextPage, previousPage">
        @if ($deals->isEmpty() && $search !== '')
            <x-ui.empty-state :title="__('No deals match')" :message="__('Try searching for something else.')" />
        @elseif ($deals->isEmpty())
            <x-ui.empty-state :title="__('No group-buy deals yet')" :message="__('Create a deal to offer a lower price when shoppers team up.')" />
        @else
            <table class="w-full min-w-[820px] text-[13px]">
                <thead class="sticky top-0 z-10 bg-surface">
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="w-10 px-3 py-5">
                            <input type="checkbox" wire:model.live="selectPage" aria-label="{{ __('Select all on this page') }}" class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                        </th>
                        <th scope="col" class="px-3 py-5 font-medium">{{ __('Product') }}</th>
                        <th scope="col" class="px-3 py-5 font-medium">{{ __('Size') }}</th>
                        <x-ui.table.sortable field="price" :sort="$sort" :direction="$direction" class="px-3 py-5 text-right font-medium">{{ __('Team price') }}</x-ui.table.sortable>
                        <x-ui.table.sortable field="people" :sort="$sort" :direction="$direction" class="px-3 py-5 text-right font-medium">{{ __('People needed') }}</x-ui.table.sortable>
                        <x-ui.table.sortable field="teams" :sort="$sort" :direction="$direction" class="px-3 py-5 text-right font-medium">{{ __('Teams') }}</x-ui.table.sortable>
                        <x-ui.table.sortable field="status" :sort="$sort" :direction="$direction">{{ __('Status') }}</x-ui.table.sortable>
                        <th scope="col" class="px-3 py-5 text-right font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($deals as $deal)
                        <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="deal-{{ $deal->id }}">
                            <td class="px-3 py-2">
                                <input type="checkbox" wire:model.live="selected" value="{{ $deal->id }}" aria-label="{{ __('Select :name', ['name' => $deal->product?->getTranslation('name', 'en')]) }}" class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                            </td>
                            <td class="px-3 py-2 font-medium">{{ $deal->product?->getTranslation('name', 'en') }}</td>
                            <td class="px-3 py-2 text-ink-soft">{{ $deal->variant?->options_label ?: __('Default') }}</td>
                            <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">@money($deal->group_price_sen)</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $deal->target_size }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $deal->teams_count }}</td>
                            <td class="px-3 py-2"><x-ui.badge :variant="$deal->status === \App\Enums\GroupBuyStatus::Active ? 'success' : 'neutral'">{{ $deal->status->label() }}</x-ui.badge></td>
                            <td class="px-3 py-2">
                                <div class="flex min-h-11 items-center justify-end gap-1">
                                    @if ($deal->status === \App\Enums\GroupBuyStatus::Active)
                                        <button type="button" wire:click="end({{ $deal->id }})" class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-2 font-medium text-danger hover:bg-danger-tint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">{{ __('End') }}</button>
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

    <x-ui.table.footer :rows="$deals" />
</div>
