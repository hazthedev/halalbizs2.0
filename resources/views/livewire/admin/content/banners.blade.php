<div class="space-y-4">

    <x-ui.section-heading :title="__('Banners')" as="h1">
        @unless ($showForm)
            <x-slot:actions>
                <x-ui.button wire:click="create">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    {{ __('Add banner') }}
                </x-ui.button>
            </x-slot:actions>
        @endunless
    </x-ui.section-heading>

    {{-- Create / edit form --}}
    @if ($showForm)
        <x-ui.card class="p-4">
            <form wire:submit="save" class="space-y-4">
                <h2 class="font-display text-lg font-medium">
                    {{ $editingId !== null ? __('Edit banner') : __('New banner') }}
                </h2>

                {{-- EN / BM title tabs --}}
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

                    <div class="pt-3">
                        <div x-show="tab === 'en'">
                            <x-ui.input :label="__('Title (English)')" wire:model="title.en" :error="$errors->first('title.en')" />
                        </div>
                        <div x-show="tab === 'ms'" x-cloak>
                            <x-ui.input :label="__('Title (Bahasa Melayu)')" wire:model="title.ms" :error="$errors->first('title.ms')" :hint="__('Optional — English is shown when empty.')" />
                        </div>
                        <div x-show="tab === 'vi'" x-cloak>
                            <x-ui.input :label="__('Title (Vietnamese)')" wire:model="title.vi" :error="$errors->first('title.vi')" :hint="__('Optional — English is shown when empty.')" />
                        </div>
                    </div>
                </div>

                {{-- Supporting line + call to action. A headline alone names a
                     category; these are what actually sell the marketplace. --}}
                <div class="grid gap-4 lg:grid-cols-3">
                    <x-ui.input :label="__('Supporting line (English)')" wire:model="subtitle.en" :error="$errors->first('subtitle.en')" :hint="__('Optional — one sentence under the headline.')" />
                    <x-ui.input :label="__('Supporting line (Bahasa Melayu)')" wire:model="subtitle.ms" :error="$errors->first('subtitle.ms')" :hint="__('Optional — English is shown when empty.')" />
                    <x-ui.input :label="__('Supporting line (Vietnamese)')" wire:model="subtitle.vi" :error="$errors->first('subtitle.vi')" :hint="__('Optional — English is shown when empty.')" />
                </div>

                <div class="grid gap-4 lg:grid-cols-3">
                    <x-ui.input :label="__('Button text (English)')" wire:model="ctaLabel.en" :error="$errors->first('ctaLabel.en')" :hint="__('Optional — e.g. Shop Drinks. Hidden when empty.')" />
                    <x-ui.input :label="__('Button text (Bahasa Melayu)')" wire:model="ctaLabel.ms" :error="$errors->first('ctaLabel.ms')" :hint="__('Optional — English is shown when empty.')" />
                    <x-ui.input :label="__('Button text (Vietnamese)')" wire:model="ctaLabel.vi" :error="$errors->first('ctaLabel.vi')" :hint="__('Optional — English is shown when empty.')" />
                </div>

                {{-- Where the banner goes: picked from a list so the same choice
                     opens on the website and in the phone app. --}}
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="banner-destination" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('When tapped, open') }}</label>
                        <select id="banner-destination" wire:model.live="destination"
                                class="block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 py-2 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                            <option value="none">{{ __('Nothing (no link)') }}</option>
                            <option value="product">{{ __('A product') }}</option>
                            <option value="store">{{ __('A store') }}</option>
                            <option value="category">{{ __('A category') }}</option>
                            <option value="flash_sale">{{ __('The flash sale') }}</option>
                            <option value="page">{{ __('A page (terms, FAQ…)') }}</option>
                            <option value="help">{{ __('The help centre') }}</option>
                            <option value="search">{{ __('Search results') }}</option>
                            <option value="custom">{{ __('A web address (website only)') }}</option>
                        </select>
                        <p class="mt-1.5 text-[13px] text-ink-faint">{{ __('Every choice except a web address also opens inside the phone app.') }}</p>
                    </div>

                    <div wire:key="destination-{{ $destination }}">
                        @if (in_array($destination, ['product', 'store'], true))
                            <x-ui.input :label="$destination === 'product' ? __('Find the product') : __('Find the store')"
                                        wire:model.live.debounce.300ms="destinationQuery" autocomplete="off"
                                        :placeholder="__('Type part of the name')"
                                        :error="$errors->first('destinationSlug')"
                                        :hint="$destinationSlug !== '' ? __('Picked. Type again to change it.') : __('Type at least 2 letters, then pick from the list.')" />
                            @if ($destinationMatches !== [])
                                <ul class="mt-1.5 divide-y divide-line overflow-hidden rounded-[var(--radius-control)] border border-line-strong bg-surface">
                                    @foreach ($destinationMatches as $slug => $name)
                                        <li wire:key="match-{{ $slug }}">
                                            <button type="button" wire:click="chooseDestination(@js($slug))"
                                                    class="block min-h-11 w-full px-3 py-2 text-left text-[13px] text-ink hover:bg-emerald-tint focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald">
                                                {{ $name }}
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @elseif ($destinationSlug === '' && mb_strlen(trim($destinationQuery)) >= 2)
                                <p class="mt-1.5 text-[13px] text-ink-soft">{{ __('Nothing live matches that name.') }}</p>
                            @endif
                        @elseif (in_array($destination, ['category', 'page'], true))
                            <label for="banner-destination-slug" class="mb-1.5 block text-[13px] font-medium text-ink">{{ $destination === 'category' ? __('Category') : __('Page') }}</label>
                            <select id="banner-destination-slug" wire:model="destinationSlug"
                                    class="block min-h-11 w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3 py-2 text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                <option value="">{{ __('Choose…') }}</option>
                                @foreach ($destination === 'category' ? $categoryOptions : $pageOptions as $slug => $name)
                                    <option value="{{ $slug }}">{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('destinationSlug')<p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>@enderror
                        @elseif ($destination === 'search')
                            <x-ui.input :label="__('Words to search for')" wire:model="destinationQuery" maxlength="80" placeholder="kurma ajwa" :error="$errors->first('destinationQuery')" />
                        @elseif ($destination === 'custom')
                            <x-ui.input :label="__('Web address')" wire:model="linkUrl" placeholder="https://…" :error="$errors->first('linkUrl')" :hint="__('The phone app cannot open this. The banner will not be tappable there.')" />
                        @endif
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input :label="__('Starts at')" type="datetime-local" wire:model="startsAt" :error="$errors->first('startsAt')" :hint="__('Optional — blank shows immediately.')" />
                    <x-ui.input :label="__('Ends at')" type="datetime-local" wire:model="endsAt" :error="$errors->first('endsAt')" :hint="__('Optional — blank never expires.')" />
                </div>

                <div>
                    <label for="banner-image" class="mb-1.5 block text-[13px] font-medium text-ink">
                        {{ $editingId !== null ? __('Replace image') : __('Image') }}
                    </label>
                    <input type="file" id="banner-image" wire:model="image" accept="image/*"
                           class="block w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3.5 py-2.5 text-[13px] text-ink file:mr-3 file:rounded-md file:border-0 file:bg-paper file:px-3 file:py-1.5 file:text-[13px] file:font-medium file:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                    @error('image')
                        <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
                    @else
                        <p class="mt-1.5 text-[13px] text-ink-faint">{{ __('JPG or PNG, up to 4 MB. Wide picture, 3 times as wide as tall, at least 1200 pixels wide (e.g. 1500 × 500).') }}</p>
                    @enderror
                    <div wire:loading wire:target="image" class="mt-1.5 text-[13px] text-ink-soft">{{ __('Uploading…') }}</div>
                    @if ($image)
                        <img src="{{ $image->temporaryUrl() }}" alt="{{ __('Banner preview') }}" class="mt-2 h-24 rounded-[var(--radius-card)] border border-line object-cover">
                    @endif
                </div>

                <div>
                    <label for="banner-image-vi" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Vietnamese image (optional)') }}</label>
                    <input type="file" id="banner-image-vi" wire:model="imageVi" accept="image/*"
                           class="block w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3.5 py-2.5 text-[13px] text-ink file:mr-3 file:rounded-md file:border-0 file:bg-paper file:px-3 file:py-1.5 file:text-[13px] file:font-medium file:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                    @error('imageVi')
                        <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
                    @else
                        <p class="mt-1.5 text-[13px] text-ink-faint">{{ __('Used for Vietnamese when copy is baked into the artwork. Falls back to the main image when empty.') }}</p>
                    @enderror
                    <div wire:loading wire:target="imageVi" class="mt-1.5 text-[13px] text-ink-soft">{{ __('Uploading…') }}</div>
                    @if ($imageVi)
                        <img src="{{ $imageVi->temporaryUrl() }}" alt="{{ __('Vietnamese banner preview') }}" class="mt-2 h-24 rounded-[var(--radius-card)] border border-line object-cover">
                    @elseif ($editing && ($viImage = $editing->getFirstMediaUrl('image_vi')))
                        <div class="mt-2 flex items-center gap-3">
                            <img src="{{ $viImage }}" alt="{{ __('Vietnamese banner preview') }}" class="h-24 rounded-[var(--radius-card)] border border-line object-cover">
                            <button type="button" wire:click="removeVietnameseImage" class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-2 text-[13px] font-medium text-danger hover:bg-danger-tint">{{ __('Remove') }}</button>
                        </div>
                    @endif
                </div>

                <div>
                    <label for="banner-video" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Video (optional)') }}</label>
                    <input type="file" id="banner-video" wire:model="video" accept="video/mp4,video/webm"
                           class="block w-full rounded-[var(--radius-control)] border border-line-strong bg-surface px-3.5 py-2.5 text-[13px] text-ink file:mr-3 file:rounded-md file:border-0 file:bg-paper file:px-3 file:py-1.5 file:text-[13px] file:font-medium file:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                    @error('video')
                        <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
                    @else
                        <p class="mt-1.5 text-[13px] text-ink-faint">{{ __('MP4 or WebM, up to 30 MB. Plays muted on loop in the home carousel; the image is the fallback.') }}</p>
                    @enderror
                    <div wire:loading wire:target="video" class="mt-1.5 text-[13px] text-ink-soft">{{ __('Uploading…') }}</div>
                    @if ($editing?->getFirstMedia('video') !== null)
                        <button type="button" wire:click="removeVideo"
                                wire:confirm="{{ __('Remove the video from this banner? It falls back to the image.') }}"
                                class="mt-2 inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-2 text-[13px] font-medium text-danger hover:bg-danger-tint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                            {{ __('Remove current video') }}
                        </button>
                    @endif
                </div>

                <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-[13px] font-medium text-ink">
                    <input type="checkbox" wire:model="isActive" class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                    {{ __('Active') }}
                </label>

                <div class="flex items-center gap-2">
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save, image, imageVi, video">
                        {{ $editingId !== null ? __('Save banner') : __('Create banner') }}
                    </x-ui.button>
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
        @if ($banners->isEmpty())
            <x-ui.empty-state :title="__('No banners yet')" :message="__('Banners appear in the storefront home carousel as soon as you add one.')" />
        @else
            <table class="w-full min-w-[760px] text-[13px]">
                <thead>
                    <tr class="border-b border-line text-left text-ink-soft">
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Order') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Banner') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Link') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Schedule') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">{{ __('Active') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($banners as $banner)
                        <tr class="border-b border-line last:border-b-0 hover:bg-paper" wire:key="banner-{{ $banner->id }}">
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-0.5">
                                    <button type="button" wire:click="move({{ $banner->id }}, -1)" @disabled($loop->first)
                                            aria-label="{{ __('Move up') }}"
                                            class="inline-flex size-11 items-center justify-center rounded-[var(--radius-control)] text-ink-soft hover:text-ink disabled:opacity-30 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5"/></svg>
                                    </button>
                                    <button type="button" wire:click="move({{ $banner->id }}, 1)" @disabled($loop->last)
                                            aria-label="{{ __('Move down') }}"
                                            class="inline-flex size-11 items-center justify-center rounded-[var(--radius-control)] text-ink-soft hover:text-ink disabled:opacity-30 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-3">
                                    @if ($url = $banner->getFirstMediaUrl('image'))
                                        <img src="{{ $url }}" alt="{{ $banner->getTranslation('title', 'en') }}" class="h-10 w-20 shrink-0 rounded-[var(--radius-control)] border border-line bg-paper object-cover">
                                    @else
                                        <span class="flex h-10 w-20 shrink-0 items-center justify-center rounded-[var(--radius-control)] border border-line bg-paper text-ink-faint">—</span>
                                    @endif
                                    <span class="font-medium text-ink">{{ $banner->getTranslation('title', 'en') }}</span>
                                    @if ($banner->draft)
                                        <x-ui.badge variant="neutral">{{ __('Draft') }}</x-ui.badge>
                                    @endif
                                </div>
                            </td>
                            <td class="max-w-44 truncate px-3 py-2 font-mono text-[12px] text-ink-soft">{{ $banner->link_url ?? '—' }}</td>
                            <td class="px-3 py-2 whitespace-nowrap text-[12px] text-ink-soft">
                                @if ($banner->starts_at === null && $banner->ends_at === null)
                                    {{ __('Always on') }}
                                @else
                                    {{ $banner->starts_at?->display()->format('d M Y H:i') ?? __('Now') }} → {{ $banner->ends_at?->display()->format('d M Y H:i') ?? __('No end') }}
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                <button type="button" role="switch" aria-checked="{{ $banner->is_active ? 'true' : 'false' }}"
                                        wire:click="toggleActive({{ $banner->id }})"
                                        aria-label="{{ __('Toggle :title', ['title' => $banner->getTranslation('title', 'en')]) }}"
                                        class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-[var(--radius-control)] focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                    <span class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-150 {{ $banner->is_active ? 'bg-emerald' : 'bg-line-strong' }}">
                                        <span class="inline-block size-4 rounded-full bg-white transition-transform duration-150 {{ $banner->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                    </span>
                                </button>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" wire:click="edit({{ $banner->id }})"
                                            class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-2 font-medium text-ink-soft hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Edit') }}</button>
                                    @if ($banner->draft)
                                        @can('cms.publish')
                                            <button type="button" wire:click="publishDraft('banner', {{ $banner->id }})"
                                                    class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-2 font-medium text-emerald hover:bg-paper focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Publish') }}</button>
                                            <button type="button" wire:click="discardDraft('banner', {{ $banner->id }})"
                                                    wire:confirm="{{ __('Throw away this draft? The live banner stays as it is.') }}"
                                                    class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-2 font-medium text-danger hover:bg-danger-tint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Throw away') }}</button>
                                        @endcan
                                    @endif
                                    <button type="button" wire:click="delete({{ $banner->id }})"
                                            wire:confirm="{{ __('Delete this banner? This cannot be undone.') }}"
                                            class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-2 font-medium text-danger hover:bg-danger-tint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Delete') }}</button>
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
