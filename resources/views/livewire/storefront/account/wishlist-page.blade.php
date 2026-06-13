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
        <x-ui.empty-state :title="__('Nothing saved yet')" :message="__('Tap the heart on any product and it will be kept here.')">
            <x-ui.button :href="route('home')">{{ __('Browse products') }}</x-ui.button>
        </x-ui.empty-state>
    @endif
</x-account-shell>
