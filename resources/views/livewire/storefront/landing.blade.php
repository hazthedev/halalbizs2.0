<div>
    @include('livewire.storefront.landing.hero', ['categories' => $categories])
    @include('livewire.storefront.landing.trust')
    @include('livewire.storefront.landing.categories', ['categories' => $categories])
    @include('livewire.storefront.landing.how-it-works')
    @include('livewire.storefront.landing.stats', ['stats' => $stats])
    @include('livewire.storefront.landing.seller-cta')
    @include('livewire.storefront.landing.footer-cta')
</div>

{{-- No motion script here on purpose — this pass is structure + static CSS
     skin only (see resources/css/app.css's "Night Souk" section for the
     marquee/lantern-bob motion, all pure CSS and reduced-motion gated). A
     later task wires an anime.js layer against the data-plx/data-motion
     hooks left in this markup; the old GSAP-based resources/js/landing.js
     targets a different DOM shape (background-position parallax, no
     data-plx) and is intentionally left unwired until that task replaces
     it. Nothing on this page requires JS to be visible or readable. --}}
