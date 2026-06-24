<div class="mx-auto w-full max-w-7xl px-4 py-8 lg:py-12">
    <x-ui.section-heading as="h1" :title="__('Search by image')" />
    <p class="mt-1 text-[13px] text-ink-soft">{{ __('Upload a photo and we’ll find visually similar products.') }}</p>

    {{-- Uploader --}}
    <div class="mt-5 max-w-xl">
        <label for="visual-upload"
               class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-[var(--radius-card)] border-2 border-dashed border-line-strong bg-surface px-4 py-10 text-center transition-colors hover:border-emerald">
            <svg class="size-7 text-ink-faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/></svg>
            <span class="text-sm font-semibold text-ink">{{ __('Choose a photo') }}</span>
            <span class="text-[13px] text-ink-soft">{{ __('JPG or PNG, up to 8MB') }}</span>
            <input id="visual-upload" type="file" wire:model="image" accept="image/*" class="sr-only">
        </label>

        <div wire:loading wire:target="image" class="mt-3 text-[13px] text-ink-soft">{{ __('Reading your image…') }}</div>
        @error('image') <p class="mt-2 text-[13px] text-danger">{{ $message }}</p> @enderror

        @if ($image)
            <div class="mt-3 flex items-center gap-3">
                <img src="{{ $image->temporaryUrl() }}" alt="" class="size-16 rounded-[var(--radius-card)] border border-line object-cover">
                <button type="button" wire:click="clear" class="text-[13px] font-semibold text-ink-soft hover:text-danger">{{ __('Clear') }}</button>
            </div>
        @endif
    </div>

    {{-- Results --}}
    @if ($image && $products->isEmpty())
        <p class="mt-8 text-sm text-ink-soft">{{ __('No close matches yet — try another photo.') }}</p>
    @elseif ($products->isNotEmpty())
        <h2 class="mt-8 font-display text-lg font-bold">{{ __('Visually similar') }}</h2>
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($products as $product)
                <x-product-card :product="$product" :wishlisted="in_array($product->id, $wishlistedIds)" wire:key="vs-{{ $product->id }}" />
            @endforeach
        </div>
    @endif
</div>
