<x-account-shell active="wishlist" :title="__('Wishlist')">
    @if ($products->isNotEmpty())
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 xl:grid-cols-4">
            @foreach ($products as $product)
                <div wire:key="wishlist-product-{{ $product->id }}">
                    <x-product-card :product="$product" :wishlisted="true" />
                </div>
            @endforeach
        </div>
    @else
        <x-ui.card class="px-6 py-16 text-center">
            <p class="font-display text-xl font-semibold">{{ __('Nothing saved yet') }}</p>
            <p class="mt-1 text-sm text-ink-soft">{{ __('Tap the heart on any product and it will be kept here.') }}</p>
            <div class="mt-5">
                <x-ui.button :href="route('home')">{{ __('Browse products') }}</x-ui.button>
            </div>
        </x-ui.card>
    @endif
</x-account-shell>
