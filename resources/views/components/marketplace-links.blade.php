@props(['product'])

@php
    // Read the setting here rather than taking it as a prop, the same way
    // product-card.blade.php does — this component is dropped into pages that
    // do not all pass it down.
    $purchasingEnabled = app(\App\Settings\GeneralSettings::class)->purchasing_enabled;
@endphp

@if ($product->showsMarketplaceLinks($purchasingEnabled))
    {{-- <details> rather than an Alpine dropdown: it is a disclosure widget the
         browser already ships — keyboard, Escape, screen-reader state and the
         no-JS case all come free, and this list is plain links with no state to
         hold. The app has no dropdown component to reuse, so the alternative was
         writing one. --}}
    <details class="group mt-4" data-testid="pdp-marketplace-links">
        <summary class="inline-flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-[var(--radius-control)] border border-line-strong bg-surface px-4 text-[13px] font-medium text-ink transition-colors duration-(--dur-micro) hover:bg-paper focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald [&::-webkit-details-marker]:hidden">
            {{ __('Also available in') }}
            <svg class="size-3.5 shrink-0 text-ink-faint transition-transform duration-(--dur-micro) group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
        </summary>

        <ul class="mt-2 max-w-xs overflow-hidden rounded-[var(--radius-control)] border border-line-strong bg-surface">
            @foreach ($product->marketplaceLinks as $link)
                <li class="border-b border-line last:border-b-0">
                    {{-- A raw anchor, not x-ui.button: that component hardcodes
                         wire:navigate on its <a> branch with no opt-out, and
                         wire:navigate on a cross-origin URL does not work.
                         rel follows the courier-tracking link in account/order-detail,
                         plus nofollow+ugc — these are seller-submitted outbound links
                         and should not hand our SEO credit to a competitor. --}}
                    <a href="{{ $link->url }}"
                       target="_blank"
                       rel="noopener noreferrer nofollow ugc"
                       class="flex min-h-11 items-center justify-between gap-2 px-4 text-[13px] text-ink transition-colors duration-(--dur-micro) hover:bg-paper focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald">
                        <span class="truncate">{{ $link->title }}</span>
                        <svg class="size-3.5 shrink-0 text-ink-faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                        <span class="sr-only">{{ __('(opens in a new tab)') }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        <p class="mt-2 text-[13px] text-ink-faint">{{ __('You will be taken to the seller’s listing on another site.') }}</p>
    </details>
@endif
