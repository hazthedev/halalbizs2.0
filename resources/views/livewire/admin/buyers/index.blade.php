<div class="space-y-4">

    <h1 class="font-display text-[22px] font-bold leading-tight">{{ __('Buyers') }}</h1>

    {{-- Filters --}}
    <div class="flex flex-wrap items-center gap-2">
        <input type="search"
               wire:model.live.debounce.300ms="search"
               placeholder="{{ __('Search by name or email') }}"
               aria-label="{{ __('Search buyers') }}"
               class="min-h-11 w-full max-w-xs rounded-lg border border-line-strong bg-surface px-3.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
        <select wire:model.live="status"
                aria-label="{{ __('Filter by status') }}"
                class="min-h-11 rounded-lg border border-line-strong bg-surface px-3 text-sm text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
            <option value="">{{ __('All statuses') }}</option>
            <option value="active">{{ __('Active') }}</option>
            <option value="suspended">{{ __('Suspended') }}</option>
        </select>
    </div>

    <x-ui.card class="overflow-x-auto">
        @if ($buyers->isEmpty())
            <div class="px-6 py-16 text-center">
                <h2 class="font-display text-xl font-semibold">{{ __('No buyers found') }}</h2>
                <p class="mt-1 text-sm text-ink-soft">{{ __('Every registered account that is not admin staff appears here.') }}</p>
            </div>
        @else
            <table class="w-full min-w-[760px] text-[13px]">
                <thead class="sticky top-14 z-10 bg-surface">
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Name') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Email') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Verified') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Orders') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Joined') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Status') }}</th>
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
                            <td class="px-3 py-2 whitespace-nowrap text-ink-soft">{{ $buyer->created_at->format('j M Y') }}</td>
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
    </x-ui.card>

    @if ($buyers->hasPages())
        <div>{{ $buyers->links() }}</div>
    @endif
</div>
