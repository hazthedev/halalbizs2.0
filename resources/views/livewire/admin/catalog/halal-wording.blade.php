<div class="space-y-4">
    <x-ui.section-heading :title="__('Halal wording, no approved certificate')" as="h1" />
    <p class="text-[13px] text-ink-soft">{{ __('Listings that say "halal" in the name or description but have no approved, in-force certificate. For review only: nothing is blocked.') }}</p>

    <x-ui.table.search :placeholder="__('Search product or store')" />
    <x-ui.table.per-page />

    <x-ui.card class="overflow-x-auto">
        @if ($products->isEmpty())
            <x-ui.empty-state :title="__('Nothing flagged')" :message="__('Every listing that says halal has an approved certificate.')" />
        @else
            <table class="w-full min-w-[640px] text-[13px]">
                <thead class="sticky top-0 z-10 bg-surface">
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Product') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Store') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Status') }}</th>
                        <x-ui.table.sortable field="updated" default="desc" :sort="$sort" :direction="$direction">{{ __('Updated') }}</x-ui.table.sortable>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="halal-{{ $product->id }}">
                            <td class="px-3 py-2">@include('livewire.admin.catalog.partials.moderation-product-cell', ['product' => $product])</td>
                            <td class="px-3 py-2 text-ink-soft">{{ $product->store?->name }}</td>
                            <td class="px-3 py-2"><x-ui.badge variant="warn">{{ $product->status->label() }}</x-ui.badge></td>
                            <td class="px-3 py-2 whitespace-nowrap text-[12px] text-ink-soft">{{ $product->updated_at->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    @if ($products->total() > 0)
        <x-ui.table.footer :rows="$products" />
    @endif
</div>
