<div class="space-y-4">

    <x-ui.section-heading :title="__('Audit log')" as="h1" />

    {{-- Filters --}}
    <x-ui.card class="p-3">
        <div class="flex flex-wrap items-end gap-2">
            <div class="min-w-0 flex-1">
                <label for="filter-search" class="mb-1 block text-[13px] font-medium text-ink">{{ __('Search') }}</label>
                <input id="filter-search" type="search" wire:model.live.debounce.300ms="search"
                       placeholder="{{ __('Search who, event or description') }}"
                       class="block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 py-2 text-[13px] text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
            </div>
            <div>
                <label for="filter-subject" class="mb-1 block text-[13px] font-medium text-ink">{{ __('Subject') }}</label>
                <select id="filter-subject" wire:model.live="subjectType"
                        class="min-h-11 rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 py-2 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                    <option value="">{{ __('All subjects') }}</option>
                    @foreach ($subjectTypes as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filter-from" class="mb-1 block text-[13px] font-medium text-ink">{{ __('From') }}</label>
                <input id="filter-from" type="date" wire:model.live="dateFrom"
                       class="min-h-11 rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 py-2 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
            </div>
            <div>
                <label for="filter-to" class="mb-1 block text-[13px] font-medium text-ink">{{ __('To') }}</label>
                <input id="filter-to" type="date" wire:model.live="dateTo"
                       class="min-h-11 rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 py-2 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
            </div>
            @if ($search !== '' || $subjectType !== '' || $dateFrom !== '' || $dateTo !== '')
                <x-ui.button variant="plain" type="button" wire:click="clearFilters"
                        class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-3 text-[13px] font-medium text-ink-soft hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                    {{ __('Clear filters') }}
                </x-ui.button>
            @endif
        </div>
    </x-ui.card>

    {{-- Datagrid --}}
    @php
        // Description is dropped when it only repeats the event ("created" next to CREATED).
        $showDescription = $activities->contains(fn ($row) => trim((string) $row->description) !== ''
            && strcasecmp(trim((string) $row->description), (string) $row->event) !== 0);
    @endphp
    <x-ui.card class="overflow-x-auto">
        <x-ui.table-skeleton wire:loading wire:target="search, subjectType, dateFrom, dateTo, clearFilters, sortBy, perPage" />
        <div wire:loading.remove wire:target="search, subjectType, dateFrom, dateTo, clearFilters, sortBy, perPage">
        @if ($activities->isEmpty())
            <x-ui.empty-state :title="__('Nothing logged yet')" :message="__('Admin and system actions appear here as they happen.')" />
        @else
            <table class="w-full min-w-[820px] text-[13px]">
                <thead class="sticky top-0 z-10 bg-surface">
                    <tr class="border-b border-line text-left text-ink-soft">
                        <x-ui.table.sortable field="when" :sort="$sort" :direction="$direction" class="px-3 py-2.5 font-medium">{{ __('When') }}</x-ui.table.sortable>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Who') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Subject') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Event') }}</th>
                        @if ($showDescription)
                            <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Description') }}</th>
                        @endif
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Changes') }}</th>
                    </tr>
                </thead>
                @foreach ($activities as $activity)
                    @php
                        // activitylog v5 keeps model diffs in attribute_changes; older rows used properties.
                        $new = $activity->attribute_changes['attributes'] ?? $activity->properties['attributes'] ?? [];
                        $old = $activity->attribute_changes['old'] ?? $activity->properties['old'] ?? [];
                        $hasDiff = $new !== [] || $old !== [];
                        $format = fn ($value) => $value === null ? '—' : (is_string($value) || is_numeric($value) ? (string) $value : json_encode($value));
                    @endphp
                    {{-- One tbody per activity so the diff row can share Alpine state --}}
                    <tbody x-data="{ open: false }" wire:key="activity-{{ $activity->id }}">
                        <tr class="border-b border-line hover:bg-paper">
                            <td class="px-3 py-2 whitespace-nowrap text-[12px] text-ink-soft">{{ $activity->created_at->display()->format('d M Y H:i') }}</td>
                            <td class="px-3 py-2 font-medium">{{ $activity->causer?->name ?? __('System') }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                @if ($activity->subject_type !== null)
                                    @if (isset($subOrderNos[$activity->subject_id]) && $activity->subject_type === \App\Models\SubOrder::class)
                                        {{ __('Order') }} <span class="font-mono text-[12px] text-ink-soft">{{ $subOrderNos[$activity->subject_id] }}</span>
                                    @else
                                        {{ $activity->subject_type === \App\Models\SubOrder::class ? __('Order') : \Illuminate\Support\Str::headline(class_basename($activity->subject_type)) }} <span class="font-mono text-[12px] text-ink-soft">#{{ $activity->subject_id }}</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-2"><x-ui.badge variant="neutral">{{ $activity->event ?? '—' }}</x-ui.badge></td>
                            @if ($showDescription)
                                <td class="max-w-56 truncate px-3 py-2 text-ink-soft">{{ strcasecmp(trim((string) $activity->description), (string) $activity->event) === 0 ? '' : $activity->description }}</td>
                            @endif
                            <td class="px-3 py-2 text-right">
                                @if ($hasDiff)
                                    <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open ? 'true' : 'false'"
                                            class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-2 font-medium text-ink-soft hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                        <span x-text="open ? @js(__('Hide')) : @js(__('Diff'))">{{ __('Diff') }}</span>
                                    </button>
                                @else
                                    <span class="text-ink-faint">—</span>
                                @endif
                            </td>
                        </tr>
                        @if ($hasDiff)
                            <tr x-show="open" x-cloak class="border-b border-line bg-paper">
                                <td colspan="{{ $showDescription ? 6 : 5 }}" class="px-4 py-3">
                                    <ul class="space-y-1 font-mono text-[12px]">
                                        @foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $key)
                                            <li wire:key="diff-{{ $activity->id }}-{{ $key }}">
                                                <span class="font-medium text-ink">{{ $key }}:</span>
                                                <span class="text-ink-soft">{{ $format($old[$key] ?? null) }}</span>
                                                <span aria-hidden="true">→</span>
                                                <span class="sr-only">{{ __('changed to') }}</span>
                                                <span class="text-ink">{{ $format($new[$key] ?? null) }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                @endforeach
            </table>
        @endif
        </div>
    </x-ui.card>

    <x-ui.table.footer :rows="$activities" />
</div>
