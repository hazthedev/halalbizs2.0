<div class="space-y-4">

    <x-ui.section-heading :title="__('Pages')" as="h1">
        @unless ($showForm)
            <x-slot:actions>
                <x-ui.button wire:click="create">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    {{ __('Add page') }}
                </x-ui.button>
            </x-slot:actions>
        @endunless
    </x-ui.section-heading>

    {{-- Editor --}}
    @if ($showForm)
        <x-ui.card class="p-4">
            <form wire:submit="save" class="space-y-4">
                <h2 class="font-display text-lg font-medium">
                    {{ $editingId !== null ? __('Edit page') : __('New page') }}
                </h2>

                @if ($editingIsSystem)
                    <div class="flex items-center gap-2">
                        <span class="rounded-[var(--radius-control)] bg-paper px-3 py-2 font-mono text-[13px] text-ink-soft">/{{ $slug }}</span>
                        <x-ui.badge variant="neutral">{{ __('System page — slug locked') }}</x-ui.badge>
                    </div>
                @else
                    <x-ui.input :label="__('Slug')" wire:model="slug" placeholder="shipping-guide" :error="$errors->first('slug')" :hint="__('Lowercase letters, numbers, and dashes — becomes the page URL.')" class="max-w-sm" />
                @endif

                {{-- EN / BM tabs for title + body --}}
                <div x-data="{ tab: 'en' }">
                    <div class="flex gap-1 border-b border-line" role="tablist">
                        <button type="button" role="tab" x-on:click="tab = 'en'"
                                x-bind:aria-selected="tab === 'en' ? 'true' : 'false'"
                                x-bind:class="tab === 'en' ? 'border-ink text-ink' : 'border-transparent text-ink-soft hover:text-ink'"
                                class="min-h-11 border-b-2 px-3 text-[13px] font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                            {{ __('English') }}
                        </button>
                        <button type="button" role="tab" x-on:click="tab = 'ms'"
                                x-bind:aria-selected="tab === 'ms' ? 'true' : 'false'"
                                x-bind:class="tab === 'ms' ? 'border-ink text-ink' : 'border-transparent text-ink-soft hover:text-ink'"
                                class="min-h-11 border-b-2 px-3 text-[13px] font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                            {{ __('Bahasa Melayu') }}
                        </button>
                        <button type="button" role="tab" x-on:click="tab = 'vi'"
                                x-bind:aria-selected="tab === 'vi' ? 'true' : 'false'"
                                x-bind:class="tab === 'vi' ? 'border-ink text-ink' : 'border-transparent text-ink-soft hover:text-ink'"
                                class="min-h-11 border-b-2 px-3 text-[13px] font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                            {{ __('Tiếng Việt') }}
                        </button>
                    </div>

                    <div class="space-y-4 pt-3">
                        <div x-show="tab === 'en'" class="space-y-4">
                            <x-ui.input :label="__('Title (English)')" wire:model="title.en" :error="$errors->first('title.en')" />
                            <x-ui.rich-editor model="body.en" id="body-en" :label="__('Body (English)')" :error="$errors->first('body.en')" />
                        </div>
                        <div x-show="tab === 'ms'" x-cloak class="space-y-4">
                            <x-ui.input :label="__('Title (Bahasa Melayu)')" wire:model="title.ms" :error="$errors->first('title.ms')" :hint="__('Optional — English is shown when empty.')" />
                            <x-ui.rich-editor model="body.ms" id="body-ms" :label="__('Body (Bahasa Melayu)')" :error="$errors->first('body.ms')" />
                        </div>
                        <div x-show="tab === 'vi'" x-cloak class="space-y-4">
                            <x-ui.input :label="__('Title (Vietnamese)')" wire:model="title.vi" :error="$errors->first('title.vi')" :hint="__('Optional — English is shown when empty.')" />
                            <x-ui.rich-editor model="body.vi" id="body-vi" :label="__('Body (Vietnamese)')" :error="$errors->first('body.vi')" />
                        </div>
                    </div>
                </div>

                {{-- Search engines and shares (item 17). All optional: empty falls back to the title and the page text. --}}
                <fieldset class="space-y-4 border-t border-line pt-4">
                    <legend class="font-display text-base font-medium">{{ __('Search and sharing') }}</legend>
                    <p class="text-[13px] text-ink-faint">{{ __('What Google and a shared link show. Leave empty to use the page title and the start of the page text.') }}</p>
                    <div class="grid gap-4 lg:grid-cols-3">
                        @foreach (['en' => __('English'), 'ms' => __('Bahasa Melayu'), 'vi' => __('Tiếng Việt')] as $code => $language)
                            <div class="space-y-3" wire:key="seo-{{ $code }}">
                                <x-ui.input :label="__('Search title').' ('.$language.')'" wire:model="seoTitle.{{ $code }}" maxlength="70" :error="$errors->first('seoTitle.'.$code)" />
                                <x-ui.input :label="__('Search description').' ('.$language.')'" wire:model="seoDescription.{{ $code }}" maxlength="200" :error="$errors->first('seoDescription.'.$code)" />
                            </div>
                        @endforeach
                    </div>
                    <div>
                        <label for="share-image" class="block text-[13px] font-medium text-ink">{{ __('Share picture') }}</label>
                        <input id="share-image" type="file" wire:model="shareImage" accept="image/*"
                               class="mt-1.5 block w-full max-w-md rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 py-2 text-[13px] text-ink file:mr-3 file:rounded-[var(--radius-control)] file:border-0 file:bg-paper file:px-3 file:py-1.5 file:text-[13px] file:font-medium">
                        @error('shareImage')
                            <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
                        @else
                            <p class="mt-1.5 text-[13px] text-ink-faint">{{ __('Wide picture, best at 1200 x 630, up to 4 MB. Shown when the page is shared.') }}</p>
                        @enderror
                        <div wire:loading wire:target="shareImage" class="mt-1.5 text-[13px] text-ink-soft">{{ __('Uploading…') }}</div>
                        @if ($shareImage)
                            <img src="{{ $shareImage->temporaryUrl() }}" alt="{{ __('Share picture preview') }}" class="mt-2 h-24 rounded-[var(--radius-card)] border border-line object-cover">
                        @elseif ($editingShareImage !== '')
                            <div class="mt-2 flex items-center gap-3">
                                <img src="{{ $editingShareImage }}" alt="{{ __('Share picture preview') }}" class="h-24 rounded-[var(--radius-card)] border border-line object-cover">
                                <button type="button" wire:click="removeShareImage" class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-2 text-[13px] font-medium text-danger hover:bg-danger-tint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Remove') }}</button>
                            </div>
                        @endif
                    </div>
                </fieldset>

                @unless ($editingIsSystem)
                    <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-[13px] font-medium text-ink">
                        <input type="checkbox" wire:model="showInFooter" class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                        {{ __('Show a link to this page in the website footer') }}
                    </label>
                @endunless

                @if (! in_array($slug, \App\Livewire\Admin\Content\Pages::ALWAYS_ACTIVE_SLUGS, true))
                    <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-[13px] font-medium text-ink">
                        <input type="checkbox" wire:model="isActive" class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                        {{ __('Published') }}
                    </label>
                @else
                    <p class="text-[13px] text-ink-faint">{{ __('Terms and privacy pages stay published — checkout links to them.') }}</p>
                @endif

                <div class="flex items-center gap-2">
                    <x-ui.button type="submit" wire:loading.attr="disabled">{{ $editingId !== null ? __('Save page') : __('Create page') }}</x-ui.button>
                    <x-ui.button variant="ghost" wire:click="cancel">{{ __('Cancel') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif

    {{-- List --}}
    {{-- While the form is open the list is put away, the same way the "Add"
         button above already is. It used to sit under the editor, pushing this
         page to 1871px against a 744px viewport and leaving you editing one
         record with every other one still on screen. Covers New as well as
         Edit — same clutter either way. --}}
    @unless ($showForm)
    <x-ui.card class="overflow-x-auto">
        @if ($pages->isEmpty())
            <x-ui.empty-state :title="__('No pages yet')" :message="__('Run the seeder to create the system pages, or add one above.')" />
        @else
            <table class="w-full min-w-[640px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Slug') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Title') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Published') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Updated') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pages as $page)
                        @php
                            $isSystem = in_array($page->slug, \App\Livewire\Admin\Content\Pages::SYSTEM_SLUGS, true);
                            $isLockedActive = in_array($page->slug, \App\Livewire\Admin\Content\Pages::ALWAYS_ACTIVE_SLUGS, true);
                        @endphp
                        <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="page-{{ $page->id }}">
                            <td class="px-3 py-2">
                                <span class="font-mono text-[12px]">/{{ $page->slug }}</span>
                                @if ($isSystem)
                                    <x-ui.badge variant="neutral" class="ml-1">{{ __('System') }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-3 py-2 font-medium text-ink">
                                {{ $page->getTranslation('title', 'en') }}
                                @if ($page->draft !== null)
                                    <x-ui.badge variant="warn" class="ml-1">{{ __('Draft') }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                <button type="button" role="switch" aria-checked="{{ $page->is_active ? 'true' : 'false' }}"
                                        wire:click="toggleActive({{ $page->id }})" @disabled($isLockedActive)
                                        aria-label="{{ __('Toggle :title', ['title' => $page->getTranslation('title', 'en')]) }}"
                                        class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-[var(--radius-control)] focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald disabled:cursor-not-allowed disabled:opacity-50">
                                    <span class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-150 {{ $page->is_active ? 'bg-emerald' : 'bg-line-strong' }}">
                                        <span class="inline-block size-4 rounded-full bg-white transition-transform duration-150 {{ $page->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                    </span>
                                </button>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-[12px] text-ink-soft">{{ $page->updated_at?->diffForHumans() }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center justify-end gap-1">
                                    <x-ui.draft-actions type="page" :id="$page->id" :draft="$page->draft" :preview-path="'/page/'.$page->slug" />
                                    <button type="button" wire:click="edit({{ $page->id }})"
                                            class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-2 font-medium text-ink-soft hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Edit') }}</button>
                                    @if (! $isSystem && auth()->user()->can('cms.publish'))
                                        <button type="button" wire:click="delete({{ $page->id }})"
                                                wire:confirm="{{ __('Delete this page? This cannot be undone.') }}"
                                                class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-2 font-medium text-danger hover:bg-danger-tint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Delete') }}</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>
    @endunless
</div>
