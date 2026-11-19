<div class="space-y-4">

    <x-ui.section-heading :title="__('Platform vouchers')" as="h1">
        <x-slot:actions>
            <x-ui.button icon="plus" :href="route('admin.content.vouchers.create')">
                {{ __('Add voucher') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.section-heading>

    @if (session('status'))
        <div role="status" class="rounded-[var(--radius-control)] bg-emerald-tint px-4 py-3 text-sm text-emerald-deep">{{ session('status') }}</div>
    @endif

    <div class="rounded-[var(--radius-card)] border border-line bg-surface px-4 py-3 text-[13px] text-ink-soft shadow-soft">
        {{ __('Platform vouchers created here already work at checkout.') }}
    </div>

    <x-ui.table.search :placeholder="__('Search code')" />

    <x-ui.table.per-page />

    <x-ui.card class="overflow-x-auto">
        <x-ui.table-skeleton wire:loading wire:target="search, sortBy, perPage, gotoPage, nextPage, previousPage" />
        <div wire:loading.remove wire:target="search, sortBy, perPage, gotoPage, nextPage, previousPage">
        @if ($vouchers->isEmpty() && $search !== '')
            <x-ui.empty-state :title="__('No vouchers match')" :message="__('Try searching for something else.')" />
        @elseif ($vouchers->isEmpty())
            <x-ui.empty-state :title="__('No platform vouchers yet')" :message="__('Create one above — buyers apply codes at checkout.')" />
        @else
            <table class="w-full min-w-[860px] text-[13px]">
                <thead class="sticky top-0 z-10 bg-surface">
                    <tr class="border-b border-line text-left text-ink-soft">
                        <x-ui.table.sortable field="code" :sort="$sort" :direction="$direction">{{ __('Code') }}</x-ui.table.sortable>
                        <x-ui.table.sortable field="type" :sort="$sort" :direction="$direction">{{ __('Type') }}</x-ui.table.sortable>
                        <x-ui.table.sortable field="value" :sort="$sort" :direction="$direction" class="px-3 py-5 text-right font-medium">{{ __('Value') }}</x-ui.table.sortable>
                        <x-ui.table.sortable field="min_spend" :sort="$sort" :direction="$direction" class="px-3 py-5 text-right font-medium">{{ __('Min spend') }}</x-ui.table.sortable>
                        <x-ui.table.sortable field="window" default="desc" :sort="$sort" :direction="$direction">{{ __('Window') }}</x-ui.table.sortable>
                        <x-ui.table.sortable field="used" :sort="$sort" :direction="$direction" class="px-3 py-5 text-right font-medium">{{ __('Used') }}</x-ui.table.sortable>
                        <x-ui.table.sortable field="active" :sort="$sort" :direction="$direction">{{ __('Active') }}</x-ui.table.sortable>
                        <th scope="col" class="px-3 py-5 text-right font-medium">{{ __('Actions') }}</th>
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
                                    <x-ui.button variant="ghost" :href="route('admin.content.vouchers.edit', $voucher)">{{ __('Edit') }}</x-ui.button>
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
        </div>
    </x-ui.card>

    <x-ui.table.footer :rows="$vouchers" />
</div>
