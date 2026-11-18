<div class="mx-auto w-full max-w-5xl space-y-4 px-4 py-6 lg:py-8">
    <x-ui.section-heading as="h1" :title="$managing->title">
        <x-slot:actions><x-ui.button variant="ghost" :href="route('seller.live.index')">{{ __('Back') }}</x-ui.button></x-slot:actions>
    </x-ui.section-heading>
                    <div class="border-t border-line px-5 py-4">
                        {{-- Add product --}}
                        <div class="flex flex-wrap items-end gap-2">
                            <label class="min-w-0 flex-1">
                                <span class="text-[13px] font-medium">{{ __('Add a product') }}</span>
                                <select wire:model="addProductId" class="mt-1 block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-canvas px-3 text-sm">
                                    <option value="">{{ __('Choose…') }}</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}">{{ $product->getTranslation('name', 'en') }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <button type="button" wire:click="addProduct" class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] border border-line-strong bg-surface px-4 text-sm font-medium text-ink hover:border-ink" wire:loading.attr="disabled" wire:target="addProduct"><span wire:loading.inline-flex wire:target="addProduct" role="status"><x-ui.spinner /><span class="sr-only">{{ __('Loading…') }}</span></span> {{ __('Add') }}</button>
                        </div>

                        {{-- Rail --}}
                        @if ($managing->products->isNotEmpty())
                            <ul class="mt-3 divide-y divide-line">
                                @foreach ($managing->products as $product)
                                    <li class="flex items-center justify-between gap-3 py-2.5">
                                        <span class="min-w-0 truncate text-[13px] text-ink">{{ $product->getTranslation('name', 'en') }}</span>
                                        <div class="flex items-center gap-3">
                                            @if ($managing->featured_product_id === $product->id)
                                                <span class="text-[11px] font-medium uppercase tracking-[0.06em] text-emerald">{{ __('Featured') }}</span>
                                            @else
                                                <button type="button" wire:click="feature({{ $managing->id }}, {{ $product->id }})" class="text-[13px] font-medium text-ink-soft hover:text-emerald" wire:loading.attr="disabled" wire:target="feature"><span wire:loading.inline-flex wire:target="feature" role="status"><x-ui.spinner /><span class="sr-only">{{ __('Loading…') }}</span></span> {{ __('Feature') }}</button>
                                            @endif
                                            <button type="button" wire:click="removeProduct({{ $managing->id }}, {{ $product->id }})" class="text-[13px] font-medium text-ink-soft hover:text-danger">{{ __('Remove') }}</button>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-3 text-[13px] text-ink-soft">{{ __('No products on the rail yet.') }}</p>
                        @endif
                    </div>
</div>
