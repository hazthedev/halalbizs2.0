<div class="space-y-4">

    <x-ui.section-heading as="h1" :title="__('Shop vouchers')">
            <x-slot:actions>
                <x-ui.button icon="plus" :href="route('seller.vouchers.create')">
                    {{ __('Add voucher') }}
                </x-ui.button>
            </x-slot:actions>
    </x-ui.section-heading>

    <div class="rounded-[var(--radius-card)] border border-line bg-surface px-4 py-3 text-[13px] text-ink-soft shadow-soft">
        {{ __('Shop vouchers apply to your items only — buyers can stack one with a platform voucher at checkout.') }}
    </div>

    @if (session('status'))
        <p role="status" class="text-sm text-emerald">{{ session('status') }}</p>
    @endif

    {{-- List --}}
    <x-ui.card class="overflow-x-auto">
        @if ($vouchers->isEmpty())
            <x-ui.empty-state :title="__('No shop vouchers yet')" :message="__('Create one above — buyers pick it at checkout when your items are in their order.')" />
        @else
            <table class="w-full min-w-[860px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Code') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Type') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Value') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Min spend') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Window') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Used / quota') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Active') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vouchers as $voucher)
                        <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="voucher-{{ $voucher->id }}">
                            <td class="px-3 py-2 font-mono font-medium">{{ $voucher->code }}</td>
                            <td class="px-3 py-2">{{ $voucher->type->label() }}</td>
                            <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">
                                @switch($voucher->type)
                                    @case(\App\Enums\VoucherType::Fixed) @money($voucher->value_sen) @break
                                    @case(\App\Enums\VoucherType::Percent)
                                        {{ rtrim(rtrim((string) $voucher->percent, '0'), '.') }}%@if ($voucher->max_discount_sen !== null) <span class="text-ink-soft">({{ __('cap') }} @money($voucher->max_discount_sen))</span>@endif
                                        @break
                                    @default —
                                @endswitch
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">
                                @if ($voucher->min_spend_sen > 0) @money($voucher->min_spend_sen) @else — @endif
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-[12px] text-ink-soft">
                                {{ $voucher->starts_at->display()->format('d M Y') }} → {{ $voucher->ends_at->display()->format('d M Y') }}
                            </td>
                            <td class="px-3 py-2 text-right font-mono tabular-nums">
                                {{ $voucher->used_count }}<span class="text-ink-faint">/{{ $voucher->quota ?? '∞' }}</span>
                            </td>
                            <td class="px-3 py-2">
                                <button type="button" role="switch" aria-checked="{{ $voucher->is_active ? 'true' : 'false' }}"
                                        wire:click="toggleActive({{ $voucher->id }})"
                                        aria-label="{{ __('Toggle :code', ['code' => $voucher->code]) }}"
                                        class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-[var(--radius-control)] focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                    <span class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-150 {{ $voucher->is_active ? 'bg-emerald' : 'bg-line-strong' }}">
                                        <span class="inline-block size-4 rounded-full bg-white transition-transform duration-150 {{ $voucher->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                    </span>
                                </button>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-center justify-end gap-1">
                                    <x-ui.button variant="ghost" :href="route('seller.vouchers.edit', $voucher)">{{ __('Edit') }}</x-ui.button>
                                    <button type="button" wire:click="delete({{ $voucher->id }})"
                                            wire:confirm="{{ __('Delete voucher :code? This cannot be undone.', ['code' => $voucher->code]) }}"
                                            class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-2 font-medium text-danger hover:bg-danger-tint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Delete') }}</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>
</div>
