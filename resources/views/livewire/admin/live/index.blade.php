<div class="space-y-4">
    <x-ui.section-heading :title="__('Live shopping')" :subtitle="__('Monitor and moderate live-commerce sessions (M2.4).')" as="h1" />

    {{-- Live now --}}
    <div class="overflow-hidden rounded-[var(--radius-card)] border border-line bg-surface shadow-soft">
        <h2 class="border-b border-line px-4 py-3 font-semibold">{{ __('Live now') }} <span class="text-ink-faint">({{ $liveNow->count() }})</span></h2>
        @if ($liveNow->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-ink-soft">{{ __('No streams live right now.') }}</p>
        @else
            <ul class="divide-y divide-line">
                @foreach ($liveNow as $session)
                    <li wire:key="live-{{ $session->id }}" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-ink">{{ $session->title }}</p>
                            <p class="text-xs text-ink-faint">{{ $session->store?->name }} · {{ trans_choice(':count item|:count items', $session->products_count, ['count' => $session->products_count]) }} · {{ $session->started_at?->diffForHumans() }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <a href="{{ route('live.room', $session->slug) }}" target="_blank" class="text-[13px] font-semibold text-emerald hover:text-emerald-deep">{{ __('Open room') }}</a>
                            <button type="button" wire:click="forceEnd({{ $session->id }})" wire:confirm="{{ __('Force-end this live session?') }}" class="text-[13px] font-semibold text-ink-soft hover:text-danger">{{ __('Force end') }}</button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Scheduled + ended --}}
    <div class="overflow-hidden rounded-[var(--radius-card)] border border-line bg-surface shadow-soft">
        <table class="w-full text-sm">
            <thead class="border-b border-line text-left text-[11px] uppercase tracking-[0.06em] text-ink-faint">
                <tr>
                    <th class="px-4 py-3 font-semibold">{{ __('Session') }}</th>
                    <th class="px-4 py-3 font-semibold">{{ __('Store') }}</th>
                    <th class="px-4 py-3 font-semibold">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($sessions as $session)
                    <tr wire:key="sess-{{ $session->id }}">
                        <td class="px-4 py-3 font-medium text-ink">{{ $session->title }}</td>
                        <td class="px-4 py-3 text-ink-soft">{{ $session->store?->name }}</td>
                        <td class="px-4 py-3"><x-ui.badge variant="neutral">{{ $session->status->label() }}</x-ui.badge></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-10 text-center text-ink-soft">{{ __('No scheduled or past sessions.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $sessions->links() }}
</div>
