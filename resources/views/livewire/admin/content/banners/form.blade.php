<div class="space-y-4">
    <x-ui.section-heading as="h1" :title="$editingId !== null ? __('Edit banner') : __('New banner')" />
    <x-ui.card class="p-4">
        <form wire:submit="save" class="space-y-4">
            

            {{-- EN / BM / VI tabs: every translated field --}}
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
                    <div x-show="tab === 'en'" class="space-y-4">
                        <x-ui.input :label="__('Title (English)')" wire:model="title.en" :error="$errors->first('title.en')" />
                        <x-ui.input :label="__('Supporting line (English)')" wire:model="subtitle.en" :error="$errors->first('subtitle.en')" :hint="__('Optional — one sentence under the headline.')" />
                        <x-ui.input :label="__('Button text (English)')" wire:model="ctaLabel.en" :error="$errors->first('ctaLabel.en')" :hint="__('Optional — e.g. Shop Drinks. Hidden when empty.')" />
                    </div>
                    <div x-show="tab === 'ms'" x-cloak class="space-y-4">
                        <x-ui.input :label="__('Title (Bahasa Melayu)')" wire:model="title.ms" :error="$errors->first('title.ms')" :hint="__('Optional — English is shown when empty.')" />
                        <x-ui.input :label="__('Supporting line (Bahasa Melayu)')" wire:model="subtitle.ms" :error="$errors->first('subtitle.ms')" :hint="__('Optional — English is shown when empty.')" />
                        <x-ui.input :label="__('Button text (Bahasa Melayu)')" wire:model="ctaLabel.ms" :error="$errors->first('ctaLabel.ms')" :hint="__('Optional — English is shown when empty.')" />
                    </div>
                    <div x-show="tab === 'vi'" x-cloak class="space-y-4">
                        <x-ui.input :label="__('Title (Vietnamese)')" wire:model="title.vi" :error="$errors->first('title.vi')" :hint="__('Optional — English is shown when empty.')" />
                        <x-ui.input :label="__('Supporting line (Vietnamese)')" wire:model="subtitle.vi" :error="$errors->first('subtitle.vi')" :hint="__('Optional — English is shown when empty.')" />
                        <x-ui.input :label="__('Button text (Vietnamese)')" wire:model="ctaLabel.vi" :error="$errors->first('ctaLabel.vi')" :hint="__('Optional — English is shown when empty.')" />
                    </div>
                </div>
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
                @elseif ($editing && ($mainImage = $editing->getFirstMediaUrl('image', 'card')))
                    <img src="{{ $mainImage }}" alt="{{ __('Current banner image') }}" class="mt-2 h-24 rounded-[var(--radius-card)] border border-line object-cover">
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
                @elseif ($editing && ($viImage = $editing->getFirstMediaUrl('image_vi', 'card')))
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
                <x-ui.button variant="ghost" :href="route('admin.content.banners')">{{ __('Cancel') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</div>
