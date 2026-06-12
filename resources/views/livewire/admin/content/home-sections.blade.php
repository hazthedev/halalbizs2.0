<div class="space-y-4">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl font-bold">{{ __('Home sections') }}</h1>

        @if ($missingTypes !== [])
            <div class="flex items-center gap-2">
                <label for="add-type" class="sr-only">{{ __('Section type') }}</label>
                <select id="add-type" wire:model="addType"
                        class="min-h-11 rounded-lg border border-line-strong bg-surface px-3 py-2 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                    <option value="">{{ __('Choose a section…') }}</option>
                    @foreach ($missingTypes as $type)
                        <option value="{{ $type }}">{{ \App\Livewire\Admin\Content\HomeSections::typeLabel($type) }}</option>
                    @endforeach
                </select>
                <x-ui.button wire:click="addSection">{{ __('Add section') }}</x-ui.button>
            </div>
        @endif
    </div>

    <p class="text-[13px] text-ink-soft">{{ __('These sections render on the storefront home page, top to bottom.') }}</p>

    <x-ui.card>
        @if ($sections->isEmpty())
            <div class="px-6 py-16 text-center">
                <h2 class="font-display text-xl font-semibold">{{ __('The home page is empty') }}</h2>
                <p class="mt-1 text-sm text-ink-soft">{{ __('Add a section above — buyers see it the moment it\'s active.') }}</p>
            </div>
        @else
            <ul class="divide-y divide-line">
                @foreach ($sections as $section)
                    <li wire:key="section-{{ $section->id }}">
                        <div class="flex flex-wrap items-center gap-2 px-3 py-2">
                            {{-- Reorder --}}
                            <div class="flex items-center gap-0.5">
                                <button type="button" wire:click="move({{ $section->id }}, -1)" @disabled($loop->first)
                                        aria-label="{{ __('Move up') }}"
                                        class="inline-flex size-11 items-center justify-center rounded-lg text-ink-soft hover:text-ink disabled:opacity-30 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5"/></svg>
                                </button>
                                <button type="button" wire:click="move({{ $section->id }}, 1)" @disabled($loop->last)
                                        aria-label="{{ __('Move down') }}"
                                        class="inline-flex size-11 items-center justify-center rounded-lg text-ink-soft hover:text-ink disabled:opacity-30 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                                </button>
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="text-[13px] font-semibold text-ink">{{ \App\Livewire\Admin\Content\HomeSections::typeLabel($section->type) }}</p>
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
                                    class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                <span class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-150 {{ $section->is_active ? 'bg-emerald' : 'bg-line-strong' }}">
                                    <span class="inline-block size-4 rounded-full bg-white transition-transform duration-150 {{ $section->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                </span>
                            </button>

                            <button type="button" wire:click="{{ $editingId === $section->id ? 'cancel' : 'edit('.$section->id.')' }}"
                                    class="inline-flex min-h-11 items-center rounded-lg px-2 text-[13px] font-medium text-ink-soft hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                {{ $editingId === $section->id ? __('Close') : __('Edit') }}
                            </button>
                            <button type="button" wire:click="delete({{ $section->id }})"
                                    wire:confirm="{{ __('Remove this section from the home page?') }}"
                                    class="inline-flex min-h-11 items-center rounded-lg px-2 text-[13px] font-medium text-danger hover:bg-danger-tint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                {{ __('Remove') }}
                            </button>
                        </div>

                        {{-- Inline editor --}}
                        @if ($editingId === $section->id)
                            <form wire:submit="save" class="space-y-4 border-t border-line bg-paper px-4 py-4">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <x-ui.input :label="__('Heading (English)')" wire:model="title.en" :error="$errors->first('title.en')" :hint="__('Optional — leave blank for no heading.')" />
                                    <x-ui.input :label="__('Heading (Bahasa Melayu)')" wire:model="title.ms" :error="$errors->first('title.ms')" :hint="__('Optional — English is shown when empty.')" />
                                </div>

                                @if (in_array($section->type, ['product_carousel', 'product_grid'], true))
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label for="section-source" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Source') }}</label>
                                            <select id="section-source" wire:model="source"
                                                    class="block min-h-11 w-full rounded-lg border border-line-strong bg-surface px-3 py-2 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
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
