@props(['product'])

@php
    // Read the setting here rather than taking it as a prop, the same way
    // product-card.blade.php does — this component is dropped into pages that
    // do not all pass it down.
    $purchasingEnabled = app(\App\Settings\GeneralSettings::class)->purchasing_enabled;
@endphp

@if ($product->showsMarketplaceLinks($purchasingEnabled))
    <div class="mt-4" data-testid="pdp-marketplace-links">
        <p class="text-[11px] font-medium uppercase tracking-[0.06em] text-ink-faint">{{ __('Also available on') }}</p>

        <div class="mt-2 flex flex-wrap gap-2">
            @foreach ($product->marketplaceLinks as $link)
                {{-- A raw anchor, not x-ui.button: that component hardcodes
                     wire:navigate on its <a> branch with no opt-out, and
                     wire:navigate on a cross-origin URL does not work.
                     rel follows the courier-tracking link in account/order-detail,
                     plus nofollow+ugc — these are seller-submitted outbound links
                     and should not hand our SEO credit to a competitor. --}}
                <a href="{{ $link->url }}"
                   target="_blank"
                   rel="noopener noreferrer nofollow ugc"
                   class="inline-flex min-h-11 items-center gap-1.5 rounded-[var(--radius-control)] border border-line-strong bg-surface px-4 text-[13px] font-medium text-ink transition-colors duration-(--dur-micro) hover:bg-paper focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                    {{ __('Buy on :platform', ['platform' => $link->label()]) }}
                    <svg class="size-3.5 shrink-0 text-ink-faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                    <span class="sr-only">{{ __('(opens in a new tab)') }}</span>
                </a>
            @endforeach
        </div>

        <p class="mt-2 text-[13px] text-ink-faint">{{ __('You will be taken to the seller’s listing on another site.') }}</p>
    </div>
@endif
