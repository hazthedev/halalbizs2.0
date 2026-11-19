<div class="space-y-4">
    <x-ui.section-heading :title="__('Live shopping')" :subtitle="__('Monitor and moderate live-commerce sessions.')" as="h1" />

    {{-- Live now --}}
    <x-ui.card class="overflow-hidden">
        <h2 class="border-b border-line px-4 py-3 font-medium">{{ __('Live now') }} @if ($liveNow->isNotEmpty())<span class="text-ink-faint">({{ $liveNow->count() }})</span>@endif</h2>
        @if ($liveNow->isEmpty())
            <x-ui.empty-state :title="__('No streams live right now')" :message="__('Sessions that are streaming show up here, with a control to end them.')" />
        @else
            <ul class="divide-y divide-line">
                @foreach ($liveNow as $session)
                    <li wire:key="live-{{ $session->id }}" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-ink">{{ $session->title }}</p>
                            <p class="text-xs text-ink-faint">{{ $session->store?->name }} · {{ trans_choice(':count item|:count items', $session->products_count, ['count' => $session->products_count]) }} · {{ $session->started_at?->diffForHumans() }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <a href="{{ route('live.room', $session->slug) }}" target="_blank" class="text-[13px] font-medium text-emerald hover:text-emerald-deep">{{ __('Open room') }}</a>
                            <x-ui.button variant="plain" type="button" wire:click="forceEnd({{ $session->id }})" wire:confirm="{{ __('Force-end this live session?') }}" class="text-[13px] font-medium text-ink-soft hover:text-danger">{{ __('Force end') }}</x-ui.button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    {{-- Scheduled + ended --}}
    <x-ui.table.search :placeholder="__('Search session or store')" />

    <x-ui.table.per-page />

    <x-ui.card class="overflow-x-auto">
        <x-ui.table-skeleton wire:loading wire:target="search, sortBy, perPage, gotoPage, nextPage, previousPage" />
        <div wire:loading.remove wire:target="search, sortBy, perPage, gotoPage, nextPage, previousPage">
        @if ($sessions->isEmpty())
            <x-ui.empty-state :title="$search !== '' ? __('No sessions match') : __('No scheduled or past sessions')" :message="$search !== '' ? __('Try searching for something else.') : __('Scheduled and finished live sessions are listed here.')" />
        @else
            <table class="w-full min-w-[560px] text-[13px]">
                <thead class="sticky top-0 z-10 bg-surface">
                    <tr class="border-b border-line text-left text-ink-soft">
                        <x-ui.table.sortable field="session" :sort="$sort" :direction="$direction">{{ __('Session') }}</x-ui.table.sortable>
                        <th scope="col" class="px-3 py-6.5 font-medium">{{ __('Store') }}</th>
                        <x-ui.table.sortable field="status" :sort="$sort" :direction="$direction">{{ __('Status') }}</x-ui.table.sortable>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sessions as $session)
                        <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="sess-{{ $session->id }}">
                            <td class="px-3 py-2 font-medium text-ink">{{ $session->title }}</td>
                            <td class="px-3 py-2 text-ink-soft">{{ $session->store?->name }}</td>
                            <td class="px-3 py-2"><x-ui.badge :variant="$session->status === \App\Enums\LiveSessionStatus::Scheduled ? 'info' : 'neutral'">{{ $session->status->label() }}</x-ui.badge></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        </div>
    </x-ui.card>

    <x-ui.table.footer :rows="$sessions" />
</div>
