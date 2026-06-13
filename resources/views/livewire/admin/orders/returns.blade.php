<div class="space-y-4">

    {{-- Header --}}
    <x-ui.section-heading :title="__('Returns')" as="h1" />

    {{-- Lifecycle tabs — the queue (disputed + escalated) is the work surface --}}
    <nav class="flex gap-1 overflow-x-auto border-b border-line" aria-label="{{ __('Return request status') }}">
        @foreach ($tabLabels as $key => $label)
            <button
                type="button"
                wire:click="$set('tab', '{{ $key }}')"
                wire:key="tab-{{ $key }}"
                aria-current="{{ $tab === $key ? 'page' : 'false' }}"
                class="inline-flex min-h-11 shrink-0 items-center gap-1.5 whitespace-nowrap border-b-2 px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $tab === $key ? 'border-ink font-semibold text-ink' : 'border-transparent font-medium text-ink-soft hover:text-ink' }}"
            >
                {{ $label }}
                @if ($counts[$key] > 0)
                    <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-emerald-tint px-1.5 py-0.5 text-[11px] font-semibold tabular-nums text-emerald">{{ $counts[$key] }}</span>
                @endif
            </button>
        @endforeach
    </nav>

    {{-- Resolution panel (docs/09 §D): refund buyer or side with the seller --}}
    @if ($resolving)
        @php($resolvingOnline = $resolving->subOrder->order->payment_method === \App\Enums\PaymentMethod::Ipay88)
        <x-ui.card class="space-y-3 p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">
                    {{ __('Resolve return on') }}
                    <span class="font-mono">{{ $resolving->subOrder->sub_order_no }}</span>
                </h2>
                <x-return-status-pill :status="$resolving->status" />
            </div>

            <dl class="space-y-1.5 text-[13px]">
                <div class="flex gap-2">
                    <dt class="shrink-0 text-ink-soft">{{ __('Reason') }}:</dt>
                    <dd class="text-ink">{{ $resolving->reason?->label ?? '—' }}</dd>
                </div>
                @if ($resolving->description)
                    <div class="flex gap-2">
                        <dt class="shrink-0 text-ink-soft">{{ __('Buyer says') }}:</dt>
                        <dd class="text-ink">{{ $resolving->description }}</dd>
                    </div>
                @endif
                @if ($resolving->seller_response)
                    <div class="flex gap-2">
                        <dt class="shrink-0 text-ink-soft">{{ __('Seller says') }}:</dt>
                        <dd class="text-ink">{{ $resolving->seller_response }}</dd>
                    </div>
                @endif
                <div class="flex gap-2">
                    <dt class="shrink-0 text-ink-soft">{{ __('Refund amount') }}:</dt>
                    <dd class="font-mono font-semibold tabular-nums">@money($resolving->subOrder->total_sen)</dd>
                </div>
            </dl>

            @if ($resolving->getMedia('photos')->isNotEmpty())
                <ul class="flex flex-wrap gap-2">
                    @foreach ($resolving->getMedia('photos') as $photo)
                        <li wire:key="resolve-photo-{{ $photo->id }}">
                            <a href="{{ $photo->getUrl() }}" target="_blank" rel="noopener"
                               class="block size-16 overflow-hidden rounded-[var(--radius-control)] border border-line bg-paper focus-visible:ring-2 focus-visible:ring-emerald">
                                <img src="{{ $photo->getUrl() }}" alt="{{ __('Return photo :n', ['n' => $loop->iteration]) }}" class="size-full object-cover" loading="lazy">
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="space-y-2 rounded-[var(--radius-card)] border border-line bg-paper p-3">
                @if ($resolvingOnline)
                    <p class="text-[13px] text-ink-soft">{{ __('Online payment — move the money in the iPay88 merchant portal first, then record the reference here.') }}</p>
                    <label for="refund-reference" class="block text-[13px] font-medium text-ink">{{ __('iPay88 portal reference') }}</label>
                    <input id="refund-reference" type="text" wire:model="refundReference"
                           placeholder="IP88-RFND-0000"
                           class="block min-h-11 w-full max-w-sm rounded-[var(--radius-control)] border bg-surface px-3 font-mono text-[13px] text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('refundReference') ? 'border-danger' : 'border-line-strong' }}">
                    @error('refundReference')
                        <p class="text-[13px] text-danger">{{ $message }}</p>
                    @enderror
                @else
                    <p class="text-[13px] text-ink-soft">{{ __('Cash on delivery — no gateway money to move. The refund is recorded as a ledger adjustment against the store.') }}</p>
                @endif
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="refundBuyer" wire:loading.attr="disabled"
                        wire:confirm="{{ __('Refund the buyer? This closes the return and adjusts the seller ledger — it cannot be undone.') }}"
                        class="inline-flex min-h-11 items-center justify-center rounded-[var(--radius-control)] bg-emerald px-4 text-sm font-semibold text-white hover:bg-emerald-deep active:bg-emerald-night disabled:cursor-not-allowed disabled:opacity-50 focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-2">
                    {{ __('Refund buyer') }}
                </button>
                @if ($resolving->subOrder->status === \App\Enums\SubOrderStatus::ReturnRequested)
                    <button type="button" wire:click="sideWithSeller({{ $resolving->id }})" wire:loading.attr="disabled"
                            wire:confirm="{{ __('Side with the seller? The return is rejected and the order goes back to its previous status.') }}"
                            class="inline-flex min-h-11 items-center justify-center rounded-[var(--radius-control)] border border-ink px-4 text-sm font-semibold text-ink hover:bg-paper disabled:cursor-not-allowed disabled:opacity-50 focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-2">
                        {{ __('Side with seller') }}
                    </button>
                @endif
                <button type="button" wire:click="closeResolve"
                        class="inline-flex min-h-11 items-center justify-center rounded-[var(--radius-control)] px-4 text-sm font-semibold text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">
                    {{ __('Close') }}
                </button>
            </div>
        </x-ui.card>
    @endif

    {{-- Table per design §6 — hairline rows, 13px, mono ids --}}
    <x-ui.card class="overflow-x-auto">
        @if ($requests->isEmpty())
            <x-ui.empty-state
                :title="$tab === 'queue' ? __('Nothing needs a decision') : __('No return requests here')"
                :message="$tab === 'queue'
                    ? __('Disputed and auto-escalated returns land here for a refund-or-reject call.')
                    : __('Switch tabs to see the rest of the returns lifecycle.')" />
        @else
            <table class="w-full min-w-[860px] text-[13px]">
                <thead class="sticky top-14 z-10 bg-surface">
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Sub-order') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Store') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Buyer') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Reason') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Status') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Escalated') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requests as $request)
                        <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="return-request-{{ $request->id }}">
                            <td class="px-3 py-2 whitespace-nowrap">
                                <a href="{{ route('admin.orders.show', $request->subOrder) }}" wire:navigate
                                   class="inline-flex min-h-11 items-center font-mono font-medium text-ink underline-offset-2 hover:text-emerald hover:underline focus-visible:ring-2 focus-visible:ring-emerald">
                                    {{ $request->subOrder->sub_order_no }}
                                </a>
                            </td>
                            <td class="px-3 py-2"><span class="line-clamp-1 max-w-44">{{ $request->subOrder->store->name }}</span></td>
                            <td class="px-3 py-2"><span class="line-clamp-1 max-w-44">{{ $request->subOrder->order->user->name }}</span></td>
                            <td class="px-3 py-2"><span class="line-clamp-1 max-w-52">{{ $request->reason?->label ?? '—' }}</span></td>
                            <td class="px-3 py-2"><x-return-status-pill :status="$request->status" /></td>
                            <td class="px-3 py-2 whitespace-nowrap text-ink-soft">
                                {{ $request->escalated_at?->format('j M Y, g:ia') ?? '—' }}
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex justify-end">
                                    @if (in_array($request->status, [\App\Enums\ReturnStatus::Accepted, \App\Enums\ReturnStatus::Disputed, \App\Enums\ReturnStatus::Escalated], true))
                                        <button type="button" wire:click="openResolve({{ $request->id }})"
                                                class="inline-flex min-h-11 items-center whitespace-nowrap rounded-[var(--radius-control)] border border-ink px-3 text-[13px] font-semibold text-ink hover:bg-paper focus-visible:ring-2 focus-visible:ring-emerald">
                                            {{ __('Resolve') }}
                                        </button>
                                    @else
                                        <span class="inline-flex min-h-11 items-center text-ink-faint">—</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    @if ($requests->hasPages())
        <div>{{ $requests->links() }}</div>
    @endif
</div>
