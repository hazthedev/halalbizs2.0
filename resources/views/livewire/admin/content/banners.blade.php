<div class="space-y-4">

    <div class="flex items-center justify-between gap-3">
        <h1 class="font-display text-2xl font-bold">{{ __('Banners') }}</h1>
        @unless ($showForm)
            <x-ui.button wire:click="create">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                {{ __('Add banner') }}
            </x-ui.button>
        @endunless
    </div>

    {{-- Create / edit form --}}
    @if ($showForm)
        <x-ui.card class="p-4">
            <form wire:submit="save" class="space-y-4">
                <h2 class="font-display text-lg font-semibold">
                    {{ $editingId !== null ? __('Edit banner') : __('New banner') }}
                </h2>

                {{-- EN / BM title tabs --}}
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

                    <div class="pt-3">
                        <div x-show="tab === 'en'">
                            <x-ui.input :label="__('Title (English)')" wire:model="title.en" :error="$errors->first('title.en')" />
                        </div>
                        <div x-show="tab === 'ms'" x-cloak>
                            <x-ui.input :label="__('Title (Bahasa Melayu)')" wire:model="title.ms" :error="$errors->first('title.ms')" :hint="__('Optional — English is shown when empty.')" />
                        </div>
                    </div>
                </div>

                <x-ui.input :label="__('Link URL')" wire:model="linkUrl" placeholder="/c/snacks" :error="$errors->first('linkUrl')" :hint="__('Optional — where the banner clicks through to.')" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input :label="__('Starts at')" type="datetime-local" wire:model="startsAt" :error="$errors->first('startsAt')" :hint="__('Optional — blank shows immediately.')" />
                    <x-ui.input :label="__('Ends at')" type="datetime-local" wire:model="endsAt" :error="$errors->first('endsAt')" :hint="__('Optional — blank never expires.')" />
                </div>

                <div>
                    <label for="banner-image" class="mb-1.5 block text-[13px] font-medium text-ink">
                        {{ $editingId !== null ? __('Replace image') : __('Image') }}
                    </label>
                    <input type="file" id="banner-image" wire:model="image" accept="image/*"
                           class="block w-full rounded-lg border border-line-strong bg-surface px-3.5 py-2.5 text-[13px] text-ink file:mr-3 file:rounded-md file:border-0 file:bg-paper file:px-3 file:py-1.5 file:text-[13px] file:font-medium file:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                    @error('image')
                        <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
                    @else
                        <p class="mt-1.5 text-[13px] text-ink-faint">{{ __('JPG or PNG, up to 4 MB. Wide images (e.g. 1280×400) look best.') }}</p>
                    @enderror
                    <div wire:loading wire:target="image" class="mt-1.5 text-[13px] text-ink-soft">{{ __('Uploading…') }}</div>
                    @if ($image)
                        <img src="{{ $image->temporaryUrl() }}" alt="{{ __('Banner preview') }}" class="mt-2 h-24 rounded-lg border border-line object-cover">
                    @endif
                </div>

                <div>
                    <label for="banner-video" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Video (optional)') }}</label>
                    <input type="file" id="banner-video" wire:model="video" accept="video/mp4,video/webm"
                           class="block w-full rounded-lg border border-line-strong bg-surface px-3.5 py-2.5 text-[13px] text-ink file:mr-3 file:rounded-md file:border-0 file:bg-paper file:px-3 file:py-1.5 file:text-[13px] file:font-medium file:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                    @error('video')
                        <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
                    @else
                        <p class="mt-1.5 text-[13px] text-ink-faint">{{ __('MP4 or WebM, up to 30 MB. Plays muted on loop in the home carousel; the image is the fallback.') }}</p>
                    @enderror
                    <div wire:loading wire:target="video" class="mt-1.5 text-[13px] text-ink-soft">{{ __('Uploading…') }}</div>
                    @if ($editingId !== null && \App\Models\Banner::find($editingId)?->getFirstMedia('video') !== null)
                        <button type="button" wire:click="removeVideo"
                                wire:confirm="{{ __('Remove the video from this banner? It falls back to the image.') }}"
                                class="mt-2 inline-flex min-h-11 items-center rounded-lg px-2 text-[13px] font-medium text-danger hover:bg-danger-tint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                            {{ __('Remove current video') }}
                        </button>
                    @endif
                </div>

                <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-[13px] font-medium text-ink">
                    <input type="checkbox" wire:model="isActive" class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                    {{ __('Active') }}
                </label>

                <div class="flex items-center gap-2">
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save, image, video">
                        {{ $editingId !== null ? __('Save banner') : __('Create banner') }}
                    </x-ui.button>
                    <x-ui.button variant="ghost" wire:click="cancel">{{ __('Cancel') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif

    {{-- List --}}
    <x-ui.card class="overflow-x-auto">
        @if ($banners->isEmpty())
            <div class="px-6 py-16 text-center">
                <h2 class="font-display text-xl font-semibold">{{ __('No banners yet') }}</h2>
                <p class="mt-1 text-sm text-ink-soft">{{ __('Banners appear in the storefront home carousel as soon as you add one.') }}</p>
            </div>
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
                                            class="inline-flex size-11 items-center justify-center rounded-lg text-ink-soft hover:text-ink disabled:opacity-30 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5"/></svg>
                                    </button>
                                    <button type="button" wire:click="move({{ $banner->id }}, 1)" @disabled($loop->last)
                                            aria-label="{{ __('Move down') }}"
                                            class="inline-flex size-11 items-center justify-center rounded-lg text-ink-soft hover:text-ink disabled:opacity-30 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-3">
                                    @if ($url = $banner->getFirstMediaUrl('image'))
                                        <img src="{{ $url }}" alt="{{ $banner->getTranslation('title', 'en') }}" class="h-10 w-20 shrink-0 rounded-lg border border-line bg-paper object-cover">
                                    @else
                                        <span class="flex h-10 w-20 shrink-0 items-center justify-center rounded-lg border border-line bg-paper text-ink-faint">—</span>
                                    @endif
                                    <span class="font-medium text-ink">{{ $banner->getTranslation('title', 'en') }}</span>
                                </div>
                            </td>
                            <td class="max-w-44 truncate px-3 py-2 font-mono text-[12px] text-ink-soft">{{ $banner->link_url ?? '—' }}</td>
                            <td class="px-3 py-2 whitespace-nowrap text-[12px] text-ink-soft">
                                @if ($banner->starts_at === null && $banner->ends_at === null)
                                    {{ __('Always on') }}
                                @else
                                    {{ $banner->starts_at?->format('d M Y H:i') ?? __('Now') }} → {{ $banner->ends_at?->format('d M Y H:i') ?? __('No end') }}
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                <button type="button" role="switch" aria-checked="{{ $banner->is_active ? 'true' : 'false' }}"
                                        wire:click="toggleActive({{ $banner->id }})"
                                        aria-label="{{ __('Toggle :title', ['title' => $banner->getTranslation('title', 'en')]) }}"
                                        class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                                    <span class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-150 {{ $banner->is_active ? 'bg-emerald' : 'bg-line-strong' }}">
                                        <span class="inline-block size-4 rounded-full bg-white transition-transform duration-150 {{ $banner->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                    </span>
                                </button>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" wire:click="edit({{ $banner->id }})"
                                            class="inline-flex min-h-11 items-center rounded-lg px-2 font-medium text-ink-soft hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">{{ __('Edit') }}</button>
                                    <button type="button" wire:click="delete({{ $banner->id }})"
                                            wire:confirm="{{ __('Delete this banner? This cannot be undone.') }}"
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
