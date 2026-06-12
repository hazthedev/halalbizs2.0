{{-- Thumb + name + storefront preview link. Pending products 404 publicly;
     the PDP lets admins (and the owner) preview, so the link works while signed in. --}}
<div class="flex items-center gap-3">
    @if ($url = $product->getFirstMediaUrl('images', 'thumb'))
        <img src="{{ $url }}" alt="{{ $product->getTranslation('name', 'en') }}" class="size-10 shrink-0 rounded-lg border border-line bg-paper object-cover">
    @else
        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-line bg-paper text-ink-faint">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 19.5h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Z"/></svg>
        </span>
    @endif
    <div class="min-w-0">
        <span class="line-clamp-2 max-w-64 font-medium text-ink">{{ $product->getTranslation('name', 'en') }}</span>
        <a href="{{ route('product.show', $product) }}" target="_blank" rel="noopener"
           class="inline-flex min-h-6 items-center gap-1 text-[12px] font-medium text-emerald hover:text-emerald-deep focus-visible:ring-2 focus-visible:ring-emerald">
            {{ __('Preview on storefront') }}
            <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
        </a>
    </div>
</div>
