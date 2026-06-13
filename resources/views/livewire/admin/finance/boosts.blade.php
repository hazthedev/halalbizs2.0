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
            <x-ui.card class="p-4" wire:key="revenue-{{ $key }}">
                <p class="text-[13px] text-ink-soft">{{ $label }}</p>
                <p class="mt-1 font-display text-xl font-bold tabular-nums">@money($revenue[$key])</p>
            </x-ui.card>
        @endforeach
    </div>

    {{-- All boosts --}}
    <x-ui.card class="overflow-x-auto">
        @if ($boosts->isEmpty())
            <x-ui.empty-state :title="__('No boosts yet')" :message="__('Seller boosts appear here the moment they are paid for.')" />
        @else
            <table class="w-full min-w-[820px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Store') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Product') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Window') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Fee') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Status') }}</th>
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
                                {{ $boost->starts_at->format('d M Y H:i') }} → {{ $boost->ends_at->format('d M Y H:i') }}
                            </td>
                            <td class="px-3 py-2 text-right font-semibold tabular-nums whitespace-nowrap">@money($boost->amount_sen)</td>
                            <td class="px-3 py-2"><x-ui.badge :variant="$pill">{{ $boost->status->label() }}</x-ui.badge></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    @if ($boosts->hasPages())
        <div>{{ $boosts->links() }}</div>
    @endif
</div>
