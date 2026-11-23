{{-- New Google account consent step (GoogleAuthController::consent, HB-G221).
     Same card, tick and wording as register: no account exists until this posts
     with the box ticked. --}}
@component('layouts.landing', ['variant' => 'light', 'title' => __('Create account')])
<div class="mx-auto w-full max-w-md px-4 py-12 sm:py-16">
    <x-ui.card class="p-6 sm:p-8">
        <x-ui.section-heading as="h1"
            :title="__('Create account')"
            :subtitle="__('Signing up with Google as :email.', ['email' => $email])" />

        <form method="POST" action="{{ route('auth.google.consent.store') }}" class="mt-6 space-y-4">
            @csrf

            <div>
                <label class="flex min-h-11 cursor-pointer items-start gap-2.5 py-2 text-[13px] leading-snug text-ink-soft">
                    <input type="checkbox" name="terms" value="1" @checked(old('terms')) class="mt-0.5 size-4 shrink-0 rounded accent-emerald" required>
                    <span>
                        {{ __('I agree to the') }}
                        <a href="{{ route('page.show', 'terms') }}" class="font-medium text-emerald hover:text-emerald-deep">{{ __('terms & conditions') }}</a>
                        {{ __('and consent to my personal data being processed under the') }}
                        <a href="{{ route('page.show', 'privacy') }}" class="font-medium text-emerald hover:text-emerald-deep">{{ __('privacy policy') }}</a>.
                    </span>
                </label>
                @error('terms')
                    <p class="mt-1 text-[13px] text-danger">{{ $message }}</p>
                @enderror
            </div>

            <x-ui.button type="submit" class="w-full">
                {{ __('Create account') }}
            </x-ui.button>
        </form>

        <p class="mt-6 border-t border-line pt-5 text-center text-sm text-ink-soft">
            {{ __('Already have an account?') }}
            <a href="{{ route('login') }}" class="font-medium text-emerald hover:text-emerald-deep">{{ __('Log in') }}</a>
        </p>
    </x-ui.card>
</div>
@endcomponent
