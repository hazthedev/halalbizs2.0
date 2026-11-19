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

            <x-ui.button type="submit" class="w-full" wire:loading.attr="disabled" target="resetPassword">
                {{ __('Reset password') }}
            </x-ui.button>
        </form>
    </x-ui.card>
</div>
