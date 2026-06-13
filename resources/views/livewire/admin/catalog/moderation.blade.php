<div class="space-y-4">

    {{-- Header --}}
    <x-ui.section-heading :title="__('Moderation')" as="h1" />

    @unless ($requireApproval)
        <div class="rounded-[var(--radius-card)] border border-line bg-surface px-4 py-3 text-[13px] text-ink-soft shadow-soft">
            {{ __('Product approval is off — new products go live immediately. Turn it on under System → Settings to route them through this queue.') }}
        </div>
    @endunless

    {{-- Bulk actions --}}
    @if (count($selected) > 0)
        <div class="flex flex-wrap items-center gap-3 rounded-[var(--radius-card)] border border-line bg-surface px-4 py-2 shadow-soft">
            <span class="text-[13px] font-medium text-ink">{{ trans_choice('{1}:count selected|[2,*]:count selected', count($selected), ['count' => count($selected)]) }}</span>
            <x-ui.button wire:click="bulkApprove" wire:loading.attr="disabled">{{ __('Approve selected') }}</x-ui.button>
            <button type="button" wire:click="startBulkReject"
                    class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] border border-ink px-3 text-[13px] font-semibold text-ink hover:bg-paper focus-visible:ring-2 focus-visible:ring-emerald">
                {{ __('Reject selected') }}
            </button>
            <button type="button" wire:click="bulkBan"
                    wire:confirm="{{ __('Ban the selected products? They disappear from the storefront and the sellers are notified.') }}"
                    class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] border border-danger px-3 text-[13px] font-semibold text-danger hover:bg-danger-tint focus-visible:ring-2 focus-visible:ring-emerald">
                {{ __('Ban selected') }}
            </button>
        </div>
    @endif

    {{-- Pending queue --}}
    <x-ui.card class="overflow-x-auto">
        @if ($pending->isEmpty())
            <x-ui.empty-state :title="__('Queue clear')" :message="__('Products waiting for review appear here the moment sellers submit them.')" />
        @else
            <table class="w-full min-w-[880px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="w-10 px-3 py-2.5">
                            <input type="checkbox" wire:model.live="selectPage" aria-label="{{ __('Select all on this page') }}"
                                   class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                        </th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Product') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Store') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Category') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Price') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Status') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Submitted') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pending as $product)
                        <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="pending-{{ $product->id }}">
                            <td class="px-3 py-2">
                                <input type="checkbox" wire:model.live="selected" value="{{ $product->id }}"
                                       aria-label="{{ __('Select :name', ['name' => $product->getTranslation('name', 'en')]) }}"
                                       class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                            </td>
                            <td class="px-3 py-2">
                                @include('livewire.admin.catalog.partials.moderation-product-cell', ['product' => $product])
                            </td>
                            <td class="px-3 py-2 text-ink-soft">{{ $product->store?->name }}</td>
                            <td class="px-3 py-2 text-ink-soft">{{ $product->category?->getTranslation('name', 'en') }}</td>
                            <td class="px-3 py-2 text-right font-semibold tabular-nums whitespace-nowrap">
                                @if ($product->variants->isNotEmpty())
                                    @money($product->minPriceSen())@if ($product->maxPriceSen() !== $product->minPriceSen()) – @money($product->maxPriceSen())@endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-2"><x-ui.badge variant="warn">{{ $product->status->label() }}</x-ui.badge></td>
                            <td class="px-3 py-2 whitespace-nowrap text-[12px] text-ink-soft">{{ $product->updated_at->diffForHumans() }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" wire:click="approve({{ $product->id }})" wire:loading.attr="disabled"
                                            class="inline-flex min-h-11 items-center rounded-lg px-2 font-semibold text-emerald hover:text-emerald-deep focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Approve') }}</button>
                                    <button type="button" wire:click="startReject({{ $product->id }})"
                                            class="inline-flex min-h-11 items-center rounded-lg px-2 font-medium text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Reject') }}</button>
                                    <button type="button" wire:click="ban({{ $product->id }})"
                                            wire:confirm="{{ __('Ban ":name"? It disappears from the storefront and the seller is notified.', ['name' => $product->getTranslation('name', 'en')]) }}"
                                            class="inline-flex min-h-11 items-center rounded-lg px-2 font-medium text-danger hover:bg-danger-tint focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Ban') }}</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    @if ($pending->hasPages())
        <div>{{ $pending->links() }}</div>
    @endif

    {{-- Banned reference list --}}
    <div class="space-y-2 pt-2">
        <x-ui.section-heading :title="__('Banned products')" />
        <x-ui.card class="overflow-x-auto">
            @if ($banned->isEmpty())
                <p class="px-4 py-6 text-[13px] text-ink-faint">{{ __('No banned products.') }}</p>
            @else
                <table class="w-full min-w-[720px] text-[13px]">
                    <thead>
                        <tr class="border-b border-line text-left text-ink-soft">
                            <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Product') }}</th>
                            <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Store') }}</th>
                            <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Category') }}</th>
                            <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Price') }}</th>
                            <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Status') }}</th>
                            <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Banned') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($banned as $product)
                            <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="banned-{{ $product->id }}">
                                <td class="px-3 py-2">
                                    @include('livewire.admin.catalog.partials.moderation-product-cell', ['product' => $product])
                                </td>
                                <td class="px-3 py-2 text-ink-soft">{{ $product->store?->name }}</td>
                                <td class="px-3 py-2 text-ink-soft">{{ $product->category?->getTranslation('name', 'en') }}</td>
                                <td class="px-3 py-2 text-right font-semibold tabular-nums whitespace-nowrap">
                                    @if ($product->variants->isNotEmpty())
                                        @money($product->minPriceSen())@if ($product->maxPriceSen() !== $product->minPriceSen()) – @money($product->maxPriceSen())@endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-2"><x-ui.badge variant="danger">{{ $product->status->label() }}</x-ui.badge></td>
                                <td class="px-3 py-2 whitespace-nowrap text-[12px] text-ink-soft">{{ $product->updated_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-ui.card>
    </div>

    {{-- Reject modal --}}
    @if ($rejectOpen)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-ink/40 p-4 sm:p-8" wire:click.self="cancelReject">
            <x-ui.card class="w-full max-w-lg shadow-pop">
                <form wire:submit="confirmReject">
                    <div class="border-b border-line px-5 py-4">
                        <h2 class="font-display text-lg font-semibold">
                            {{ trans_choice('{1}Reject product|[2,*]Reject :count products', count($rejectIds), ['count' => count($rejectIds)]) }}
                        </h2>
                    </div>
                    <div class="space-y-2 px-5 py-4">
                        <label for="reject-reason" class="block text-[13px] font-medium text-ink">{{ __('Reason for the seller') }}</label>
                        <textarea id="reject-reason" wire:model="rejectReason" rows="3" required
                                  placeholder="{{ __('e.g. Images are blurry — re-shoot on a plain background.') }}"
                                  class="block w-full rounded-[var(--radius-control)] border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('rejectReason') ? 'border-danger' : 'border-line-strong' }}"></textarea>
                        @error('rejectReason')<p class="text-[13px] text-danger">{{ $message }}</p>@enderror
                        <p class="text-[13px] text-ink-faint">{{ __('The product moves back to drafts and the seller gets this reason by email and in-app.') }}</p>
                    </div>
                    <div class="flex items-center justify-end gap-2 border-t border-line px-5 py-4">
                        <button type="button" wire:click="cancelReject" class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-3 text-[13px] font-semibold text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Cancel') }}</button>
                        <button type="submit" wire:loading.attr="disabled"
                                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-[var(--radius-control)] bg-danger px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90 focus-visible:ring-2 focus-visible:ring-emerald disabled:cursor-not-allowed disabled:opacity-50">
                            {{ __('Reject and notify') }}
                        </button>
                    </div>
                </form>
            </x-ui.card>
        </div>
    @endif
</div>
