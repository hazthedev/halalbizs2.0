<div class="space-y-4">

    {{-- Header --}}
    <x-ui.section-heading :title="__('Categories')" as="h1">
        <x-slot:actions>
            <x-ui.button wire:click="startCreate">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                {{ __('Add root category') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.section-heading>

    @error('parent')
        <div class="rounded-[var(--radius-card)] border border-danger bg-danger-tint px-4 py-3 text-[13px] text-danger">{{ $message }}</div>
    @enderror

    {{-- Tree --}}
    <x-ui.card>
        @php $roots = $byParent->get(0, collect()); @endphp

        @if ($roots->isEmpty())
            <x-ui.empty-state :title="__('No categories yet')" :message="__('Build the catalog tree — three levels deep at most.')">
                <x-ui.button wire:click="startCreate">{{ __('Add root category') }}</x-ui.button>
            </x-ui.empty-state>
        @else
            <div class="divide-y divide-line">
                @foreach ($roots as $category)
                    @include('livewire.admin.catalog.partials.category-node', [
                        'category' => $category,
                        'depth' => 1,
                        'byParent' => $byParent,
                        'isFirst' => $loop->first,
                        'isLast' => $loop->last,
                    ])
                @endforeach
            </div>
        @endif
    </x-ui.card>

    {{-- Edit / create panel --}}
    @if ($formOpen)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-ink/40 p-4 sm:p-8" wire:click.self="cancel">
            <x-ui.card class="w-full max-w-2xl shadow-pop" x-data="{ tab: 'en' }">
                <form wire:submit="save">
                    <div class="flex items-center justify-between border-b border-line px-5 py-4">
                        <h2 class="font-display text-lg font-semibold">
                            @if ($editingId !== null)
                                {{ __('Edit category') }}
                            @elseif ($parentName !== null)
                                {{ __('Add child of :name', ['name' => $parentName]) }}
                            @else
                                {{ __('Add root category') }}
                            @endif
                        </h2>
                        <button type="button" wire:click="cancel" class="flex size-11 items-center justify-center rounded-[var(--radius-control)] text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald" aria-label="{{ __('Close') }}">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="space-y-4 px-5 py-4">
                        {{-- Language tabs --}}
                        <div class="flex gap-1 border-b border-line" role="tablist">
                            <button type="button" role="tab" x-on:click="tab = 'en'" :aria-selected="tab === 'en'"
                                    class="min-h-11 px-3 text-[13px] font-semibold focus-visible:ring-2 focus-visible:ring-emerald"
                                    :class="tab === 'en' ? 'border-b-2 border-ink text-ink' : 'text-ink-soft hover:text-ink'">
                                {{ __('English') }}
                            </button>
                            <button type="button" role="tab" x-on:click="tab = 'ms'" :aria-selected="tab === 'ms'"
                                    class="min-h-11 px-3 text-[13px] font-semibold focus-visible:ring-2 focus-visible:ring-emerald"
                                    :class="tab === 'ms' ? 'border-b-2 border-ink text-ink' : 'text-ink-soft hover:text-ink'">
                                {{ __('Bahasa Melayu') }}
                            </button>
                        </div>

                        <div x-show="tab === 'en'" class="space-y-4">
                            <x-ui.input :label="__('Name (English)')" wire:model="name.en" :error="$errors->first('name.en')" />
                            <div>
                                <label for="description-en" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Description (English)') }}</label>
                                <textarea id="description-en" wire:model="description.en" rows="3"
                                          class="block w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald"></textarea>
                                @error('description.en')<p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div x-show="tab === 'ms'" x-cloak class="space-y-4">
                            <x-ui.input :label="__('Name (Bahasa Melayu)')" wire:model="name.ms" :error="$errors->first('name.ms')" :hint="__('Optional — falls back to English when empty.')" />
                            <div>
                                <label for="description-ms" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Description (Bahasa Melayu)') }}</label>
                                <textarea id="description-ms" wire:model="description.ms" rows="3"
                                          class="block w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald"></textarea>
                                @error('description.ms')<p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.input :label="__('Commission rate (%)')" wire:model="commissionRate" inputmode="decimal" placeholder="—"
                                        :error="$errors->first('commissionRate')" :hint="__('Leave empty to inherit from the parent or the global rate.')" />

                            <div>
                                <span class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Image') }}</span>
                                <div class="flex items-center gap-3">
                                    @if ($image)
                                        <img src="{{ $image->temporaryUrl() }}" alt="{{ __('New category image') }}" class="size-11 rounded-[var(--radius-control)] border border-line bg-paper object-cover">
                                    @elseif ($editingImageUrl)
                                        <img src="{{ $editingImageUrl }}" alt="{{ __('Category image') }}" class="size-11 rounded-[var(--radius-control)] border border-line bg-paper object-cover">
                                    @endif
                                    <label class="inline-flex min-h-11 cursor-pointer items-center rounded-[var(--radius-control)] border border-ink px-3 text-[13px] font-semibold text-ink hover:bg-paper">
                                        <input type="file" wire:model="image" accept="image/*" class="sr-only">
                                        {{ ($image || $editingImageUrl) ? __('Replace image') : __('Upload image') }}
                                    </label>
                                    @if ($image || $editingImageUrl)
                                        <button type="button" wire:click="removeImage" class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-2 text-[13px] font-medium text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Remove') }}</button>
                                    @endif
                                </div>
                                <div wire:loading wire:target="image" class="mt-1.5 text-[13px] text-ink-faint">{{ __('Uploading…') }}</div>
                                @error('image')<p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-[13px] font-medium text-ink">
                            <input type="checkbox" wire:model="isActive" class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                            {{ __('Active — visible on the storefront') }}
                        </label>

                        {{-- Attribute mapping --}}
                        <div>
                            <span class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Attributes for this category') }}</span>
                            @if ($allAttributes->isEmpty())
                                <p class="text-[13px] text-ink-faint">{{ __('No attributes yet — create them under Catalog → Attributes.') }}</p>
                            @else
                                <div class="grid gap-1 rounded-[var(--radius-card)] border border-line p-3 sm:grid-cols-2">
                                    @foreach ($allAttributes as $attribute)
                                        <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-[var(--radius-control)] px-2 text-[13px] text-ink hover:bg-paper" wire:key="attr-option-{{ $attribute->id }}">
                                            <input type="checkbox" wire:model="selectedAttributeIds" value="{{ $attribute->id }}"
                                                   class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                                            {{ $attribute->getTranslation('name', 'en') }}
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                            @error('selectedAttributeIds.*')<p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>@enderror
                        </div>

                        @error('parent')<p class="text-[13px] text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-line px-5 py-4">
                        <button type="button" wire:click="cancel" class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-3 text-[13px] font-semibold text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Cancel') }}</button>
                        <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save, image">
                            {{ $editingId !== null ? __('Save changes') : __('Create category') }}
                        </x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        </div>
    @endif
</div>
