<div class="space-y-4">

    <x-ui.section-heading :title="__('Home sections')" as="h1">
        @if ($missingTypes !== [] || $sections->contains(fn ($section) => $section->draft !== null))
            <x-slot:actions>
                <div class="flex items-center gap-2">
                    @can('cms.publish')
                        @if ($sections->contains(fn ($section) => $section->draft !== null))
                            <x-ui.button wire:click="publishAllDrafts">{{ __('Publish all') }}</x-ui.button>
                        @endif
                    @endcan
                    @if ($missingTypes !== [])
                    <label for="add-type" class="sr-only">{{ __('Section type') }}</label>
                    <select id="add-type" wire:model="addType"
                            class="min-h-11 rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 py-2 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                        <option value="">{{ __('Choose a section…') }}</option>
                        @foreach ($missingTypes as $type)
                            <option value="{{ $type }}">{{ \App\Livewire\Admin\Content\HomeSections::typeLabel($type) }}</option>
                        @endforeach
                    </select>
                    <x-ui.button wire:click="addSection">{{ __('Add section') }}</x-ui.button>
                    @endif
                </div>
            </x-slot:actions>
        @endif
    </x-ui.section-heading>

    <p class="text-[13px] text-ink-soft">{{ __('These sections render on the storefront home page, top to bottom.') }}</p>

    {{-- Which layout is on screen: the everyday one, or one that shows by itself between two dates (item 12). --}}
    <x-ui.card class="space-y-3 p-4">
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-56">
                <label for="layout-choice" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Layout') }}</label>
                <select id="layout-choice" wire:change="chooseLayout($event.target.value === '' ? null : Number($event.target.value))"
                        class="block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 py-2 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                    <option value="" @selected($layout === null)>{{ __('Everyday layout') }}</option>
                    @foreach ($layouts as $option)
                        <option value="{{ $option->id }}" @selected($layout?->id === $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>

            @if ($layout !== null)
                <a href="{{ route('admin.content.preview', ['path' => '/?layout='.$layout->id]) }}" target="_blank" rel="noopener"
                   class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-2 text-[13px] font-medium text-ink-soft hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Preview this layout') }}</a>
            @endif

            @can('cms.publish')
                @if ($layout !== null)
                    <button type="button" wire:click="editLayout"
                            class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-2 text-[13px] font-medium text-ink-soft hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Change name or dates') }}</button>
                    <button type="button" wire:click="deleteLayout({{ $layout->id }})"
                            wire:confirm="{{ __('Delete this layout and its sections? The everyday layout is not touched.') }}"
                            class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-2 text-[13px] font-medium text-danger hover:bg-danger-tint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Delete layout') }}</button>
                @endif
                <x-ui.button variant="ghost" wire:click="newLayout" class="ml-auto">{{ __('New scheduled layout') }}</x-ui.button>
            @endcan
        </div>

        <p class="text-[13px] text-ink-soft">
            @if ($layout === null)
                {{ __('The everyday layout shows whenever no scheduled layout is running.') }}
            @else
                {{ $layout->starts_at->display()->format('d M Y H:i') }} → {{ $layout->ends_at->display()->format('d M Y H:i') }} ·
                @if ($layout->isShowingNow())
                    <x-ui.badge variant="neutral">{{ __('Showing now') }}</x-ui.badge>
                @elseif ($layout->ends_at->isPast())
                    {{ __('Ended. The everyday layout is back.') }}
                @else
                    {{ __('Switches on by itself at the start and back to the everyday layout at the end.') }}
                @endif
            @endif
        </p>

        @if ($showLayoutForm)
            <form wire:submit="saveLayout" class="space-y-4 border-t border-line pt-4">
                <div class="grid gap-4 lg:grid-cols-3">
                    <x-ui.input :label="__('Layout name')" wire:model="layoutName" maxlength="80" :error="$errors->first('layoutName')" :hint="__('For example: Ramadan.')" />
                    <x-ui.input :label="__('Starts at')" type="datetime-local" wire:model="layoutStarts" :error="$errors->first('layoutStarts')" />
                    <x-ui.input :label="__('Ends at')" type="datetime-local" wire:model="layoutEnds" :error="$errors->first('layoutEnds')" />
                </div>
                @unless ($editingLayout)
                    <p class="text-[13px] text-ink-faint">{{ __('It starts as a copy of the everyday layout. Change its sections, then publish them before the start.') }}</p>
                @endunless
                <div class="flex items-center gap-2">
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="saveLayout">{{ __('Save layout') }}</x-ui.button>
                    <x-ui.button variant="ghost" wire:click="cancelLayout">{{ __('Cancel') }}</x-ui.button>
                </div>
            </form>
        @endif
    </x-ui.card>

    <x-ui.card>
        @if ($sections->isEmpty())
            <x-ui.empty-state :title="__('The home page is empty')" :message="__('Add a section above — buyers see it the moment it\'s active.')" />
        @else
            <ul class="divide-y divide-line">
                @foreach ($sections as $section)
                    <li wire:key="section-{{ $section->id }}">
                        <div class="flex flex-wrap items-center gap-2 px-3 py-2">
                            {{-- Reorder --}}
                            <div class="flex items-center gap-0.5">
                                <button type="button" wire:click="move({{ $section->id }}, -1)" @disabled($loop->first)
                                        aria-label="{{ __('Move up') }}"
                                        class="inline-flex size-11 items-center justify-center rounded-[var(--radius-control)] text-ink-soft hover:text-ink disabled:opacity-30 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5"/></svg>
                                </button>
                                <button type="button" wire:click="move({{ $section->id }}, 1)" @disabled($loop->last)
                                        aria-label="{{ __('Move down') }}"
                                        class="inline-flex size-11 items-center justify-center rounded-[var(--radius-control)] text-ink-soft hover:text-ink disabled:opacity-30 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                                </button>
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="text-[13px] font-medium text-ink">
                                    {{ \App\Livewire\Admin\Content\HomeSections::typeLabel($section->type) }}
                                    @if ($section->draft !== null)
                                        <x-ui.badge variant="neutral" class="ml-1">{{ __('Draft') }}</x-ui.badge>
                                    @endif
                                    @if (($section->show_on ?? 'both') !== 'both')
                                        <x-ui.badge variant="neutral" class="ml-1">{{ $section->show_on === 'web' ? __('Web only') : __('App only') }}</x-ui.badge>
                                    @endif
                                </p>
                                <p class="truncate text-[12px] text-ink-soft">
                                    {{ $section->getTranslation('title', 'en', false) ?: __('No heading') }}
                                    @if ($section->payload)
                                        · <span class="font-mono">{{ collect($section->payload)->map(fn ($v, $k) => "$k: $v")->implode(' · ') }}</span>
                                    @endif
                                </p>
                            </div>

                            {{-- Active toggle --}}
                            <button type="button" role="switch" aria-checked="{{ $section->is_active ? 'true' : 'false' }}"
                                    wire:click="toggleActive({{ $section->id }})"
                                    aria-label="{{ __('Toggle :type', ['type' => \App\Livewire\Admin\Content\HomeSections::typeLabel($section->type)]) }}"
                                    class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-[var(--radius-control)] focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                <span class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-150 {{ $section->is_active ? 'bg-emerald' : 'bg-line-strong' }}">
                                    <span class="inline-block size-4 rounded-full bg-white transition-transform duration-150 {{ $section->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                </span>
                            </button>

                            <x-ui.draft-actions type="section" :id="$section->id" :draft="$section->draft" :preview-path="$layout === null ? '/' : '/?layout='.$layout->id" />

                            <button type="button" wire:click="{{ $editingId === $section->id ? 'cancel' : 'edit('.$section->id.')' }}"
                                    class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-2 text-[13px] font-medium text-ink-soft hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                {{ $editingId === $section->id ? __('Close') : __('Edit') }}
                            </button>
                            <button type="button" wire:click="delete({{ $section->id }})"
                                    wire:confirm="{{ __('Remove this section from the home page?') }}"
                                    class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-2 text-[13px] font-medium text-danger hover:bg-danger-tint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                {{ __('Remove') }}
                            </button>
                        </div>

                        {{-- Inline editor --}}
                        @if ($editingId === $section->id)
                            <form wire:submit="save" class="space-y-4 border-t border-line bg-paper px-4 py-4">
                                <div class="grid gap-4 lg:grid-cols-3">
                                    <x-ui.input :label="__('Heading (English)')" wire:model="title.en" :error="$errors->first('title.en')" :hint="__('Optional — leave blank for no heading.')" />
                                    <x-ui.input :label="__('Heading (Bahasa Melayu)')" wire:model="title.ms" :error="$errors->first('title.ms')" :hint="__('Optional — English is shown when empty.')" />
                                    <x-ui.input :label="__('Heading (Vietnamese)')" wire:model="title.vi" :error="$errors->first('title.vi')" :hint="__('Optional — English is shown when empty.')" />
                                </div>

                                @if (in_array($section->type, ['product_carousel', 'product_grid'], true))
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label for="section-source" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Source') }}</label>
                                            <select id="section-source" wire:model="source"
                                                    class="block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 py-2 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                                <option value="latest">{{ __('Latest products') }}</option>
                                                <option value="top">{{ __('Top sellers') }}</option>
                                            </select>
                                            @error('source')<p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>@enderror
                                        </div>
                                        <x-ui.input :label="__('Limit')" type="number" min="1" max="48" wire:model="limit" :error="$errors->first('limit')" :hint="__('How many products to show.')" />
                                    </div>
                                @elseif ($section->type === 'category_grid')
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <x-ui.input :label="__('Limit')" type="number" min="1" max="48" wire:model="limit" :error="$errors->first('limit')" :hint="__('How many top-level categories to show.')" />
                                    </div>
                                @else
                                    <p class="text-[13px] text-ink-faint">
                                        {{ $section->type === 'banner' ? __('Shows every active banner — manage them on the Banners page.') : __('Filled from each visitor\'s own browsing history — nothing to configure.') }}
                                    </p>
                                @endif

                                <div class="sm:max-w-xs">
                                    <label for="section-show-on" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Show on') }}</label>
                                    <select id="section-show-on" wire:model="showOn"
                                            class="block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 py-2 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                        <option value="both">{{ __('Web and app') }}</option>
                                        <option value="web">{{ __('Web only') }}</option>
                                        <option value="app">{{ __('App only') }}</option>
                                    </select>
                                    @error('showOn')<p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>@enderror
                                </div>

                                <div class="flex items-center gap-2">
                                    <x-ui.button type="submit" wire:loading.attr="disabled">{{ __('Save section') }}</x-ui.button>
                                    <x-ui.button variant="ghost" wire:click="cancel">{{ __('Cancel') }}</x-ui.button>
                                </div>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</div>
