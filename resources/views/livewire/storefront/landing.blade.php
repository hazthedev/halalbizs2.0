<div>
    @include('livewire.storefront.landing.hero')
    @include('livewire.storefront.landing.trust')
    @include('livewire.storefront.landing.categories', ['categories' => $categories])
    @include('livewire.storefront.landing.how-it-works')
    @include('livewire.storefront.landing.stats', ['stats' => $stats])
    @include('livewire.storefront.landing.seller-cta')
    @include('livewire.storefront.landing.footer-cta')
</div>

{{-- Motion layer for this page only — see resources/js/landing.js. --}}
@push('scripts')
    @vite(['resources/js/landing.js'])
@endpush
