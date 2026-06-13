<div class="max-w-3xl space-y-4">

    <x-ui.section-heading :title="__('Theme')" as="h1">
        <x-slot:actions>
            <x-ui.button variant="ghost" wire:click="resetDefaults"
                         wire:confirm="{{ __('Reset the occasion theme to defaults? The announcement, schedule, and hero image are cleared.') }}">
                {{ __('Reset to defaults') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.section-heading>

    {{-- Hard rule 8 note --}}
    <p class="rounded-[var(--radius-card)] border border-line bg-paper px-4 py-3 text-[13px] text-ink-soft">
        {{ __('Occasion colors style only the announcement bar and the home hero. Buttons, links, and prices keep the standard emerald — never recolor actions.') }}
    </p>

    <form wire:submit="save" class="space-y-4">

        <x-ui.card class="space-y-4 p-4">
            <h2 class="font-display text-lg font-semibold">{{ __('Occasion') }}</h2>
            <x-ui.input :label="__('Occasion name')" wire:model="occasion" :error="$errors->first('occasion')"
                        placeholder="{{ __('e.g. Hari Raya Aidilfitri') }}" :hint="__('Shown on the home hero. Leave empty for no label.')" class="max-w-sm" />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input :label="__('Starts at')" type="datetime-local" wire:model="startsAt" :error="$errors->first('startsAt')" :hint="__('Empty = always on.')" />
                <x-ui.input :label="__('Ends at')" type="datetime-local" wire:model="endsAt" :error="$errors->first('endsAt')" :hint="__('Empty = no end.')" />
            </div>
        </x-ui.card>

        <x-ui.card class="space-y-4 p-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-display text-lg font-semibold">{{ __('Announcement bar') }}</h2>
                <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-[13px] font-medium text-ink">
                    <input type="checkbox" wire:model="announcementEnabled" class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                    {{ __('Enabled') }}
                </label>
            </div>

            <x-ui.input :label="__('Text (English)')" wire:model="announcementTextEn" :error="$errors->first('announcementTextEn')"
                        placeholder="{{ __('e.g. Raya sale — free shipping over RM40') }}" />
            <x-ui.input :label="__('Text (Bahasa Melayu)')" wire:model="announcementTextMs" :error="$errors->first('announcementTextMs')"
                        :hint="__('Optional — English is shown when empty.')" />

            <div class="grid gap-4 sm:grid-cols-2">
                <div x-data="{ value: $wire.entangle('announcementBg') }">
                    <label for="announcement-bg-hex" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Bar background') }}</label>
                    <div class="flex items-center gap-2">
                        <input type="color" x-model="value" aria-label="{{ __('Bar background color picker') }}"
                               class="size-11 shrink-0 cursor-pointer rounded-[var(--radius-control)] border border-line-strong bg-surface p-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                        <input id="announcement-bg-hex" type="text" x-model="value" wire:model="announcementBg"
                               class="block min-h-11 w-full rounded-[var(--radius-control)] border bg-surface px-3.5 py-2.5 font-mono text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('announcementBg') ? 'border-danger' : 'border-line-strong' }}">
                    </div>
                    @error('announcementBg')<p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>@enderror
                </div>
                <div x-data="{ value: $wire.entangle('announcementTextColor') }">
                    <label for="announcement-text-hex" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Bar text color') }}</label>
                    <div class="flex items-center gap-2">
                        <input type="color" x-model="value" aria-label="{{ __('Bar text color picker') }}"
                               class="size-11 shrink-0 cursor-pointer rounded-[var(--radius-control)] border border-line-strong bg-surface p-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                        <input id="announcement-text-hex" type="text" x-model="value" wire:model="announcementTextColor"
                               class="block min-h-11 w-full rounded-[var(--radius-control)] border bg-surface px-3.5 py-2.5 font-mono text-[13px] text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald {{ $errors->has('announcementTextColor') ? 'border-danger' : 'border-line-strong' }}">
                    </div>
                    @error('announcementTextColor')<p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>@enderror
                </div>
            </div>
        </x-ui.card>

        <x-ui.card class="space-y-4 p-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-display text-lg font-semibold">{{ __('Home hero') }}</h2>
                <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-[13px] font-medium text-ink">
                    <input type="checkbox" wire:model="heroImageEnabled" class="size-4 rounded border-line-strong text-emerald focus-visible:ring-2 focus-visible:ring-emerald">
                    {{ __('Enabled') }}
                </label>
            </div>

            <div>
                <label for="hero-image" class="mb-1.5 block text-[13px] font-medium text-ink">{{ __('Hero image') }}</label>
                <input id="hero-image" type="file" accept="image/*" wire:model="heroImage"
                       class="block w-full text-[13px] text-ink-soft file:mr-3 file:min-h-11 file:cursor-pointer file:rounded-lg file:border file:border-line-strong file:bg-surface file:px-4 file:py-2 file:text-[13px] file:font-semibold file:text-ink hover:file:bg-paper">
                @error('heroImage')
                    <p class="mt-1.5 text-[13px] text-danger">{{ $message }}</p>
                @else
                    <p class="mt-1.5 text-[13px] text-ink-faint">{{ __('Wide image, at least 1600px — shown 280px tall with a dark overlay.') }}</p>
                @enderror
                <div wire:loading wire:target="heroImage" class="mt-2 text-[13px] text-ink-soft">{{ __('Uploading…') }}</div>
            </div>

            @if ($heroImage)
                <div>
                    <p class="mb-1.5 text-[13px] font-medium text-ink">{{ __('New image (saved on Save)') }}</p>
                    <img src="{{ $heroImage->temporaryUrl() }}" alt="{{ __('Hero preview') }}" class="h-24 w-full max-w-md rounded-[var(--radius-card)] border border-line object-cover">
                </div>
            @elseif ($heroUrl)
                <div class="flex items-end gap-3">
                    <div>
                        <p class="mb-1.5 text-[13px] font-medium text-ink">{{ __('Current image') }}</p>
                        <img src="{{ $heroUrl }}" alt="{{ __('Current hero image') }}" class="h-24 w-full max-w-md rounded-[var(--radius-card)] border border-line object-cover">
                    </div>
                    <button type="button" wire:click="removeHeroImage"
                            wire:confirm="{{ __('Remove the hero image?') }}"
                            class="inline-flex min-h-11 items-center rounded-[var(--radius-control)] px-2 text-[13px] font-medium text-danger hover:bg-danger-tint focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald">
                        {{ __('Remove') }}
                    </button>
                </div>
            @endif
        </x-ui.card>

        <div class="flex items-center gap-2">
            <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save, heroImage">{{ __('Save theme') }}</x-ui.button>
        </div>
    </form>
</div>
