{{-- The page had no description, no OG card and no canonical, so it shared
     as a bare URL. `og:image` reuses the mail brand mark: it is the only
     raster brand asset in the repo, and a 56px glyph still beats no card at
     all. Swap it for a proper 1200x630 share image when one exists. --}}
@push('meta')
    @php $landingBlurb = __('Shop halal-certified products from verified Malaysian sellers, or open your own stall. HalalBizs is Malaysia\'s halal-first marketplace.'); @endphp
    <meta name="description" content="{{ $landingBlurb }}">
    <link rel="canonical" href="{{ route('landing') }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="{{ __('Malaysia’s Halal-First Marketplace') }}">
    <meta property="og:description" content="{{ $landingBlurb }}">
    <meta property="og:url" content="{{ route('landing') }}">
    <meta property="og:image" content="{{ rtrim(config('app.url'), '/') }}/email/hb-mark.png">
    <meta name="twitter:card" content="summary">
@endpush

<div>
    @include('livewire.storefront.landing.hero', ['categories' => $categories])
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
