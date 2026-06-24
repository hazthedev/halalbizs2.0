<div class="mx-auto max-w-4xl space-y-4">

    {{-- Header --}}
    <div>
        <a href="{{ route('seller.products.index') }}" wire:navigate class="text-[13px] font-medium text-ink-soft hover:text-ink">← {{ __('Products') }}</a>
        <x-ui.section-heading as="h1" :title="$editing ? __('Edit product') : __('Add product')" class="mt-1" />
    </div>

    @if ($requireApproval)
        <div class="rounded-[var(--radius-card)] border border-line bg-warn-tint px-4 py-3 text-[13px] text-warn">
            {{ __('Product approval is on — published products are reviewed by the marketplace team before they go live.') }}
        </div>
    @endif

    <form wire:submit="publish" class="space-y-4">

        {{-- ── Basics ─────────────────────────────────────────────── --}}
        <x-ui.card class="p-5" x-data="{ lang: 'en' }">
            <h2 class="font-display text-lg font-semibold">{{ __('Basics') }}</h2>

            {{-- Language tabs --}}
            <div class="mt-3 flex gap-1 border-b border-line" role="tablist" aria-label="{{ __('Content language') }}">
                <button type="button" role="tab" x-on:click="lang = 'en'"
                        x-bind:aria-selected="lang === 'en'"
                        x-bind:class="lang === 'en' ? 'border-ink text-ink' : 'border-transparent text-ink-soft hover:text-ink'"
                        class="-mb-px min-h-11 border-b-2 px-4 text-sm font-semibold focus-visible:ring-2 focus-visible:ring-emerald">
                    EN
                </button>
                <button type="button" role="tab" x-on:click="lang = 'ms'"
                        x-bind:aria-selected="lang === 'ms'"
                        x-bind:class="lang === 'ms' ? 'border-ink text-ink' : 'border-transparent text-ink-soft hover:text-ink'"
                        class="-mb-px min-h-11 border-b-2 px-4 text-sm font-semibold focus-visible:ring-2 focus-visible:ring-emerald">
                    BM
                </button>
            </div>

            <div x-show="lang === 'en'" class="mt-4 space-y-4">
                <x-ui.input
                    :label="__('Product name (English)')"
                    name="name.en"
                    wire:model="name.en"
                    required
                    maxlength="255"
                    :error="$errors->first('name.en')"
                />
                <div>
                    <div class="mb-1.5 flex items-center justify-between gap-2">
                        <label for="description.en" class="block text-[13px] font-medium text-ink">{{ __('Description (English)') }}</label>
                        <button type="button" wire:click="generateCopy" wire:loading.attr="disabled" wire:target="generateCopy"
                                class="inline-flex items-center gap-1 rounded-lg border border-brass/40 px-2.5 py-1 text-[12px] font-semibold text-brass hover:bg-brass-tint/40">
                            <span wire:loading.remove wire:target="generateCopy">{{ __('Generate with AI') }}</span>
                            <span wire:loading wire:target="generateCopy">{{ __('Generating…') }}</span>
                        </button>
                    </div>
                    <textarea id="description.en" wire:model="description.en" rows="6"
                              class="block w-full rounded-lg border border-line-strong bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald"
                              placeholder="{{ __('What is it, what is it made of, how big is it…') }}"></textarea>
                    @error('description.en')<p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>@enderror
                </div>
            </div>

            <div x-show="lang === 'ms'" x-cloak class="mt-4 space-y-4">
                <x-ui.input
                    :label="__('Product name (Malay)')"
                    name="name.ms"
                    wire:model="name.ms"
                    maxlength="255"
                    :hint="__('Optional — falls back to English when empty.')"
                    :error="$errors->first('name.ms')"
                />
                <div>
                    <label for="description.ms" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Description (Malay)') }}</label>
                    <textarea id="description.ms" wire:model="description.ms" rows="6"
                              class="block w-full rounded-lg border border-line-strong bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald"></textarea>
                    <p class="mt-1.5 text-[13px] text-ink-faint">{{ __('Optional — falls back to English when empty.') }}</p>
                </div>
            </div>

            {{-- Category cascader --}}
            <div class="mt-5">
                <span class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Category') }}</span>
                <div class="grid gap-2 sm:grid-cols-3">
                    <select wire:model.live="categoryTop" aria-label="{{ __('Top category') }}"
                            class="min-h-11 rounded-lg border border-line-strong bg-surface px-3 py-2 text-sm text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                        <option value="">{{ __('Choose…') }}</option>
                        @foreach ($topCategories as $top)
                            <option value="{{ $top->id }}">{{ $top->getTranslation('name', app()->getLocale()) }}</option>
                        @endforeach
                    </select>

                    @if ($childCategories->isNotEmpty())
                        <select wire:model.live="categoryChild" aria-label="{{ __('Sub-category') }}"
                                class="min-h-11 rounded-lg border border-line-strong bg-surface px-3 py-2 text-sm text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                            <option value="">{{ __('Choose…') }}</option>
                            @foreach ($childCategories as $child)
                                <option value="{{ $child->id }}">{{ $child->getTranslation('name', app()->getLocale()) }}</option>
                            @endforeach
                        </select>
                    @endif

                    @if ($leafCategories->isNotEmpty())
                        <select wire:model.live="categoryLeaf" aria-label="{{ __('Final category') }}"
                                class="min-h-11 rounded-lg border border-line-strong bg-surface px-3 py-2 text-sm text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                            <option value="">{{ __('Choose…') }}</option>
                            @foreach ($leafCategories as $leaf)
                                <option value="{{ $leaf->id }}">{{ $leaf->getTranslation('name', app()->getLocale()) }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
                @error('category')<p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>@enderror
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="brandId" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Brand (optional)') }}</label>
                    <select id="brandId" wire:model="brandId"
                            class="block min-h-11 w-full rounded-lg border border-line-strong bg-surface px-3 py-2 text-sm text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                        <option value="">{{ __('No brand') }}</option>
                        @foreach ($brands as $brand)
                            <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                        @endforeach
                    </select>
                    @error('brandId')<p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>@enderror
                </div>

                <fieldset>
                    <legend class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Condition') }}</legend>
                    <div class="flex gap-2">
                        @foreach ($conditions as $conditionCase)
                            <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium {{ $condition === $conditionCase->value ? 'border-emerald bg-emerald-tint text-emerald' : 'border-line-strong text-ink' }}">
                                <input type="radio" wire:model.live="condition" value="{{ $conditionCase->value }}" class="sr-only">
                                {{ $conditionCase->label() }}
                            </label>
                        @endforeach
                    </div>
                    @error('condition')<p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>@enderror
                </fieldset>

                <fieldset class="mt-4">
                    <legend class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Tax class') }}</legend>
                    <select wire:model="taxClass" class="min-h-11 w-full rounded-lg border border-line-strong bg-canvas px-3 text-sm text-ink focus:border-emerald focus:ring-emerald">
                        @foreach (\App\Enums\TaxClass::cases() as $taxCase)
                            <option value="{{ $taxCase->value }}">{{ $taxCase->label() }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[12px] text-ink-faint">{{ __('SST is only charged when your store is SST-registered.') }}</p>
                    @error('taxClass')<p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>@enderror
                </fieldset>

                @if ($this->availableAttributes()->isNotEmpty())
                    <fieldset class="mt-4">
                        <legend class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Attributes') }}</legend>
                        <p class="mb-2 text-[12px] text-ink-faint">{{ __('Tagging values helps buyers filter to your product.') }}</p>
                        <div class="space-y-3">
                            @foreach ($this->availableAttributes() as $facetAttribute)
                                <div>
                                    <p class="text-[12px] font-medium text-ink-soft">{{ $facetAttribute->getTranslation('name', app()->getLocale()) }}</p>
                                    <div class="mt-1 flex flex-wrap gap-1.5">
                                        @foreach ($facetAttribute->values as $facetValue)
                                            <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border px-3 py-1.5 text-[13px] {{ in_array($facetValue->id, $attributeValueIds) ? 'border-emerald bg-emerald-tint text-emerald' : 'border-line-strong text-ink' }}">
                                                <input type="checkbox" wire:model.live="attributeValueIds" value="{{ $facetValue->id }}" class="sr-only">
                                                {{ $facetValue->getTranslation('value', app()->getLocale()) }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </fieldset>
                @endif
            </div>
        </x-ui.card>

        {{-- ── Trust & details metafields (M2.7) ──────────────────── --}}
        @if (config('metafields.enabled', true))
            <x-ui.card class="p-5">
                <h2 class="font-display text-lg font-semibold">{{ __('Halal & product details') }}</h2>
                <p class="mt-1 text-[13px] text-ink-soft">{{ __('Optional trust signals shown to buyers — certification, ingredients, expiry.') }}</p>

                @foreach (config('metafields.groups', []) as $groupKey => $groupLabel)
                    @php($fields = collect(config('metafields.definitions', []))->filter(fn ($d) => ($d['group'] ?? 'details') === $groupKey))
                    @if ($fields->isNotEmpty())
                        <fieldset class="mt-4">
                            <legend class="text-[11px] font-semibold uppercase tracking-[0.06em] text-ink-faint">{{ __($groupLabel) }}</legend>
                            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                                @foreach ($fields as $key => $def)
                                    <div @class(['sm:col-span-2' => ($def['type'] ?? 'text') === 'textarea'])>
                                        <label for="mf-{{ $key }}" class="text-[13px] font-medium text-ink">{{ __($def['label']) }}</label>
                                        @if (($def['type'] ?? 'text') === 'textarea')
                                            <textarea id="mf-{{ $key }}" wire:model="metafields.{{ $key }}" rows="2" maxlength="2000"
                                                      class="mt-1 block w-full rounded-lg border border-line-strong bg-canvas px-3 py-2 text-sm text-ink focus:border-emerald focus:ring-emerald"></textarea>
                                        @else
                                            <input id="mf-{{ $key }}" type="{{ ($def['type'] ?? 'text') === 'date' ? 'date' : 'text' }}"
                                                   wire:model="metafields.{{ $key }}" maxlength="255"
                                                   class="mt-1 block min-h-11 w-full rounded-lg border border-line-strong bg-canvas px-3 text-sm text-ink focus:border-emerald focus:ring-emerald">
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>
                    @endif
                @endforeach
            </x-ui.card>
        @endif

        {{-- ── Images ─────────────────────────────────────────────── --}}
        <x-ui.card class="p-5">
            <h2 class="font-display text-lg font-semibold">{{ __('Images') }}</h2>
            <p class="mt-1 text-[13px] text-ink-soft">{{ __('Square (1:1) images look best. Up to :max images, 4MB each.', ['max' => $maxImages]) }}</p>

            <div class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-5">
                @foreach ($existingMedia as $index => $media)
                    <div class="group relative aspect-square overflow-hidden rounded-[var(--radius-card)] border border-line bg-paper" wire:key="media-{{ $media->id }}">
                        <img src="{{ $media->getUrl() }}" alt="{{ __('Product image :n', ['n' => $index + 1]) }}" class="size-full object-cover">
                        <div class="absolute inset-x-0 bottom-0 flex justify-between gap-1 bg-ink/70 p-1">
                            <button type="button" wire:click="moveMedia({{ $media->id }}, -1)" @disabled($loop->first) class="flex size-8 items-center justify-center rounded text-paper disabled:opacity-40" aria-label="{{ __('Move image earlier') }}">←</button>
                            <button type="button" wire:click="removeMedia({{ $media->id }})" class="flex size-8 items-center justify-center rounded text-paper hover:text-danger" aria-label="{{ __('Remove image') }}">✕</button>
                            <button type="button" wire:click="moveMedia({{ $media->id }}, 1)" @disabled($loop->last) class="flex size-8 items-center justify-center rounded text-paper disabled:opacity-40" aria-label="{{ __('Move image later') }}">→</button>
                        </div>
                    </div>
                @endforeach

                @foreach ($newImages as $index => $image)
                    <div class="group relative aspect-square overflow-hidden rounded-[var(--radius-card)] border border-line bg-paper" wire:key="new-image-{{ $index }}-{{ $image->getFilename() }}">
                        <img src="{{ $image->temporaryUrl() }}" alt="{{ __('New image :n', ['n' => $index + 1]) }}" class="size-full object-cover">
                        <div class="absolute inset-x-0 bottom-0 flex justify-between gap-1 bg-ink/70 p-1">
                            <button type="button" wire:click="moveNewImage({{ $index }}, -1)" @disabled($loop->first) class="flex size-8 items-center justify-center rounded text-paper disabled:opacity-40" aria-label="{{ __('Move image earlier') }}">←</button>
                            <button type="button" wire:click="removeNewImage({{ $index }})" class="flex size-8 items-center justify-center rounded text-paper hover:text-danger" aria-label="{{ __('Remove image') }}">✕</button>
                            <button type="button" wire:click="moveNewImage({{ $index }}, 1)" @disabled($loop->last) class="flex size-8 items-center justify-center rounded text-paper disabled:opacity-40" aria-label="{{ __('Move image later') }}">→</button>
                        </div>
                    </div>
                @endforeach

                @if ($existingMedia->count() + count($newImages) < $maxImages)
                    <label class="flex aspect-square cursor-pointer flex-col items-center justify-center gap-1 rounded-[var(--radius-card)] border border-dashed border-line-strong text-ink-soft hover:border-ink hover:text-ink">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        <span class="text-[12px] font-medium">{{ __('Add images') }}</span>
                        <input type="file" wire:model="newImages" multiple accept="image/*" class="sr-only">
                    </label>
                @endif
            </div>

            <div wire:loading wire:target="newImages" class="mt-2 text-[13px] text-ink-soft">{{ __('Uploading…') }}</div>
            @error('newImages')<p class="mt-2 text-[13px] text-danger">{{ $message }}</p>@enderror
            @error('newImages.*')<p class="mt-2 text-[13px] text-danger">{{ $message }}</p>@enderror

            {{-- ── Video (1 optional clip, shown in the PDP gallery) ──── --}}
            <div class="mt-6 border-t border-line pt-4">
                <h3 class="text-sm font-semibold text-ink">{{ __('Video') }}</h3>
                <p class="mt-1 text-[13px] text-ink-soft">{{ __('1 video, optional, max 30MB. MP4 or WebM — buyers play it from the product gallery.') }}</p>

                @if ($newVideo !== null)
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        @if ($newVideo->isPreviewable())
                            <video src="{{ $newVideo->temporaryUrl() }}" controls preload="metadata" class="h-28 rounded-lg border border-line bg-paper"></video>
                        @else
                            <span class="text-[13px] font-medium text-ink">{{ $newVideo->getClientOriginalName() }}</span>
                        @endif
                        <button type="button" wire:click="removeNewVideo"
                                class="inline-flex min-h-11 items-center rounded-lg px-2 text-[13px] font-medium text-danger hover:bg-danger-tint focus-visible:ring-2 focus-visible:ring-emerald">
                            {{ __('Remove video') }}
                        </button>
                    </div>
                @elseif ($existingVideo !== null)
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <video src="{{ $existingVideo->getUrl() }}" controls preload="metadata" class="h-28 rounded-lg border border-line bg-paper"></video>
                        <button type="button" wire:click="removeExistingVideo"
                                wire:confirm="{{ __('Remove this video from the product?') }}"
                                class="inline-flex min-h-11 items-center rounded-lg px-2 text-[13px] font-medium text-danger hover:bg-danger-tint focus-visible:ring-2 focus-visible:ring-emerald">
                            {{ __('Remove video') }}
                        </button>
                    </div>
                @else
                    <label class="mt-3 inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border border-dashed border-line-strong px-4 text-[13px] font-medium text-ink-soft hover:border-ink hover:text-ink">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
                        {{ __('Add a video') }}
                        <input type="file" wire:model="newVideo" accept="video/mp4,video/webm" class="sr-only">
                    </label>
                @endif

                <div wire:loading wire:target="newVideo" class="mt-2 text-[13px] text-ink-soft">{{ __('Uploading…') }}</div>
                @error('newVideo')<p class="mt-2 text-[13px] text-danger">{{ $message }}</p>@enderror
            </div>
        </x-ui.card>

        {{-- ── Variations ─────────────────────────────────────────── --}}
        <x-ui.card class="p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-display text-lg font-semibold">{{ __('Variations') }}</h2>
                    <p class="mt-1 text-[13px] text-ink-soft">{{ __('Sell one item, or build combinations like Colour × Size.') }}</p>
                </div>
                <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-sm font-medium text-ink">
                    <input type="checkbox" wire:model.live="hasVariations" class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                    {{ __('This product has variations') }}
                </label>
            </div>

            @error('matrix')
                <div class="mt-3 rounded-lg border border-danger bg-danger-tint px-3 py-2 text-[13px] text-danger">{{ $message }}</div>
            @enderror

            @unless ($hasVariations)
                {{-- Single-variant inputs --}}
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-ui.input
                        :label="__('Price (RM)')"
                        name="price"
                        wire:model="price"
                        inputmode="decimal"
                        placeholder="19.90"
                        required
                        :error="$errors->first('price')"
                    />
                    <x-ui.input
                        :label="__('Sale price (RM, optional)')"
                        name="salePrice"
                        wire:model="salePrice"
                        inputmode="decimal"
                        placeholder="15.90"
                        :error="$errors->first('salePrice')"
                    />
                    <x-ui.input
                        :label="__('Stock')"
                        name="stock"
                        wire:model="stock"
                        inputmode="numeric"
                        required
                        :error="$errors->first('stock')"
                    />
                    <x-ui.input
                        :label="__('SKU (optional)')"
                        name="sku"
                        wire:model="sku"
                        class="font-mono"
                        :error="$errors->first('sku')"
                    />
                </div>
            @else
                {{-- Option groups --}}
                <div class="mt-4 space-y-3">
                    @foreach ($optionGroups as $groupIndex => $group)
                        <div class="rounded-[var(--radius-card)] border border-line p-4" wire:key="option-group-{{ $groupIndex }}">
                            <div class="flex items-end gap-2">
                                <x-ui.input
                                    class="flex-1"
                                    :label="__('Option :n name', ['n' => $groupIndex + 1])"
                                    wire:model="optionGroups.{{ $groupIndex }}.name"
                                    :placeholder="$groupIndex === 0 ? __('e.g. Colour') : __('e.g. Size')"
                                    :error="$errors->first('optionGroups.'.$groupIndex.'.name')"
                                />
                                <button type="button" wire:click="removeOptionGroup({{ $groupIndex }})"
                                        class="inline-flex min-h-11 items-center rounded-lg px-3 text-[13px] font-medium text-ink-soft hover:text-danger focus-visible:ring-2 focus-visible:ring-emerald">
                                    {{ __('Remove') }}
                                </button>
                            </div>

                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                @foreach ($group['values'] as $valueIndex => $value)
                                    <span class="inline-flex items-center gap-1 rounded-full border border-line bg-paper py-1 pl-3 pr-1 text-[13px] font-medium text-ink" wire:key="chip-{{ $groupIndex }}-{{ $valueIndex }}">
                                        {{ $value }}
                                        <button type="button" wire:click="removeOptionValue({{ $groupIndex }}, {{ $valueIndex }})"
                                                class="flex size-6 items-center justify-center rounded-full text-ink-soft hover:text-danger"
                                                aria-label="{{ __('Remove :value', ['value' => $value]) }}">✕</button>
                                    </span>
                                @endforeach

                                <div class="flex items-center gap-1">
                                    <input
                                        type="text"
                                        wire:model="optionGroups.{{ $groupIndex }}.draft"
                                        wire:keydown.enter.prevent="addOptionValue({{ $groupIndex }})"
                                        placeholder="{{ __('Add value…') }}"
                                        aria-label="{{ __('New value for option :n', ['n' => $groupIndex + 1]) }}"
                                        class="min-h-11 w-36 rounded-lg border border-line-strong bg-surface px-3 py-1.5 text-[13px] text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald"
                                    >
                                    <button type="button" wire:click="addOptionValue({{ $groupIndex }})"
                                            class="inline-flex min-h-11 items-center rounded-lg border border-ink px-3 text-[13px] font-semibold text-ink hover:bg-paper focus-visible:ring-2 focus-visible:ring-emerald">
                                        {{ __('Add') }}
                                    </button>
                                </div>
                            </div>
                            @error('optionGroups.'.$groupIndex.'.values')<p class="mt-2 text-[13px] text-danger">{{ $message }}</p>@enderror
                        </div>
                    @endforeach

                    @if (count($optionGroups) < \App\Livewire\Seller\Products\Form::MAX_OPTION_GROUPS)
                        <button type="button" wire:click="addOptionGroup"
                                class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-ink px-4 text-sm font-semibold text-ink hover:bg-paper focus-visible:ring-2 focus-visible:ring-emerald">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            {{ __('Add a second option') }}
                        </button>
                    @endif
                    @error('optionGroups')<p class="text-[13px] text-danger">{{ $message }}</p>@enderror
                </div>

                {{-- Matrix table --}}
                @if ($matrix !== [])
                    <div class="mt-5 overflow-x-auto rounded-[var(--radius-card)] border border-line">
                        <table class="w-full min-w-[640px] text-[13px]">
                            <thead>
                                <tr class="border-b border-line bg-paper text-left text-ink-soft">
                                    <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Variant') }}</th>
                                    <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Price (RM)') }}</th>
                                    <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Sale (RM)') }}</th>
                                    <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Stock') }}</th>
                                    <th scope="col" class="px-3 py-2.5 font-medium">{{ __('SKU') }}</th>
                                    <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Image') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- Bulk-apply row --}}
                                <tr class="border-b border-line bg-paper/60">
                                    <td class="px-3 py-2 text-[12px] font-medium text-ink-soft">{{ __('Apply to all') }}</td>
                                    <td class="px-3 py-2">
                                        <div class="flex items-center gap-1">
                                            <input type="text" wire:model="bulkPrice" inputmode="decimal" placeholder="0.00" aria-label="{{ __('Price for all variants') }}"
                                                   class="min-h-11 w-20 rounded-lg border border-line-strong bg-surface px-2 py-1.5 text-right tabular-nums focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                            <button type="button" wire:click="applyPriceToAll" class="min-h-11 rounded-lg px-2 text-[12px] font-semibold text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Set') }}</button>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex items-center gap-1">
                                            <input type="text" wire:model="bulkSalePrice" inputmode="decimal" placeholder="0.00" aria-label="{{ __('Sale price for all variants') }}"
                                                   class="min-h-11 w-20 rounded-lg border border-line-strong bg-surface px-2 py-1.5 text-right tabular-nums focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                            <button type="button" wire:click="applySalePriceToAll" class="min-h-11 rounded-lg px-2 text-[12px] font-semibold text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Set') }}</button>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex items-center gap-1">
                                            <input type="text" wire:model="bulkStock" inputmode="numeric" placeholder="0" aria-label="{{ __('Stock for all variants') }}"
                                                   class="min-h-11 w-16 rounded-lg border border-line-strong bg-surface px-2 py-1.5 text-right tabular-nums focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                            <button type="button" wire:click="applyStockToAll" class="min-h-11 rounded-lg px-2 text-[12px] font-semibold text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Set') }}</button>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2"></td>
                                    <td class="px-3 py-2"></td>
                                </tr>

                                @foreach ($matrix as $key => $row)
                                    <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="matrix-{{ $key }}">
                                        <td class="px-3 py-2 font-medium text-ink">{{ $row['label'] }}</td>
                                        <td class="px-3 py-2">
                                            <input type="text" wire:model="matrix.{{ $key }}.price" inputmode="decimal" placeholder="0.00" aria-label="{{ __(':label price', ['label' => $row['label']]) }}"
                                                   class="min-h-11 w-24 rounded-lg border bg-surface px-2 py-1.5 text-right tabular-nums focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('matrix.'.$key.'.price') ? 'border-danger' : 'border-line-strong' }}">
                                            @error('matrix.'.$key.'.price')<p class="mt-1 text-[12px] text-danger">{{ $message }}</p>@enderror
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="text" wire:model="matrix.{{ $key }}.sale_price" inputmode="decimal" placeholder="—" aria-label="{{ __(':label sale price', ['label' => $row['label']]) }}"
                                                   class="min-h-11 w-24 rounded-lg border bg-surface px-2 py-1.5 text-right tabular-nums focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('matrix.'.$key.'.sale_price') ? 'border-danger' : 'border-line-strong' }}">
                                            @error('matrix.'.$key.'.sale_price')<p class="mt-1 text-[12px] text-danger">{{ $message }}</p>@enderror
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="text" wire:model="matrix.{{ $key }}.stock" inputmode="numeric" aria-label="{{ __(':label stock', ['label' => $row['label']]) }}"
                                                   class="min-h-11 w-16 rounded-lg border bg-surface px-2 py-1.5 text-right tabular-nums focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('matrix.'.$key.'.stock') ? 'border-danger' : 'border-line-strong' }}">
                                            @error('matrix.'.$key.'.stock')<p class="mt-1 text-[12px] text-danger">{{ $message }}</p>@enderror
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="text" wire:model="matrix.{{ $key }}.sku" aria-label="{{ __(':label SKU', ['label' => $row['label']]) }}"
                                                   class="min-h-11 w-28 rounded-lg border bg-surface px-2 py-1.5 font-mono text-[12px] focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('matrix.'.$key.'.sku') ? 'border-danger' : 'border-line-strong' }}">
                                            @error('matrix.'.$key.'.sku')<p class="mt-1 text-[12px] text-danger">{{ $message }}</p>@enderror
                                        </td>
                                        <td class="px-3 py-2">
                                            <label class="inline-flex min-h-11 cursor-pointer items-center gap-1 text-[12px] font-medium text-ink-soft hover:text-ink">
                                                @if (($matrixImages[$key] ?? null) !== null)
                                                    <img src="{{ $matrixImages[$key]->temporaryUrl() }}" alt="{{ $row['label'] }}" class="size-9 rounded border border-line object-cover">
                                                @else
                                                    <span class="flex size-9 items-center justify-center rounded border border-dashed border-line-strong">+</span>
                                                @endif
                                                <input type="file" wire:model="matrixImages.{{ $key }}" accept="image/*" class="sr-only">
                                            </label>
                                            @error('matrixImages.'.$key)<p class="mt-1 text-[12px] text-danger">{{ $message }}</p>@enderror
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endunless
        </x-ui.card>

        {{-- ── Sale schedule ──────────────────────────────────────── --}}
        <x-ui.card class="p-5">
            <h2 class="font-display text-lg font-semibold">{{ __('Sale schedule (optional)') }}</h2>
            <p class="mt-1 text-[13px] text-ink-soft">{{ __('Applies to every variant that has a sale price. Leave empty to run the sale immediately and indefinitely.') }}</p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <x-ui.input
                    :label="__('Sale starts')"
                    name="saleStartsAt"
                    type="datetime-local"
                    wire:model="saleStartsAt"
                    :error="$errors->first('saleStartsAt')"
                />
                <x-ui.input
                    :label="__('Sale ends')"
                    name="saleEndsAt"
                    type="datetime-local"
                    wire:model="saleEndsAt"
                    :error="$errors->first('saleEndsAt')"
                />
            </div>
        </x-ui.card>

        {{-- ── Shipping ───────────────────────────────────────────── --}}
        <x-ui.card class="p-5">
            <h2 class="font-display text-lg font-semibold">{{ __('Shipping') }}</h2>

            <div class="mt-4 grid gap-4 sm:grid-cols-4">
                <x-ui.input
                    :label="__('Weight (g)')"
                    name="weightGrams"
                    type="number"
                    min="0"
                    wire:model="weightGrams"
                    :error="$errors->first('weightGrams')"
                />
                <x-ui.input
                    :label="__('Length (mm)')"
                    name="lengthMm"
                    type="number"
                    min="0"
                    wire:model="lengthMm"
                    :error="$errors->first('lengthMm')"
                />
                <x-ui.input
                    :label="__('Width (mm)')"
                    name="widthMm"
                    type="number"
                    min="0"
                    wire:model="widthMm"
                    :error="$errors->first('widthMm')"
                />
                <x-ui.input
                    :label="__('Height (mm)')"
                    name="heightMm"
                    type="number"
                    min="0"
                    wire:model="heightMm"
                    :error="$errors->first('heightMm')"
                />
            </div>

            <label class="mt-4 inline-flex min-h-11 cursor-pointer items-center gap-2 text-sm font-medium text-ink">
                <input type="checkbox" wire:model="codEnabled" class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                {{ __('Allow cash on delivery (COD)') }}
            </label>
        </x-ui.card>

        {{-- ── Actions ────────────────────────────────────────────── --}}
        <div class="flex flex-wrap items-center justify-end gap-2">
            <button type="button" wire:click="saveDraft" wire:loading.attr="disabled"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-ink px-4 py-2.5 text-sm font-semibold text-ink hover:bg-paper disabled:cursor-not-allowed disabled:opacity-50 focus-visible:ring-2 focus-visible:ring-emerald">
                {{ __('Save as draft') }}
            </button>
            <x-ui.button type="submit" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="publish">{{ $requireApproval ? __('Submit for review') : __('Publish') }}</span>
                <span wire:loading wire:target="publish">{{ __('Publishing…') }}</span>
            </x-ui.button>
        </div>
    </form>
</div>
