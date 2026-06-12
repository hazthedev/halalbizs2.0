{{-- One row in the checkout voucher picker (docs/09 §B).
     Expects: $option (voucher, storeName, label, minSpendMet, shortBySen,
     discountSen, freeShipping), $applied (bool), $best (bool). --}}
<button type="button"
        @if ($option->minSpendMet && ! $applied) wire:click="selectVoucher({{ $option->voucher->id }})" @endif
        @if (! $option->minSpendMet) disabled @endif
        class="flex w-full items-center justify-between gap-3 rounded-lg border p-3 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald
            {{ $applied ? 'border-emerald bg-emerald-tint' : ($option->minSpendMet ? 'cursor-pointer border-line-strong hover:border-ink' : 'border-line opacity-60') }}">
    <span class="min-w-0">
        <span class="flex flex-wrap items-center gap-1.5">
            <span class="font-mono text-sm font-semibold">{{ $option->voucher->code }}</span>
            @if ($option->storeName !== null)
                <span class="truncate text-[12px] text-ink-soft">· {{ $option->storeName }}</span>
            @endif
            @if ($best)
                <x-ui.badge variant="sale">{{ __('Best value') }}</x-ui.badge>
            @endif
            @if ($applied)
                <x-ui.badge variant="verified">{{ __('Applied') }}</x-ui.badge>
            @endif
        </span>
        <span class="mt-0.5 block text-[13px] text-ink-soft">{{ $option->label }}</span>
        @if (! $option->minSpendMet)
            <span class="mt-0.5 block text-[13px] text-warn">
                {{ __('Add :amount more to use this voucher.', ['amount' => \App\Support\Money::format($option->shortBySen)]) }}
            </span>
        @elseif ($option->voucher->min_spend_sen > 0)
            <span class="mt-0.5 block text-[13px] text-ink-faint">
                {{ __('Min spend :min — met', ['min' => \App\Support\Money::format($option->voucher->min_spend_sen)]) }}
            </span>
        @endif
    </span>
    <span class="shrink-0 text-sm font-bold tnum {{ $option->minSpendMet ? 'text-emerald' : 'text-ink-faint' }}">
        @if ($option->freeShipping)
            {{ __('Free shipping') }}
        @elseif ($option->minSpendMet)
            -@money($option->discountSen)
        @endif
    </span>
</button>
