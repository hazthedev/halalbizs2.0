<?php

namespace App\Services;

use App\Settings\SecuritySettings;
use Illuminate\Support\Facades\Http;

/**
 * Cloudflare Turnstile server-side verification. When no keys are
 * configured (local/dev), the check passes so forms keep working.
 */
class Turnstile
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(private SecuritySettings $settings) {}

    public function verify(?string $token, ?string $ip = null): bool
    {
        if (! $this->settings->turnstileEnabled()) {
            return true;
        }

        if ($token === null || $token === '') {
            return false;
        }

        $response = Http::asForm()->post(self::VERIFY_URL, [
            'secret' => $this->settings->turnstile_secret,
            'response' => $token,
            'remoteip' => $ip,
        ]);

        return $response->successful() && $response->json('success') === true;
    }
}
