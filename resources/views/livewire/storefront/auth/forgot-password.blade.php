<div class="mx-auto w-full max-w-md px-4 py-12 sm:py-16">
    <x-ui.card class="p-6 sm:p-8">
        <h1 class="font-display text-[28px] font-bold leading-tight">{{ __('Forgot password') }}</h1>
        <p class="mt-1 text-sm text-ink-soft">{{ __('Enter your email and we\'ll send you a link to set a new one.') }}</p>

        @if ($status)
            <p class="mt-4 rounded-lg bg-emerald-tint px-3.5 py-2.5 text-[13px] text-emerald">{{ $status }}</p>
        @endif

        <form wire:submit="sendResetLink" class="mt-6 space-y-4">
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

            <x-turnstile :error="$errors->first('turnstileToken')" />

            <x-ui.button type="submit" class="w-full" wire:loading.attr="disabled">
                <svg wire:loading wire:target="sendResetLink" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"/></svg>
                {{ __('Send reset link') }}
            </x-ui.button>
        </form>

        <p class="mt-6 border-t border-line pt-5 text-center text-sm text-ink-soft">
            {{ __('Remembered it?') }}
            <a href="{{ route('login') }}" wire:navigate class="font-semibold text-emerald hover:text-emerald-deep">{{ __('Back to log in') }}</a>
        </p>
    </x-ui.card>
</div>
