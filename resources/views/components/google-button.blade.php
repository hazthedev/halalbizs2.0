@php($security = app(\App\Settings\SecuritySettings::class))

{{-- Dormant until Google OAuth credentials are configured (Turnstile pattern). --}}
@if ($security->googleEnabled())
    <div>
        <div class="flex items-center gap-3" aria-hidden="true">
            <span class="h-px flex-1 bg-line"></span>
            <span class="text-[13px] text-ink-faint">{{ __('or') }}</span>
            <span class="h-px flex-1 bg-line"></span>
        </div>

        {{-- Full page load on purpose: OAuth leaves the site. --}}
        <a href="{{ route('auth.google.redirect') }}"
           class="mt-4 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-ink px-4 py-2.5 text-sm font-semibold text-ink transition-colors duration-150 hover:bg-paper focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald focus-visible:ring-offset-2">
            <svg class="size-4" viewBox="0 0 24 24" aria-hidden="true">
                <path fill="#4285F4" d="M23.52 12.27c0-.85-.08-1.66-.22-2.45H12v4.64h6.46a5.52 5.52 0 0 1-2.4 3.62v3h3.88c2.27-2.09 3.58-5.17 3.58-8.81Z"/>
                <path fill="#34A853" d="M12 24c3.24 0 5.96-1.07 7.94-2.91l-3.88-3.01c-1.07.72-2.45 1.15-4.06 1.15-3.13 0-5.78-2.11-6.72-4.95H1.27v3.11A12 12 0 0 0 12 24Z"/>
                <path fill="#FBBC05" d="M5.28 14.28a7.2 7.2 0 0 1 0-4.56V6.61H1.27a12 12 0 0 0 0 10.78l4.01-3.11Z"/>
                <path fill="#EA4335" d="M12 4.77c1.76 0 3.34.61 4.59 1.8l3.44-3.44A11.97 11.97 0 0 0 12 0 12 12 0 0 0 1.27 6.61l4.01 3.11C6.22 6.88 8.87 4.77 12 4.77Z"/>
            </svg>
            {{ __('Continue with Google') }}
        </a>
    </div>
@endif
