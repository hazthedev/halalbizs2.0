<div class="space-y-4">
    <x-ui.section-heading :title="__('Affiliates')" :subtitle="__('Creator roster and the withdrawal approval queue (M2.5).')" as="h1" />

    {{-- Withdrawal queue --}}
    <div class="overflow-hidden rounded-[var(--radius-card)] border border-line bg-surface shadow-soft">
        <h2 class="border-b border-line px-4 py-3 font-semibold">{{ __('Pending withdrawals') }} <span class="text-ink-faint">({{ $pending->count() }})</span></h2>
        @if ($pending->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-ink-soft">{{ __('No withdrawals awaiting action.') }}</p>
        @else
            <ul class="divide-y divide-line">
                @foreach ($pending as $payout)
                    <li wire:key="payout-{{ $payout->id }}" class="px-4 py-3">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-ink">{{ $payout->affiliate?->user?->name }} · <span class="font-bold tnum">@money($payout->amount_sen)</span></p>
                                <p class="text-xs text-ink-faint">{{ $payout->requested_at?->format('d M Y') }} · {{ data_get($payout->bank_snapshot, 'details') }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" wire:click="openPay({{ $payout->id }})" class="inline-flex min-h-9 items-center rounded-[var(--radius-control)] bg-emerald px-3 text-[13px] font-semibold text-white hover:bg-emerald-deep">{{ __('Mark paid') }}</button>
                                <button type="button" wire:click="openReject({{ $payout->id }})" class="text-[13px] font-semibold text-ink-soft hover:text-danger">{{ __('Reject') }}</button>
                            </div>
                        </div>

                        @if ($payingId === $payout->id)
                            <form wire:submit="markPaid" class="mt-2 flex flex-wrap items-end gap-2">
                                <input type="text" wire:model="paidReference" placeholder="{{ __('Bank transfer reference') }}" class="min-h-11 w-64 rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-sm">
                                <button type="submit" class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] bg-emerald px-4 text-sm font-semibold text-white">{{ __('Confirm paid') }}</button>
                                @error('paidReference') <p class="w-full text-[13px] text-danger">{{ $message }}</p> @enderror
                            </form>
                        @endif

                        @if ($rejectingId === $payout->id)
                            <form wire:submit="reject" class="mt-2 flex flex-wrap items-end gap-2">
                                <input type="text" wire:model="rejectReason" placeholder="{{ __('Reason for rejection') }}" class="min-h-11 w-64 rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 text-sm">
                                <button type="submit" class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] border border-danger px-4 text-sm font-semibold text-danger hover:bg-danger hover:text-white">{{ __('Confirm reject') }}</button>
                                @error('rejectReason') <p class="w-full text-[13px] text-danger">{{ $message }}</p> @enderror
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Roster --}}
    <div class="overflow-hidden rounded-[var(--radius-card)] border border-line bg-surface shadow-soft">
        <table class="w-full text-sm">
            <thead class="border-b border-line text-left text-[11px] uppercase tracking-[0.06em] text-ink-faint">
                <tr>
                    <th class="px-4 py-3 font-semibold">{{ __('Creator') }}</th>
                    <th class="px-4 py-3 font-semibold">{{ __('Code') }}</th>
                    <th class="px-4 py-3 text-right font-semibold">{{ __('Clicks') }}</th>
                    <th class="px-4 py-3 text-right font-semibold">{{ __('Referrals') }}</th>
                    <th class="px-4 py-3 text-right font-semibold">{{ __('Commission') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($affiliates as $affiliate)
                    <tr wire:key="aff-{{ $affiliate->id }}">
                        <td class="px-4 py-3 font-medium text-ink">{{ $affiliate->user?->name }}</td>
                        <td class="px-4 py-3 font-mono text-[13px]">{{ $affiliate->code }}</td>
                        <td class="px-4 py-3 text-right tnum">{{ number_format($affiliate->clicks) }}</td>
                        <td class="px-4 py-3 text-right tnum">{{ number_format($affiliate->referrals_count) }}</td>
                        <td class="px-4 py-3 text-right font-bold tnum">@money((int) $affiliate->commission_sen_sum)</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-ink-soft">{{ __('No affiliates yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $affiliates->links() }}
</div>
