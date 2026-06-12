<div class="space-y-4">

    <h1 class="font-display text-2xl font-bold">{{ __('Audit log') }}</h1>

    {{-- Filters --}}
    <x-ui.card class="p-3">
        <div class="flex flex-wrap items-end gap-2">
            <div>
                <label for="filter-subject" class="mb-1 block text-[13px] font-medium text-ink">{{ __('Subject') }}</label>
                <select id="filter-subject" wire:model.live="subjectType"
                        class="min-h-11 rounded-lg border border-line-strong bg-surface px-3 py-2 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                    <option value="">{{ __('All subjects') }}</option>
                    @foreach ($subjectTypes as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filter-from" class="mb-1 block text-[13px] font-medium text-ink">{{ __('From') }}</label>
                <input id="filter-from" type="date" wire:model.live="dateFrom"
                       class="min-h-11 rounded-lg border border-line-strong bg-surface px-3 py-2 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
            </div>
            <div>
                <label for="filter-to" class="mb-1 block text-[13px] font-medium text-ink">{{ __('To') }}</label>
                <input id="filter-to" type="date" wire:model.live="dateTo"
                       class="min-h-11 rounded-lg border border-line-strong bg-surface px-3 py-2 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
            </div>
            @if ($subjectType !== '' || $dateFrom !== '' || $dateTo !== '')
                <button type="button" wire:click="clearFilters"
                        class="inline-flex min-h-11 items-center rounded-lg px-3 text-[13px] font-medium text-ink-soft hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                    {{ __('Clear filters') }}
                </button>
            @endif
        </div>
    </x-ui.card>

    {{-- Datagrid --}}
    <x-ui.card class="overflow-x-auto">
        @if ($activities->isEmpty())
            <div class="px-6 py-16 text-center">
                <h2 class="font-display text-xl font-semibold">{{ __('Nothing logged yet') }}</h2>
                <p class="mt-1 text-sm text-ink-soft">{{ __('Admin and system actions appear here as they happen.') }}</p>
            </div>
        @else
            <table class="w-full min-w-[820px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('When') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Who') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Subject') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Event') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Description') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Changes') }}</th>
                    </tr>
                </thead>
                @foreach ($activities as $activity)
                    @php
                        $new = $activity->properties['attributes'] ?? [];
                        $old = $activity->properties['old'] ?? [];
                        $hasDiff = $new !== [] || $old !== [];
                        $format = fn ($value) => $value === null ? '—' : (is_scalar($value) ? (string) $value : json_encode($value));
                    @endphp
                    {{-- One tbody per activity so the diff row can share Alpine state --}}
                    <tbody x-data="{ open: false }" wire:key="activity-{{ $activity->id }}">
                        <tr class="border-b border-line hover:bg-paper">
                            <td class="px-3 py-2 whitespace-nowrap text-[12px] text-ink-soft">{{ $activity->created_at->format('d M Y H:i') }}</td>
                            <td class="px-3 py-2 font-medium">{{ $activity->causer?->name ?? __('System') }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                @if ($activity->subject_type !== null)
                                    {{ class_basename($activity->subject_type) }} <span class="font-mono text-[12px] text-ink-soft">#{{ $activity->subject_id }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-2"><x-ui.badge variant="neutral">{{ $activity->event ?? '—' }}</x-ui.badge></td>
                            <td class="max-w-56 truncate px-3 py-2 text-ink-soft">{{ $activity->description }}</td>
                            <td class="px-3 py-2 text-right">
                                @if ($hasDiff)
                                    <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open ? 'true' : 'false'"
                                            class="inline-flex min-h-11 items-center rounded-lg px-2 font-medium text-ink-soft hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                        <span x-text="open ? @js(__('Hide')) : @js(__('Diff'))">{{ __('Diff') }}</span>
                                    </button>
                                @else
                                    <span class="text-ink-faint">—</span>
                                @endif
                            </td>
                        </tr>
                        @if ($hasDiff)
                            <tr x-show="open" x-cloak class="border-b border-line bg-paper">
                                <td colspan="6" class="px-4 py-3">
                                    <ul class="space-y-1 font-mono text-[12px]">
                                        @foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $key)
                                            <li wire:key="diff-{{ $activity->id }}-{{ $key }}">
                                                <span class="font-semibold text-ink">{{ $key }}:</span>
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
    </x-ui.card>

    @if ($activities->hasPages())
        <div>{{ $activities->links() }}</div>
    @endif
</div>
