{{-- Skeleton matches the real tab: summary (big average + 5 bars) and three review rows — design §6/§9, no CLS. --}}
<div aria-hidden="true">
    <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:gap-10">
        <div class="shrink-0 space-y-2">
            <x-ui.skeleton class="h-12 w-20" />
            <x-ui.skeleton class="h-5 w-28" />
            <x-ui.skeleton class="h-4 w-24" />
        </div>
        <div class="w-full max-w-sm space-y-1.5">
            @for ($i = 0; $i < 5; $i++)
                <x-ui.skeleton class="h-2.5 w-full rounded-full" />
            @endfor
        </div>
    </div>
    <div class="mt-6 flex flex-wrap gap-2">
        @for ($i = 0; $i < 4; $i++)
            <x-ui.skeleton class="h-11 w-20 rounded-full" />
        @endfor
    </div>
    <div class="mt-2 divide-y divide-line">
        @for ($i = 0; $i < 3; $i++)
            <div class="space-y-2 py-4">
                <x-ui.skeleton class="h-4 w-40" />
                <x-ui.skeleton class="h-4 w-24" />
                <x-ui.skeleton class="h-4 w-full max-w-prose" />
            </div>
        @endfor
    </div>
</div>
