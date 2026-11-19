{{-- Every bare ->links() (storefront lists, and any list not on x-ui.table.footer yet): the
     "Showing x to y of z" line plus the ONE pager, pagination/table.blade.php (HB-G165). --}}
<div>
    @if ($paginator->hasPages())
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="hidden text-[13px] leading-5 text-ink-soft sm:block">
                <span>{!! __('Showing') !!}</span>
                <span class="font-medium text-ink">{{ $paginator->firstItem() }}</span>
                <span>{!! __('to') !!}</span>
                <span class="font-medium text-ink">{{ $paginator->lastItem() }}</span>
                <span>{!! __('of') !!}</span>
                <span class="font-medium text-ink">{{ $paginator->total() }}</span>
                <span>{!! __('results') !!}</span>
            </p>
            <div class="max-sm:w-full">@include('pagination.table')</div>
        </div>
    @endif
</div>
