{{-- The ONE pager (HB-G165). Light only, brand colours, no `dark:` classes: Tailwind 4's dark
     variant follows the OS colour scheme, and this site has no dark theme, so a dark-mode Mac
     painted the old copy of Livewire's pager navy (Haze 2026-11-19). x-ui.table.footer prints
     "Showing x to y of z" beside it; vendor/livewire/tailwind.blade.php (every bare ->links())
     prints the same line and includes this file. --}}
@php
if (! isset($scrollTo)) {
    $scrollTo = 'body';
}

$scrollIntoViewJsSnippet = ($scrollTo !== false)
    ? <<<JS
       (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
    JS
    : '';

$pageName = $paginator->getPageName();
$dusk = $pageName == 'page' ? '' : '.'.$pageName;
// Desktop cells are 36px (Haze: "a bit smaller" than the 44px bar); phone prev/next keep 44px.
$cell = 'inline-flex min-h-9 min-w-9 items-center justify-center border px-2.5 text-[13px] font-medium leading-5';
$link = "$cell border-line bg-surface text-ink hover:bg-paper hover:border-line-strong focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-emerald transition-colors duration-150";
$off = "$cell cursor-default border-line bg-surface text-ink-faint";
$phone = 'inline-flex min-h-11 items-center justify-center rounded-[var(--radius-control)] border border-line bg-surface px-3 text-[13px] font-medium leading-5';
@endphp

<div>
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="Pagination Navigation">
            <div class="flex justify-between gap-3 sm:hidden">
                @if ($paginator->onFirstPage())
                    <span class="{{ $phone }} cursor-default text-ink-faint">{!! __('pagination.previous') !!}</span>
                @else
                    <button type="button" wire:click="previousPage('{{ $pageName }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" dusk="previousPage{{ $dusk }}.before" class="{{ $phone }} text-ink hover:bg-paper focus-visible:outline-2 focus-visible:outline-emerald">{!! __('pagination.previous') !!}</button>
                @endif

                @if ($paginator->hasMorePages())
                    <button type="button" wire:click="nextPage('{{ $pageName }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" dusk="nextPage{{ $dusk }}.before" class="{{ $phone }} text-ink hover:bg-paper focus-visible:outline-2 focus-visible:outline-emerald">{!! __('pagination.next') !!}</button>
                @else
                    <span class="{{ $phone }} cursor-default text-ink-faint">{!! __('pagination.next') !!}</span>
                @endif
            </div>

            <div class="hidden sm:flex sm:justify-end">
                <span class="inline-flex rtl:flex-row-reverse">
                    @if ($paginator->onFirstPage())
                        <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                            <span class="{{ $off }} rounded-l-[var(--radius-control)]" aria-hidden="true">
                                <svg class="size-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                            </span>
                        </span>
                    @else
                        <button type="button" wire:click="previousPage('{{ $pageName }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" dusk="previousPage{{ $dusk }}.after" class="{{ $link }} rounded-l-[var(--radius-control)]" aria-label="{{ __('pagination.previous') }}">
                            <svg class="size-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                        </button>
                    @endif

                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <span aria-disabled="true"><span class="{{ $off }} -ml-px">{{ $element }}</span></span>
                        @endif

                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                <span wire:key="paginator-{{ $pageName }}-page{{ $page }}">
                                    @if ($page == $paginator->currentPage())
                                        <span aria-current="page"><span class="{{ $cell }} relative z-10 -ml-px cursor-default border-emerald bg-emerald text-white">{{ $page }}</span></span>
                                    @else
                                        <button type="button" wire:click="gotoPage({{ $page }}, '{{ $pageName }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" class="{{ $link }} -ml-px" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</button>
                                    @endif
                                </span>
                            @endforeach
                        @endif
                    @endforeach

                    @if ($paginator->hasMorePages())
                        <button type="button" wire:click="nextPage('{{ $pageName }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" dusk="nextPage{{ $dusk }}.after" class="{{ $link }} -ml-px rounded-r-[var(--radius-control)]" aria-label="{{ __('pagination.next') }}">
                            <svg class="size-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
                        </button>
                    @else
                        <span aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                            <span class="{{ $off }} -ml-px rounded-r-[var(--radius-control)]" aria-hidden="true">
                                <svg class="size-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
                            </span>
                        </span>
                    @endif
                </span>
            </div>
        </nav>
    @endif
</div>
