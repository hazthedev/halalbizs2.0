<div class="space-y-4">

    <x-ui.section-heading :title="__('Help articles')" as="h1">
        @unless ($showForm)
            <x-slot:actions>
                <x-ui.button wire:click="create">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    {{ __('Add article') }}
                </x-ui.button>
            </x-slot:actions>
        @endunless
    </x-ui.section-heading>

    {{-- Editor --}}
    @if ($showForm)
        <x-ui.card class="p-4">
            <form wire:submit="save" class="space-y-4">
                <h2 class="font-display text-lg font-semibold">
                    {{ $editingId !== null ? __('Edit article') : __('New article') }}
                </h2>

                <div class="flex flex-wrap items-end gap-4">
                    <div>
                        <label for="article-category" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Category') }}</label>
                        <select id="article-category" wire:model="category"
                                class="block min-h-11 w-48 rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 py-2.5 text-sm text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                            @foreach ($categories as $categoryOption)
                                <option value="{{ $categoryOption->value }}">{{ $categoryOption->label() }}</option>
                            @endforeach
                        </select>
                        @error('category')<p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>@enderror
                    </div>
                    <x-ui.input :label="__('Position')" type="number" min="0" wire:model="position"
                                :error="$errors->first('position')" class="w-28" />
                </div>

                {{-- EN / BM tabs for title + body --}}
                <div x-data="{ tab: 'en' }">
                    <div class="flex gap-1 border-b border-line" role="tablist">
                        <button type="button" role="tab" x-on:click="tab = 'en'"
                                x-bind:aria-selected="tab === 'en' ? 'true' : 'false'"
                                x-bind:class="tab === 'en' ? 'border-ink text-ink' : 'border-transparent text-ink-soft hover:text-ink'"
                                class="min-h-11 border-b-2 px-3 text-[13px] font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                            {{ __('English') }}
                        </button>
                        <button type="button" role="tab" x-on:click="tab = 'ms'"
                                x-bind:aria-selected="tab === 'ms' ? 'true' : 'false'"
                                x-bind:class="tab === 'ms' ? 'border-ink text-ink' : 'border-transparent text-ink-soft hover:text-ink'"
                                class="min-h-11 border-b-2 px-3 text-[13px] font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                            {{ __('Bahasa Melayu') }}
                        </button>
                    </div>

                    <div class="space-y-4 pt-3">
                        <div x-show="tab === 'en'" class="space-y-4">
                            <x-ui.input :label="__('Title (English)')" wire:model="title.en" :error="$errors->first('title.en')" />
                            <div>
                                <label for="article-body-en" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Body (English)') }}</label>
                                <textarea id="article-body-en" wire:model="body.en" rows="10"
                                          class="block w-full rounded-[var(--radius-control)] border bg-surface px-3.5 py-2.5 font-mono text-[13px] text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('body.en') ? 'border-danger' : 'border-line-strong' }}"></textarea>
                                @error('body.en')
                                    <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
                                @else
                                    <p class="mt-1.5 text-[13px] text-ink-faint">{{ __('HTML — allowed tags: p, br, h2, h3, ul, ol, li, strong, em, a. Everything else is stripped on save.') }}</p>
                                @enderror
                            </div>
                        </div>
                        <div x-show="tab === 'ms'" x-cloak class="space-y-4">
                            <x-ui.input :label="__('Title (Bahasa Melayu)')" wire:model="title.ms" :error="$errors->first('title.ms')" :hint="__('Optional — English is shown when empty.')" />
                            <div>
                                <label for="article-body-ms" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Body (Bahasa Melayu)') }}</label>
                                <textarea id="article-body-ms" wire:model="body.ms" rows="10"
                                          class="block w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3.5 py-2.5 font-mono text-[13px] text-ink placeholder:text-ink-faint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald"></textarea>
                                @error('body.ms')<p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-[13px] font-medium text-ink">
                    <input type="checkbox" wire:model="isActive" class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                    {{ __('Published') }}
                </label>

                <div class="flex items-center gap-2">
                    <x-ui.button type="submit" wire:loading.attr="disabled">{{ $editingId !== null ? __('Save article') : __('Create article') }}</x-ui.button>
                    <x-ui.button variant="ghost" wire:click="cancel">{{ __('Cancel') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif

    {{-- List --}}
    <x-ui.card class="overflow-x-auto">
        @if ($articles->isEmpty())
            <x-ui.empty-state :title="__('No articles yet')" :message="__('Run the seeder for the starter set, or add one above.')" />
        @else
            <table class="w-full min-w-[720px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Category') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Title') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Position') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Views') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Published') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($articles as $article)
                        <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="article-{{ $article->id }}">
                            <td class="px-3 py-2">
                                <x-ui.badge variant="neutral">{{ $article->category->label() }}</x-ui.badge>
                            </td>
                            <td class="px-3 py-2 font-medium text-ink">{{ $article->getTranslation('title', 'en') }}</td>
                            <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $article->position }}</td>
                            <td class="px-3 py-2 text-right font-mono tabular-nums">{{ number_format($article->views) }}</td>
                            <td class="px-3 py-2">
                                <button type="button" role="switch" aria-checked="{{ $article->is_active ? 'true' : 'false' }}"
                                        wire:click="toggleActive({{ $article->id }})"
                                        aria-label="{{ __('Toggle :title', ['title' => $article->getTranslation('title', 'en')]) }}"
                                        class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                    <span class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-150 {{ $article->is_active ? 'bg-emerald' : 'bg-line-strong' }}">
                                        <span class="inline-block size-4 rounded-full bg-white transition-transform duration-150 {{ $article->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                    </span>
                                </button>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" wire:click="edit({{ $article->id }})"
                                            class="inline-flex min-h-11 items-center rounded-lg px-2 font-medium text-ink-soft hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Edit') }}</button>
                                    <button type="button" wire:click="delete({{ $article->id }})"
                                            wire:confirm="{{ __('Delete this article? This cannot be undone.') }}"
                                            class="inline-flex min-h-11 items-center rounded-lg px-2 font-medium text-danger hover:bg-danger-tint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Delete') }}</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>
</div>
