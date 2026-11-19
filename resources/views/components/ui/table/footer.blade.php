@props(['rows'])
{{-- Standard list footer (HB-G165): "Showing x to y of z" bottom left, the one pager bottom right,
     on one row; it wraps on narrow widths. Rows per page lives above the table: x-ui.table.per-page. --}}
<div class="flex flex-wrap items-center justify-between gap-3 text-[13px] text-ink-soft">
    <p role="status">{{ __('table.showing', ['from' => $rows->firstItem() ?? 0, 'to' => $rows->lastItem() ?? 0, 'total' => $rows->total()]) }}</p>
    @if ($rows->hasPages())
        <div class="max-sm:w-full">{{ $rows->links('pagination.table') }}</div>
    @endif
</div>
