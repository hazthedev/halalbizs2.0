<div class="mx-auto w-full max-w-md px-4 py-12 sm:py-16">
    <x-ui.card class="p-6 sm:p-8">
        <h1 class="font-display text-[28px] font-bold leading-tight">{{ __('Create account') }}</h1>
        <p class="mt-1 text-sm text-ink-soft">{{ __('A few details and you\'re ready to shop.') }}</p>

        <form wire:submit="register" class="mt-6 space-y-4">
            <x-ui.input
                :label="__('Name')"
                name="name"
                wire:model="name"
                autocomplete="name"
                required
                autofocus
                :error="$errors->first('name')"
            />

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
                :label="__('Phone (optional)')"
                name="phone"
                type="tel"
                wire:model="phone"
                autocomplete="tel"
                placeholder="012-345 6789"
                :error="$errors->first('phone')"
            />

            <x-ui.input
                :label="__('Password')"
                name="password"
                type="password"
                wire:model="password"
                autocomplete="new-password"
                required
                :error="$errors->first('password')"
                :hint="$errors->first('password') ? null : __('At least 8 characters.')"
            />

            <x-ui.input
                :label="__('Confirm password')"
                name="password_confirmation"
                type="password"
                wire:model="password_confirmation"
                autocomplete="new-password"
                required
                :error="$errors->first('password_confirmation')"
            />

            <div>
                <label class="flex min-h-11 cursor-pointer items-start gap-2.5 py-2 text-[13px] leading-snug text-ink-soft">
                    <input type="checkbox" wire:model="terms" class="mt-0.5 size-4 shrink-0 rounded accent-emerald" required>
                    <span>
                        {{ __('I agree to the') }}
                        <a href="{{ route('page.show', 'terms') }}" wire:navigate class="font-medium text-emerald hover:text-emerald-deep">{{ __('terms & conditions') }}</a>
                        {{ __('and consent to my personal data being processed under the') }}
                        <a href="{{ route('page.show', 'privacy') }}" wire:navigate class="font-medium text-emerald hover:text-emerald-deep">{{ __('privacy policy') }}</a>.
                    </span>
                </label>
                @error('terms')
                    <p class="mt-1 text-[13px] text-danger">{{ $message }}</p>
                @enderror
            </div>

            <x-turnstile :error="$errors->first('turnstileToken')" />

            <x-ui.button type="submit" class="w-full" wire:loading.attr="disabled">
                <svg wire:loading wire:target="register" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"/></svg>
                {{ __('Create account') }}
            </x-ui.button>
        </form>

        <p class="mt-6 border-t border-line pt-5 text-center text-sm text-ink-soft">
            {{ __('Already have an account?') }}
            <a href="{{ route('login') }}" wire:navigate class="font-semibold text-emerald hover:text-emerald-deep">{{ __('Log in') }}</a>
        </p>
    </x-ui.card>
</div>
