@php use App\Enums\SubscriptionStatus; @endphp

<div class="space-y-4">
    <x-ui.section-heading :title="__('Subscriptions')" :subtitle="__('Subscribe-and-save roster and replenishment health (M2.8).')" as="h1" />

    <nav class="flex gap-1 overflow-x-auto border-b border-line" aria-label="{{ __('Subscription status') }}">
        @foreach (SubscriptionStatus::cases() as $statusCase)
            <button type="button" wire:click="$set('tab', '{{ $statusCase->value }}')" wire:key="tab-{{ $statusCase->value }}"
                    class="inline-flex min-h-11 shrink-0 items-center gap-1.5 whitespace-nowrap border-b-2 px-3 text-sm {{ $tab === $statusCase->value ? 'border-ink font-semibold text-ink' : 'border-transparent font-medium text-ink-soft hover:text-ink' }}">
                {{ $statusCase->label() }}
                @if ($counts[$statusCase->value] > 0)
                    <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-emerald-tint px-1.5 py-0.5 text-[11px] font-semibold tnum text-emerald">{{ $counts[$statusCase->value] }}</span>
                @endif
            </button>
        @endforeach
    </nav>

    <div class="overflow-hidden rounded-[var(--radius-card)] border border-line bg-surface shadow-soft">
        <table class="w-full text-sm">
            <thead class="border-b border-line text-left text-[11px] uppercase tracking-[0.06em] text-ink-faint">
                <tr>
                    <th class="px-4 py-3 font-semibold">{{ __('Buyer') }}</th>
                    <th class="px-4 py-3 font-semibold">{{ __('Product') }}</th>
                    <th class="px-4 py-3 text-right font-semibold">{{ __('Every') }}</th>
                    <th class="px-4 py-3 text-right font-semibold">{{ __('Next') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($subscriptions as $subscription)
                    <tr wire:key="sub-{{ $subscription->id }}">
                        <td class="px-4 py-3 font-medium text-ink">{{ $subscription->user?->name }}</td>
                        <td class="px-4 py-3 text-ink-soft">{{ $subscription->variant?->product?->getTranslation('name', 'en') }}</td>
                        <td class="px-4 py-3 text-right tnum">{{ __(':n days', ['n' => $subscription->interval_days]) }}</td>
                        <td class="px-4 py-3 text-right text-ink-soft">{{ $subscription->next_run_at?->format('d M Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-ink-soft">{{ __('No subscriptions in this state.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $subscriptions->links() }}
</div>
