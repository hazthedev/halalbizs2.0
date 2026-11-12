<div class="max-w-3xl space-y-4">

    <x-ui.section-heading :title="__('Footer & contact')" as="h1" />

    @unless ($canPublish)
        <p class="rounded-[var(--radius-card)] border border-line bg-paper px-4 py-3 text-[13px] text-ink-soft">{{ __('Only a publisher can change these.') }}</p>
    @endunless

    <form wire:submit="save" class="space-y-4">

        <x-ui.card class="space-y-4 p-4">
            <h2 class="font-display text-lg font-medium">{{ __('Contact') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input :label="__('Email')" type="email" wire:model="contactEmail" :error="$errors->first('contactEmail')" />
                <x-ui.input :label="__('Phone')" wire:model="contactPhone" :error="$errors->first('contactPhone')" />
                <x-ui.input :label="__('WhatsApp')" wire:model="contactWhatsapp" :error="$errors->first('contactWhatsapp')" />
                <x-ui.input :label="__('Address')" wire:model="contactAddress" :error="$errors->first('contactAddress')" />
            </div>
        </x-ui.card>

        <x-ui.card class="space-y-4 p-4">
            <h2 class="font-display text-lg font-medium">{{ __('Social links') }}</h2>
            <p class="text-[13px] text-ink-faint">{{ __('Full https:// links. Leave empty to hide.') }}</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input label="Facebook" wire:model="socialFacebook" :error="$errors->first('socialFacebook')" />
                <x-ui.input label="Instagram" wire:model="socialInstagram" :error="$errors->first('socialInstagram')" />
                <x-ui.input label="TikTok" wire:model="socialTiktok" :error="$errors->first('socialTiktok')" />
                <x-ui.input label="YouTube" wire:model="socialYoutube" :error="$errors->first('socialYoutube')" />
                <x-ui.input label="X" wire:model="socialX" :error="$errors->first('socialX')" />
            </div>
        </x-ui.card>

        @if ($canPublish)
            <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">{{ __('Save contact') }}</x-ui.button>
        @endif
    </form>
</div>
