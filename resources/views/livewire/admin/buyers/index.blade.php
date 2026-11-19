<div class="space-y-4">

    <x-ui.section-heading :title="__('Buyers')" as="h1" />

    {{-- Filters --}}
    <x-ui.card class="p-3">
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-6">
            <div class="min-w-0 lg:col-span-3">
                <label for="buyers-search" class="sr-only">{{ __('Search buyers') }}</label>
                <input id="buyers-search" type="search" wire:model.live.debounce.300ms="search"
                       placeholder="{{ __('Search by name or email') }}"
                       class="block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald placeholder:text-ink-faint">
            </div>
            <div class="lg:col-span-2">
                <label for="buyers-status" class="sr-only">{{ __('Status') }}</label>
                <select id="buyers-status" wire:model.live="status" class="block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="active">{{ __('Active') }}</option>
                    <option value="suspended">{{ __('Suspended') }}</option>
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

    <x-ui.card class="overflow-x-auto">
        <x-ui.table-skeleton wire:loading wire:target="search, status, resetFilters, sortBy, perPage" />
        <div wire:loading.remove wire:target="search, status, resetFilters, sortBy, perPage">
        @if ($buyers->isEmpty())
            <x-ui.empty-state :title="__('No buyers found')" :message="__('Every registered account that is not admin staff appears here.')" />
        @else
            <table class="w-full min-w-[760px] text-[13px]">
                <thead class="sticky top-0 z-10 bg-surface">
                    <tr class="border-b border-line text-left text-ink-soft">
                        <x-ui.table.sortable field="name" :sort="$sort" :direction="$direction" class="px-3 py-2.5 font-medium">{{ __('Name') }}</x-ui.table.sortable>
                        <x-ui.table.sortable field="email" :sort="$sort" :direction="$direction" class="px-3 py-2.5 font-medium">{{ __('Email') }}</x-ui.table.sortable>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Verified') }}</th>
                        <x-ui.table.sortable field="orders" :sort="$sort" :direction="$direction" class="px-3 py-2.5 text-right font-medium">{{ __('Orders') }}</x-ui.table.sortable>
                        <x-ui.table.sortable field="joined" :sort="$sort" :direction="$direction" class="px-3 py-2.5 font-medium">{{ __('Joined') }}</x-ui.table.sortable>
                        <x-ui.table.sortable field="status" :sort="$sort" :direction="$direction" class="px-3 py-2.5 font-medium">{{ __('Status') }}</x-ui.table.sortable>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($buyers as $buyer)
                        <tr wire:key="buyer-{{ $buyer->id }}" class="border-b border-line last:border-b-0 hover:bg-paper">
                            <td class="px-3 py-2">
                                <a href="{{ route('admin.buyers.show', $buyer) }}" wire:navigate
                                   class="inline-flex min-h-11 items-center font-medium text-ink underline-offset-2 hover:text-emerald hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                    <span class="line-clamp-1 max-w-52">{{ $buyer->name }}</span>
                                </a>
                            </td>
                            <td class="px-3 py-2 text-ink-soft">{{ $buyer->email }}</td>
                            <td class="px-3 py-2">
                                @if ($buyer->email_verified_at !== null)
                                    <x-ui.badge variant="sale">{{ __('Verified') }}</x-ui.badge>
                                @else
                                    <x-ui.badge variant="neutral">{{ __('Unverified') }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $buyer->orders_count }}</td>
                            <td class="px-3 py-2 whitespace-nowrap text-ink-soft">{{ $buyer->created_at->display()->format('j M Y') }}</td>
                            <td class="px-3 py-2">
                                @if ($buyer->isSuspended())
                                    <x-ui.badge variant="danger">{{ __('Suspended') }}</x-ui.badge>
                                @else
                                    <x-ui.badge variant="sale">{{ __('Active') }}</x-ui.badge>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        </div>
    </x-ui.card>

    <x-ui.table.footer :rows="$buyers" />
</div>
