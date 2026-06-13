@props(['rows' => 8])

{{-- Row skeletons for seller/admin datagrids (design §6): line blocks with
     an animate-pulse shimmer, matching the table's column rhythm — mono id,
     date, name, numbers, status pill. Shown via wire:loading only. --}}
<div {{ $attributes->merge(['class' => 'px-3']) }} aria-hidden="true">
    <div class="divide-y divide-line">
        @for ($i = 0; $i < $rows; $i++)
            <div class="flex items-center gap-4 py-3.5">
                <x-ui.skeleton class="h-4 w-28 shrink-0" />
                <x-ui.skeleton class="hidden h-4 w-20 shrink-0 sm:block" />
                <x-ui.skeleton class="h-4 min-w-0 flex-1" />
                <x-ui.skeleton class="hidden h-4 w-10 shrink-0 sm:block" />
                <x-ui.skeleton class="h-4 w-16 shrink-0" />
                <x-ui.skeleton class="h-5 w-20 shrink-0 rounded-full" />
            </div>
        @endfor
    </div>
</div>
