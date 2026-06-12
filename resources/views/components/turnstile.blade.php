@props(['error' => null])

@php($security = app(\App\Settings\SecuritySettings::class))

@if ($security->turnstileEnabled())
    <div wire:ignore.self>
        <div wire:ignore class="cf-turnstile" data-sitekey="{{ $security->turnstile_site_key }}" data-callback="turnstileTokenCallback"></div>
        <input type="hidden" id="turnstile-token-input" wire:model="turnstileToken">

        @if ($error)
            <p class="mt-1.5 text-[13px] text-danger">{{ $error }}</p>
        @endif

        <script>
            window.turnstileTokenCallback = function (token) {
                const input = document.getElementById('turnstile-token-input');
                if (input) {
                    input.value = token;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
            };
        </script>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    </div>
@endif
