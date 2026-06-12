<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class SecuritySettings extends Settings
{
    public string $turnstile_site_key;

    public string $turnstile_secret;

    public string $google_client_id;

    public string $google_client_secret;

    /** Stored for the future real SMS gateway driver — dormant for now. */
    public string $sms_provider_key;

    public static function group(): string
    {
        return 'security';
    }

    public static function encrypted(): array
    {
        return ['turnstile_secret', 'google_client_secret', 'sms_provider_key'];
    }

    public function turnstileEnabled(): bool
    {
        return $this->turnstile_site_key !== '' && $this->turnstile_secret !== '';
    }

    public function googleEnabled(): bool
    {
        return $this->google_client_id !== '' && $this->google_client_secret !== '';
    }
}
