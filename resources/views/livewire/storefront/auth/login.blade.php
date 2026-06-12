<div class="mx-auto w-full max-w-md px-4 py-12 sm:py-16">
    <x-ui.card class="p-6 sm:p-8">
        <h1 class="font-display text-[28px] font-bold leading-tight">{{ __('Log in') }}</h1>
        <p class="mt-1 text-sm text-ink-soft">{{ __('Welcome back — your cart is right where you left it.') }}</p>

        @if (session('status'))
            <p class="mt-4 rounded-lg bg-emerald-tint px-3.5 py-2.5 text-[13px] text-emerald">{{ session('status') }}</p>
        @endif

        <form wire:submit="login" class="mt-6 space-y-4">
            <x-ui.input
                :label="__('Email')"
                name="email"
                type="email"
                wire:model="email"
                autocomplete="email"
                required
                autofocus
                :error="$errors->first('email')"
            />

            <div>
                <x-ui.input
                    :label="__('Password')"
                    name="password"
                    type="password"
                    wire:model="password"
                    autocomplete="current-password"
                    required
                    :error="$errors->first('password')"
                />
                <div class="mt-1.5 text-right">
                    <a href="{{ route('password.request') }}" wire:navigate class="text-[13px] font-medium text-emerald hover:text-emerald-deep">{{ __('Forgot password?') }}</a>
                </div>
            </div>

            <label class="flex min-h-11 cursor-pointer items-center gap-2.5 text-sm text-ink">
                <input type="checkbox" wire:model="remember" class="size-4 rounded accent-emerald">
                {{ __('Keep me logged in') }}
            </label>

            <x-turnstile :error="$errors->first('turnstileToken')" />

            <x-ui.button type="submit" class="w-full" wire:loading.attr="disabled">
                <svg wire:loading wire:target="login" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"/></svg>
                {{ __('Log in') }}
            </x-ui.button>
        </form>

        <p class="mt-6 border-t border-line pt-5 text-center text-sm text-ink-soft">
            {{ __('New here?') }}
            <a href="{{ route('register') }}" wire:navigate class="font-semibold text-emerald hover:text-emerald-deep">{{ __('Create an account') }}</a>
        </p>
    </x-ui.card>
</div>
