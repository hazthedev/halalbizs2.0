{{-- Mirrors the real strip (heading + six cards) so there's no layout shift — design §6/§9. --}}
<div>
    <section class="mt-10" aria-hidden="true">
        <x-ui.skeleton class="h-7 w-52" />
        <div class="mt-4 flex gap-3 overflow-x-hidden pb-2">
            @for ($i = 0; $i < 6; $i++)
                <div class="w-44 shrink-0 overflow-hidden rounded-[10px] border border-line bg-surface sm:w-48">
                    <x-ui.skeleton class="aspect-square w-full rounded-none" />
                    <div class="space-y-2 p-3">
                        <x-ui.skeleton class="h-4 w-full" />
                        <x-ui.skeleton class="h-4 w-2/3" />
                        <x-ui.skeleton class="h-3 w-1/2" />
                    </div>
                </div>
            @endfor
        </div>
    </section>
</div>
