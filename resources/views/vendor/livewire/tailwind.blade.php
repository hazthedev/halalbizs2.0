@php
if (! isset($scrollTo)) {
    $scrollTo = 'body';
}

$scrollIntoViewJsSnippet = ($scrollTo !== false)
    ? <<<JS
       (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
    JS
    : '';
@endphp

<div>
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="Pagination Navigation" class="flex items-center justify-between">
            <div class="flex justify-between flex-1 sm:hidden">
                <span>
                    @if ($paginator->onFirstPage())
                        <span class="inline-flex min-h-11 min-w-11 items-center justify-center border border-line bg-surface px-3 text-sm font-medium leading-5 cursor-default text-ink-faint rounded-[var(--radius-control)]">
                            {!! __('pagination.previous') !!}
                        </span>
                    @else
                        <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.before" class="inline-flex min-h-11 min-w-11 items-center justify-center border border-line bg-surface px-3 text-sm font-medium leading-5 text-ink hover:bg-paper hover:border-line-strong focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-emerald transition-colors duration-150 rounded-[var(--radius-control)]">
                            {!! __('pagination.previous') !!}
                        </button>
                    @endif
                </span>

                <span>
                    @if ($paginator->hasMorePages())
                        <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.before" class="inline-flex min-h-11 min-w-11 items-center justify-center border border-line bg-surface px-3 text-sm font-medium leading-5 text-ink hover:bg-paper hover:border-line-strong focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-emerald transition-colors duration-150 ml-3 rounded-[var(--radius-control)]">
                            {!! __('pagination.next') !!}
                        </button>
                    @else
                        <span class="inline-flex min-h-11 min-w-11 items-center justify-center border border-line bg-surface px-3 text-sm font-medium leading-5 cursor-default text-ink-faint ml-3 rounded-[var(--radius-control)]">
                            {!! __('pagination.next') !!}
                        </span>
                    @endif
                </span>
            </div>

            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="text-[13px] leading-5 text-ink-soft">
                        <span>{!! __('Showing') !!}</span>
                        <span class="font-medium text-ink">{{ $paginator->firstItem() }}</span>
                        <span>{!! __('to') !!}</span>
                        <span class="font-medium text-ink">{{ $paginator->lastItem() }}</span>
                        <span>{!! __('of') !!}</span>
                        <span class="font-medium text-ink">{{ $paginator->total() }}</span>
                        <span>{!! __('results') !!}</span>
                    </p>
                </div>

                <div>
                    <span class="relative z-0 inline-flex rtl:flex-row-reverse">
                        <span>
                            {{-- Previous Page Link --}}
                            @if ($paginator->onFirstPage())
                                <span aria-disabled="true">
                                    <span class="inline-flex min-h-11 min-w-11 items-center justify-center border border-line bg-surface px-3 text-sm font-medium leading-5 cursor-default text-ink-faint rounded-l-[var(--radius-control)]" aria-hidden="true">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                </span>
                            @else
                                <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.after" class="inline-flex min-h-11 min-w-11 items-center justify-center border border-line bg-surface px-3 text-sm font-medium leading-5 text-ink hover:bg-paper hover:border-line-strong focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-emerald transition-colors duration-150 rounded-l-[var(--radius-control)]" aria-label="{{ __('pagination.previous') }}">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            @endif
                        </span>

                        {{-- Pagination Elements --}}
                        @foreach ($elements as $element)
                            {{-- "Three Dots" Separator --}}
                            @if (is_string($element))
                                <span aria-disabled="true">
                                    <span class="inline-flex min-h-11 min-w-11 items-center justify-center border border-line bg-surface px-3 text-sm font-medium leading-5 cursor-default text-ink-faint -ml-px">{{ $element }}</span>
                                </span>
                            @endif

                            {{-- Array Of Links --}}
                            @if (is_array($element))
                                @foreach ($element as $page => $url)
                                    <span wire:key="paginator-{{ $paginator->getPageName() }}-page{{ $page }}">
                                        @if ($page == $paginator->currentPage())
                                            <span aria-current="page">
                                                <span class="inline-flex min-h-11 min-w-11 items-center justify-center border border-line bg-surface px-3 text-sm font-medium leading-5 cursor-default border-emerald bg-emerald text-white -ml-px">{{ $page }}</span>
                                            </span>
                                        @else
                                            <button type="button" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" class="inline-flex min-h-11 min-w-11 items-center justify-center border border-line bg-surface px-3 text-sm font-medium leading-5 text-ink hover:bg-paper hover:border-line-strong focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-emerald transition-colors duration-150 -ml-px" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                                {{ $page }}
                                            </button>
                                        @endif
                                    </span>
                                @endforeach
                            @endif
                        @endforeach

                        <span>
                            {{-- Next Page Link --}}
                            @if ($paginator->hasMorePages())
                                <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.after" class="inline-flex min-h-11 min-w-11 items-center justify-center border border-line bg-surface px-3 text-sm font-medium leading-5 text-ink hover:bg-paper hover:border-line-strong focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-emerald transition-colors duration-150 -ml-px rounded-r-[var(--radius-control)]" aria-label="{{ __('pagination.next') }}">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            @else
                                <span aria-disabled="true">
                                    <span class="inline-flex min-h-11 min-w-11 items-center justify-center border border-line bg-surface px-3 text-sm font-medium leading-5 cursor-default text-ink-faint -ml-px rounded-r-[var(--radius-control)]" aria-hidden="true">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                </span>
                            @endif
                        </span>
                    </span>
                </div>
            </div>
        </nav>
    @endif
</div>
