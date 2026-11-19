<div class="space-y-4">

    <x-ui.section-heading :title="__('Stores')" as="h1" />

    {{-- Filters --}}
    <x-ui.card class="p-3">
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-6">
            <div class="min-w-0 lg:col-span-3">
                <label for="stores-search" class="sr-only">{{ __('Search stores') }}</label>
                <input id="stores-search" type="search" wire:model.live.debounce.300ms="search"
                       placeholder="{{ __('Search by store name or owner email') }}"
                       class="block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald placeholder:text-ink-faint">
            </div>
            <div class="lg:col-span-2">
                <label for="stores-status" class="sr-only">{{ __('Status') }}</label>
                <select id="stores-status" wire:model.live="status" class="block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach ($statusOptions as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        @if ($search !== '' || $status !== '')
            <button type="button" wire:click="resetFilters"
                    class="mt-2 inline-flex min-h-11 items-center text-[13px] font-medium text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">
                {{ __('Clear filters') }}
            </button>
        @endif
    </x-ui.card>

    <x-ui.table.per-page />

    <x-ui.card class="overflow-x-auto">
        <x-ui.table-skeleton wire:loading wire:target="search, status, resetFilters, sortBy, perPage" />
        <div wire:loading.remove wire:target="search, status, resetFilters, sortBy, perPage">
        @if ($stores->isEmpty())
            <x-ui.empty-state :title="__('No stores found')" :message="__('Approved, suspended and rejected stores appear here. Pending applications live in the queue.')" />
        @else
            <table class="w-full min-w-[860px] text-[13px]">
                <thead class="sticky top-0 z-10 bg-surface">
                    <tr class="border-b border-line text-left text-ink-soft">
                        <x-ui.table.sortable field="store" :sort="$sort" :direction="$direction" class="px-3 py-6.5 font-medium">{{ __('Store') }}</x-ui.table.sortable>
                        <th scope="col" class="px-3 py-6.5 font-medium">{{ __('Owner') }}</th>
                        <x-ui.table.sortable field="state" :sort="$sort" :direction="$direction" class="px-3 py-6.5 font-medium">{{ __('State') }}</x-ui.table.sortable>
                        <x-ui.table.sortable field="status" :sort="$sort" :direction="$direction" class="px-3 py-6.5 font-medium">{{ __('Status') }}</x-ui.table.sortable>
                        <x-ui.table.sortable field="rating" :sort="$sort" :direction="$direction" class="px-3 py-6.5 text-right font-medium">{{ __('Rating') }}</x-ui.table.sortable>
                        <x-ui.table.sortable field="products" :sort="$sort" :direction="$direction" class="px-3 py-6.5 text-right font-medium">{{ __('Live products') }}</x-ui.table.sortable>
                        <x-ui.table.sortable field="commission" :sort="$sort" :direction="$direction" class="px-3 py-6.5 text-right font-medium">{{ __('Commission override') }}</x-ui.table.sortable>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stores as $store)
                        @php $logoUrl = $store->getFirstMediaUrl('logo'); @endphp
                        <tr wire:key="store-{{ $store->id }}" class="border-b border-line last:border-b-0 hover:bg-paper">
                            <td class="px-3 py-2">
                                <a href="{{ route('admin.sellers.stores.show', $store) }}" wire:navigate
                                   class="inline-flex min-h-11 items-center gap-2.5 font-medium text-ink underline-offset-2 hover:text-emerald hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                    @if ($logoUrl !== '')
                                        <img src="{{ $logoUrl }}" alt="" class="size-8 shrink-0 rounded-[var(--radius-control)] border border-line bg-paper object-cover">
                                    @else
                                        <span class="flex size-8 shrink-0 items-center justify-center rounded-[var(--radius-control)] border border-line bg-paper text-[11px] font-medium text-ink-faint" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($store->name, 0, 1)) }}</span>
                                    @endif
                                    <span class="line-clamp-1 max-w-52">{{ $store->name }}</span>
                                </a>
                            </td>
                            <td class="px-3 py-2 text-ink-soft">{{ $store->user?->email ?? '—' }}</td>
                            <td class="px-3 py-2 text-ink-soft">{{ $store->state }}</td>
                            <td class="px-3 py-2"><x-store-status-pill :status="$store->status" /></td>
                            <td class="px-3 py-2 text-right tabular-nums text-ink-soft">{{ $store->rating_count > 0 ? '★ '.$store->rating_avg : '—' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $store->live_products_count }}</td>
                            <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $store->commission_rate !== null ? $store->commission_rate.'%' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        </div>
    </x-ui.card>

    <x-ui.table.footer :rows="$stores" />
</div>
