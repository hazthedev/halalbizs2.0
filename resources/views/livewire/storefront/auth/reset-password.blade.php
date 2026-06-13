<div class="mx-auto w-full max-w-md px-4 py-12 sm:py-16">
    <x-ui.card class="p-6 sm:p-8">
        <x-ui.section-heading as="h1" :title="__('Reset password')" :subtitle="__('Choose a new password for your account.')" />

        <form wire:submit="resetPassword" class="mt-6 space-y-4">
            <x-ui.input
                :label="__('Email')"
                name="email"
                type="email"
                wire:model="email"
                autocomplete="email"
                required
                :error="$errors->first('email')"
            />

            <x-ui.input
                :label="__('New password')"
                name="password"
                type="password"
                wire:model="password"
                autocomplete="new-password"
                required
                autofocus
                :error="$errors->first('password')"
                :hint="$errors->first('password') ? null : __('At least 8 characters.')"
            />

            <x-ui.input
                :label="__('Confirm new password')"
                name="password_confirmation"
                type="password"
                wire:model="password_confirmation"
                autocomplete="new-password"
                required
                :error="$errors->first('password_confirmation')"
            />

            <x-ui.button type="submit" class="w-full" wire:loading.attr="disabled">
                <svg wire:loading wire:target="resetPassword" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"/></svg>
                {{ __('Reset password') }}
            </x-ui.button>
        </form>
    </x-ui.card>
</div>
