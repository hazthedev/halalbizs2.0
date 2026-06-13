<div class="space-y-4">

    <x-ui.section-heading :title="__('Stores')" as="h1" />

    {{-- Filters --}}
    <div class="flex flex-wrap items-center gap-2">
        <input type="search"
               wire:model.live.debounce.300ms="search"
               placeholder="{{ __('Search by store name or owner email') }}"
               aria-label="{{ __('Search stores') }}"
               class="min-h-11 w-full max-w-xs rounded-[var(--radius-control)] border border-line-strong bg-surface px-3.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
        <select wire:model.live="status"
                aria-label="{{ __('Filter by status') }}"
                class="min-h-11 rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-sm text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
            <option value="">{{ __('All statuses') }}</option>
            @foreach ($statusOptions as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </select>
    </div>

    <x-ui.card class="overflow-x-auto">
        @if ($stores->isEmpty())
            <x-ui.empty-state :title="__('No stores found')" :message="__('Approved, suspended and rejected stores appear here. Pending applications live in the queue.')" />
        @else
            <table class="w-full min-w-[860px] text-[13px]">
                <thead class="sticky top-14 z-10 bg-surface">
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Store') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Owner') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('State') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Status') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Rating') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Live products') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Commission override') }}</th>
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
                                        <img src="{{ $logoUrl }}" alt="" class="size-8 shrink-0 rounded-lg border border-line bg-paper object-cover">
                                    @else
                                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg border border-line bg-paper text-[11px] font-semibold text-ink-faint" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($store->name, 0, 1)) }}</span>
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
    </x-ui.card>

    @if ($stores->hasPages())
        <div>{{ $stores->links() }}</div>
    @endif
</div>
