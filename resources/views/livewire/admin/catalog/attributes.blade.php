<div class="space-y-4">

    {{-- Header --}}
    <h1 class="font-display text-2xl font-bold">{{ __('Attributes') }}</h1>

    {{-- Inline create form --}}
    <x-ui.card class="p-4">
        <form wire:submit="create" class="flex flex-wrap items-end gap-3">
            <x-ui.input class="min-w-48 flex-1" :label="__('Name (English)')" wire:model="name.en" :error="$errors->first('name.en')" />
            <x-ui.input class="min-w-48 flex-1" :label="__('Name (Bahasa Melayu)')" wire:model="name.ms" :error="$errors->first('name.ms')" placeholder="{{ __('Optional') }}" />
            <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-[13px] font-medium text-ink">
                <input type="checkbox" wire:model="isFilterable" class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                {{ __('Filterable') }}
            </label>
            <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="create">{{ __('Add attribute') }}</x-ui.button>
        </form>
    </x-ui.card>

    {{-- List --}}
    <x-ui.card class="overflow-x-auto">
        @if ($attributeList->isEmpty())
            <div class="px-6 py-16 text-center">
                <h2 class="font-display text-xl font-semibold">{{ __('No attributes yet') }}</h2>
                <p class="mt-1 text-sm text-ink-soft">{{ __('Attributes power the storefront filters — Material, Colour, Size.') }}</p>
            </div>
        @else
            <table class="w-full min-w-[640px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Name') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Slug') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Filterable') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Values') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($attributeList as $attribute)
                        <tr class="border-b border-line last:border-b-0 hover:bg-paper {{ $managingId === $attribute->id ? 'bg-paper' : '' }}" wire:key="attribute-{{ $attribute->id }}">
                            <td class="px-3 py-2">
                                @if ($editingId === $attribute->id)
                                    <form wire:submit="update" class="flex flex-wrap items-center gap-2">
                                        <x-ui.input wire:model="editName.en" :error="$errors->first('editName.en')" aria-label="{{ __('Name (English)') }}" />
                                        <x-ui.input wire:model="editName.ms" :error="$errors->first('editName.ms')" placeholder="{{ __('BM (optional)') }}" aria-label="{{ __('Name (Bahasa Melayu)') }}" />
                                        <button type="submit" class="inline-flex min-h-11 items-center rounded-lg px-2 font-semibold text-emerald hover:text-emerald-deep focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Save') }}</button>
                                        <button type="button" wire:click="cancelEdit" class="inline-flex min-h-11 items-center rounded-lg px-2 font-medium text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Cancel') }}</button>
                                    </form>
                                @else
                                    <span class="font-medium text-ink">{{ $attribute->getTranslation('name', 'en') }}</span>
                                    @if ($attribute->getTranslation('name', 'ms', false))
                                        <span class="text-ink-faint"> · {{ $attribute->getTranslation('name', 'ms', false) }}</span>
                                    @endif
                                @endif
                            </td>
                            <td class="px-3 py-2 font-mono text-[12px] text-ink-soft">{{ $attribute->slug }}</td>
                            <td class="px-3 py-2">
                                <button type="button" wire:click="toggleFilterable({{ $attribute->id }})" role="switch" aria-checked="{{ $attribute->is_filterable ? 'true' : 'false' }}"
                                        class="flex min-h-11 items-center px-1 focus-visible:ring-2 focus-visible:ring-emerald"
                                        aria-label="{{ __('Toggle filterable for :name', ['name' => $attribute->getTranslation('name', 'en')]) }}">
                                    <span class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors duration-150 {{ $attribute->is_filterable ? 'bg-emerald' : 'bg-line-strong' }}">
                                        <span class="inline-block size-4 transform rounded-full bg-surface transition-transform duration-150 {{ $attribute->is_filterable ? 'translate-x-[18px]' : 'translate-x-0.5' }}"></span>
                                    </span>
                                </button>
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums text-ink-soft">{{ $attribute->values_count }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" wire:click="manageValues({{ $attribute->id }})"
                                            class="inline-flex min-h-11 items-center rounded-lg px-2 font-medium {{ $managingId === $attribute->id ? 'text-emerald' : 'text-ink-soft hover:text-ink' }} focus-visible:ring-2 focus-visible:ring-emerald">
                                        {{ $managingId === $attribute->id ? __('Close values') : __('Values') }}
                                    </button>
                                    <button type="button" wire:click="edit({{ $attribute->id }})"
                                            class="inline-flex min-h-11 items-center rounded-lg px-2 font-medium text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Edit') }}</button>
                                    <button type="button" wire:click="deleteAttribute({{ $attribute->id }})"
                                            wire:confirm="{{ __('Delete this attribute? Its values and category mappings are removed too.') }}"
                                            class="inline-flex min-h-11 items-center rounded-lg px-2 font-medium text-danger hover:bg-danger-tint focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Delete') }}</button>
                                </div>
                            </td>
                        </tr>

                        {{-- Values panel --}}
                        @if ($managingId === $attribute->id)
                            <tr class="border-b border-line bg-paper" wire:key="attribute-values-{{ $attribute->id }}">
                                <td colspan="5" class="px-3 py-3">
                                    <div class="max-w-xl space-y-2 rounded-lg border border-line bg-surface p-3">
                                        <p class="text-[13px] font-semibold text-ink">{{ __('Values for :name', ['name' => $attribute->getTranslation('name', 'en')]) }}</p>

                                        @forelse ($managedValues as $value)
                                            <div class="flex items-center gap-2" wire:key="value-{{ $value->id }}">
                                                <span class="flex-1 text-[13px] text-ink">
                                                    {{ $value->getTranslation('value', 'en') }}
                                                    @if ($value->getTranslation('value', 'ms', false))
                                                        <span class="text-ink-faint"> · {{ $value->getTranslation('value', 'ms', false) }}</span>
                                                    @endif
                                                </span>
                                                <button type="button" wire:click="moveValue({{ $value->id }}, -1)" @disabled($loop->first)
                                                        class="flex size-11 items-center justify-center rounded-lg text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald disabled:cursor-not-allowed disabled:opacity-40"
                                                        aria-label="{{ __('Move :value up', ['value' => $value->getTranslation('value', 'en')]) }}">
                                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5"/></svg>
                                                </button>
                                                <button type="button" wire:click="moveValue({{ $value->id }}, 1)" @disabled($loop->last)
                                                        class="flex size-11 items-center justify-center rounded-lg text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald disabled:cursor-not-allowed disabled:opacity-40"
                                                        aria-label="{{ __('Move :value down', ['value' => $value->getTranslation('value', 'en')]) }}">
                                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                                                </button>
                                                <button type="button" wire:click="removeValue({{ $value->id }})"
                                                        wire:confirm="{{ __('Remove this value?') }}"
                                                        class="inline-flex min-h-11 items-center rounded-lg px-2 text-[13px] font-medium text-danger hover:bg-danger-tint focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Remove') }}</button>
                                            </div>
                                        @empty
                                            <p class="text-[13px] text-ink-faint">{{ __('No values yet.') }}</p>
                                        @endforelse

                                        <form wire:submit="addValue" class="flex flex-wrap items-end gap-2 border-t border-line pt-3">
                                            <x-ui.input class="min-w-36 flex-1" :label="__('Value (English)')" wire:model="valueDraft.en" :error="$errors->first('valueDraft.en')" />
                                            <x-ui.input class="min-w-36 flex-1" :label="__('Value (Bahasa Melayu)')" wire:model="valueDraft.ms" :error="$errors->first('valueDraft.ms')" placeholder="{{ __('Optional') }}" />
                                            <button type="submit" class="inline-flex min-h-11 items-center rounded-lg border border-ink px-3 text-[13px] font-semibold text-ink hover:bg-paper focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Add value') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>
</div>
