<div class="space-y-4">

    <x-ui.section-heading :title="__('Boosts')" :subtitle="__('Boost fees are platform income — charged up-front from seller available balances via the ledger. Cancelled boosts are not refunded, so they still count as revenue.')" as="h1" />

    {{-- Revenue summary --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            'today' => __('Today'),
            'week' => __('Last 7 days'),
            'month' => __('Last 30 days'),
            'all' => __('All time'),
        ] as $key => $label)
            <x-ui.stat :label="$label" :value="\App\Support\Money::format($revenue[$key])" wire:key="revenue-{{ $key }}" />
        @endforeach
    </div>

    {{-- All boosts --}}
    <x-ui.table.search :placeholder="__('Search store or product')" />

    <x-ui.table.per-page />

    <x-ui.card class="overflow-x-auto">
        <x-ui.table-skeleton wire:loading wire:target="search, sortBy, perPage, gotoPage, nextPage, previousPage" />
        <div wire:loading.remove wire:target="search, sortBy, perPage, gotoPage, nextPage, previousPage">
        @if ($boosts->isEmpty())
            <x-ui.empty-state :title="$search !== '' ? __('No boosts match') : __('No boosts yet')" :message="$search !== '' ? __('Try searching for something else.') : __('Seller boosts appear here the moment they are paid for.')" />
        @else
            <table class="w-full min-w-[820px] text-[13px]">
                <thead class="sticky top-0 z-10 bg-surface">
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="px-3 py-5 font-medium">{{ __('Store') }}</th>
                        <th scope="col" class="px-3 py-5 font-medium">{{ __('Product') }}</th>
                        <x-ui.table.sortable field="window" :sort="$sort" :direction="$direction">{{ __('Window') }}</x-ui.table.sortable>
                        <x-ui.table.sortable field="fee" :sort="$sort" :direction="$direction" class="text-right">{{ __('Fee') }}</x-ui.table.sortable>
                        <x-ui.table.sortable field="status" :sort="$sort" :direction="$direction">{{ __('Status') }}</x-ui.table.sortable>
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
                            <td class="px-3 py-2 font-medium text-ink">{{ $boost->store?->name ?? '—' }}</td>
                            <td class="max-w-72 px-3 py-2">
                                <span class="line-clamp-1 text-ink">
                                    {{ $boost->product?->getTranslation('name', 'en') ?? __('Deleted product') }}
                                </span>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-[12px] text-ink-soft">
                                {{ $boost->starts_at->display()->format('d M Y H:i') }} → {{ $boost->ends_at->display()->format('d M Y H:i') }}
                            </td>
                            <td class="px-3 py-2 text-right font-medium tabular-nums whitespace-nowrap">@money($boost->amount_sen)</td>
                            <td class="px-3 py-2"><x-ui.badge :variant="$pill">{{ $boost->status->label() }}</x-ui.badge></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        </div>
    </x-ui.card>

    <x-ui.table.footer :rows="$boosts" />
</div>
