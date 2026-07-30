{{-- /welcome — rebuilt 2026-07-30 against the reference design concept.
     Section order is the reference's: hero · trust rail · departments · newly
     verified · the dark verification band · brand in focus. The chrome (trust
     ticker, header, department row, footer) comes from layouts.storefront,
     because in the reference that chrome is part of the design. --}}
@push('meta')
    @php $landingBlurb = __('Shop halal-certified products from verified Malaysian sellers, or open your own stall. HalalBizs is Malaysia\'s certificate-first marketplace.'); @endphp
    <meta name="description" content="{{ $landingBlurb }}">
    <link rel="canonical" href="{{ route('landing') }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="{{ __('Malaysia’s Halal-First Marketplace') }}">
    <meta property="og:description" content="{{ $landingBlurb }}">
    <meta property="og:url" content="{{ route('landing') }}">
    <meta property="og:image" content="{{ rtrim(config('app.url'), '/') }}/images/landing/hero-lineup.webp">
    <meta name="twitter:card" content="summary_large_image">
@endpush

<div>
    @include('livewire.storefront.landing.hero', ['stats' => $stats])
    @include('livewire.storefront.landing.trust')
    @include('livewire.storefront.landing.categories', ['categories' => $categories])
    @include('livewire.storefront.landing.newly-verified', ['newlyVerified' => $newlyVerified, 'stats' => $stats])
    @include('livewire.storefront.landing.how-it-works')
    @include('livewire.storefront.landing.brand-focus', ['featuredStore' => $featuredStore])
</div>
