@php use App\Enums\SubscriptionStatus; @endphp

<div class="space-y-4">
    <x-ui.section-heading :title="__('Subscriptions')" :subtitle="__('Subscribe-and-save roster and replenishment health.')" as="h1" />

    <nav class="flex gap-1 overflow-x-auto border-b border-line" aria-label="{{ __('Subscription status') }}">
        @foreach (SubscriptionStatus::cases() as $statusCase)
            <x-ui.tab wire:click="$set('tab', '{{ $statusCase->value }}')" wire:key="tab-{{ $statusCase->value }}" :active="$tab === $statusCase->value" :count="$counts[$statusCase->value]">{{ $statusCase->label() }}</x-ui.tab>
        @endforeach
    </nav>

    <x-ui.table.search :placeholder="__('Search buyer or product')" />

    <x-ui.table.per-page />

    <x-ui.card class="overflow-x-auto">
        <x-ui.table-skeleton wire:loading wire:target="search, tab, sortBy, perPage, gotoPage, nextPage, previousPage" />
        <div wire:loading.remove wire:target="search, tab, sortBy, perPage, gotoPage, nextPage, previousPage">
        @if ($subscriptions->isEmpty())
            <x-ui.empty-state :title="$search !== '' ? __('No subscriptions match') : __('No subscriptions in this state')" :message="$search !== '' ? __('Try searching for something else.') : __('Buyers who subscribe and save appear here.')" />
        @else
            <table class="w-full min-w-[640px] text-[13px]">
                <thead class="sticky top-0 z-10 bg-surface">
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="px-3 py-6.5 font-medium">{{ __('Buyer') }}</th>
                        <th scope="col" class="px-3 py-6.5 font-medium">{{ __('Product') }}</th>
                        <x-ui.table.sortable field="every" :sort="$sort" :direction="$direction" class="px-3 py-6.5 text-right font-medium">{{ __('Every') }}</x-ui.table.sortable>
                        <x-ui.table.sortable field="next" :sort="$sort" :direction="$direction" class="px-3 py-6.5 text-right font-medium">{{ __('Next') }}</x-ui.table.sortable>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($subscriptions as $subscription)
                        <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="sub-{{ $subscription->id }}">
                            <td class="px-3 py-2 font-medium text-ink">{{ $subscription->user?->name }}</td>
                            <td class="px-3 py-2 text-ink-soft">{{ $subscription->variant?->product?->getTranslation('name', 'en') }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ __(':n days', ['n' => $subscription->interval_days]) }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap text-ink-soft">{{ $subscription->next_run_at?->display()->format('d M Y') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        </div>
    </x-ui.card>

    <x-ui.table.footer :rows="$subscriptions" />
</div>
