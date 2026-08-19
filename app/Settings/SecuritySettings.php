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

    /**
     * Reject passwords found in public breach dumps (Password::uncompromised()).
     *
     * ON by default and should stay on: it only ever blocks a password an
     * attacker already has on a list, and the check itself fails OPEN when the
     * API is unreachable, so switching it off does not unblock legitimate
     * customers — it only permits weaker ones. The minimum length is NOT part
     * of this toggle and cannot be switched off.
     */
    public bool $breached_password_check;

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
