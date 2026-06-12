<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class SecuritySettings extends Settings
{
    public string $turnstile_site_key;

    public string $turnstile_secret;

    public static function group(): string
    {
        return 'security';
    }

    public static function encrypted(): array
    {
        return ['turnstile_secret'];
    }

    public function turnstileEnabled(): bool
    {
        return $this->turnstile_site_key !== '' && $this->turnstile_secret !== '';
    }
}
