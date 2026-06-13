<div class="mx-auto w-full max-w-md px-4 py-12 sm:py-16">
    <x-ui.card class="p-6 sm:p-8">
        <x-ui.section-heading as="h1" :title="__('Two-factor check')" />

        @if ($useRecoveryCode)
            <p class="mt-1 text-sm text-ink-soft">{{ __('Enter one of the recovery codes you saved when you set up your authenticator. Each code works once.') }}</p>
        @elseif ($method === \App\Enums\TwoFactorMethod::Totp)
            <p class="mt-1 text-sm text-ink-soft">{{ __('Open your authenticator app and enter the 6-digit code for HalalBizs.') }}</p>
        @else
            <p class="mt-1 text-sm text-ink-soft">{{ __('We\'ve emailed a 6-digit code to :email. It expires in 10 minutes.', ['email' => $maskedEmail]) }}</p>
        @endif

        <form wire:submit="verify" class="mt-6 space-y-4">
            @if ($useRecoveryCode)
                <x-ui.input
                    :label="__('Recovery code')"
                    name="recovery_code"
                    wire:model="recovery_code"
                    autocomplete="off"
                    autofocus
                    required
                    placeholder="XXXXX-XXXXX"
                    class="[&_input]:font-mono"
                    :error="$errors->first('recovery_code')"
                />
            @else
                <x-ui.input
                    :label="__('6-digit code')"
                    name="code"
                    type="text"
                    inputmode="numeric"
                    maxlength="6"
                    wire:model="code"
                    autocomplete="one-time-code"
                    autofocus
                    required
                    placeholder="123456"
                    class="[&_input]:font-mono [&_input]:tracking-[0.3em]"
                    :error="$errors->first('code')"
                />
            @endif

            <x-ui.button type="submit" class="w-full" wire:loading.attr="disabled">
                <svg wire:loading wire:target="verify" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"/></svg>
                {{ __('Verify and log in') }}
            </x-ui.button>
        </form>

        <div class="mt-5 space-y-2 border-t border-line pt-4 text-center">
            @if ($method === \App\Enums\TwoFactorMethod::Email)
                <button type="button" wire:click="resend" wire:loading.attr="disabled"
                        class="min-h-11 text-[13px] font-medium text-emerald hover:text-emerald-deep">
                    {{ __('Email me a new code') }}
                </button>
            @endif

            @if ($method === \App\Enums\TwoFactorMethod::Totp)
                <button type="button" wire:click="toggleRecovery"
                        class="block w-full min-h-11 text-[13px] font-medium text-emerald hover:text-emerald-deep">
                    {{ $useRecoveryCode ? __('Use my authenticator app instead') : __('Use a recovery code instead') }}
                </button>
            @endif

            <a href="{{ route('login') }}" wire:navigate class="block min-h-11 pt-2.5 text-[13px] text-ink-soft hover:text-ink">
                {{ __('Back to log in') }}
            </a>
        </div>
    </x-ui.card>
</div>
