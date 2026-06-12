<div class="space-y-4">

    {{-- Header --}}
    <h1 class="font-display text-2xl font-bold">{{ __('Brands') }}</h1>

    {{-- Inline create form --}}
    <x-ui.card class="p-4">
        <form wire:submit="create" class="flex flex-wrap items-end gap-3">
            <x-ui.input class="min-w-64 flex-1" :label="__('Brand name')" wire:model="newName" :error="$errors->first('newName')" />
            <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="create">{{ __('Add brand') }}</x-ui.button>
        </form>
    </x-ui.card>

    {{-- List --}}
    <x-ui.card class="overflow-x-auto">
        @if ($brands->isEmpty())
            <div class="px-6 py-16 text-center">
                <h2 class="font-display text-xl font-semibold">{{ __('No brands yet') }}</h2>
                <p class="mt-1 text-sm text-ink-soft">{{ __('Brands are optional on products — add the ones sellers ask for.') }}</p>
            </div>
        @else
            <table class="w-full min-w-[560px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Name') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Slug') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Active') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Products') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($brands as $brand)
                        <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="brand-{{ $brand->id }}">
                            <td class="px-3 py-2">
                                @if ($editingId === $brand->id)
                                    <form wire:submit="update" class="flex flex-wrap items-center gap-2">
                                        <x-ui.input wire:model="editName" :error="$errors->first('editName')" aria-label="{{ __('Brand name') }}" />
                                        <button type="submit" class="inline-flex min-h-11 items-center rounded-lg px-2 font-semibold text-emerald hover:text-emerald-deep focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Save') }}</button>
                                        <button type="button" wire:click="cancelEdit" class="inline-flex min-h-11 items-center rounded-lg px-2 font-medium text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Cancel') }}</button>
                                    </form>
                                @else
                                    <span class="font-medium text-ink">{{ $brand->name }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 font-mono text-[12px] text-ink-soft">{{ $brand->slug }}</td>
                            <td class="px-3 py-2">
                                <button type="button" wire:click="toggleActive({{ $brand->id }})" role="switch" aria-checked="{{ $brand->is_active ? 'true' : 'false' }}"
                                        class="flex min-h-11 items-center px-1 focus-visible:ring-2 focus-visible:ring-emerald"
                                        aria-label="{{ __('Toggle active for :name', ['name' => $brand->name]) }}">
                                    <span class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors duration-150 {{ $brand->is_active ? 'bg-emerald' : 'bg-line-strong' }}">
                                        <span class="inline-block size-4 transform rounded-full bg-surface transition-transform duration-150 {{ $brand->is_active ? 'translate-x-[18px]' : 'translate-x-0.5' }}"></span>
                                    </span>
                                </button>
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums text-ink-soft">{{ $brand->products_count }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" wire:click="edit({{ $brand->id }})"
                                            class="inline-flex min-h-11 items-center rounded-lg px-2 font-medium text-ink-soft hover:text-ink focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Rename') }}</button>
                                    <button type="button" wire:click="delete({{ $brand->id }})"
                                            wire:confirm="{{ $brand->products_count > 0
                                                ? trans_choice('{1}Delete ":name"? :count product loses its brand link — it keeps selling without one.|[2,*]Delete ":name"? :count products lose their brand link — they keep selling without one.', $brand->products_count, ['name' => $brand->name, 'count' => $brand->products_count])
                                                : __('Delete ":name"? This cannot be undone.', ['name' => $brand->name]) }}"
                                            class="inline-flex min-h-11 items-center rounded-lg px-2 font-medium text-danger hover:bg-danger-tint focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Delete') }}</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>
</div>
