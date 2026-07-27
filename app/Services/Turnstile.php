<?php

namespace App\Services;

use App\Settings\SecuritySettings;
use Illuminate\Support\Facades\Http;

/**
 * Cloudflare Turnstile server-side verification. When no keys are
 * configured (local/dev), the check passes so forms keep working.
 *
 * That passthrough is FAIL-CLOSED outside local/testing: an unconfigured
 * production boot must not silently wave every registration/login through
 * with zero bot friction. Same opt-in shape as Ipay88Service::isMock()
 * (IPAY88_ALLOW_MOCK); the override is read via config/services.php because
 * a raw env() call here would return null under `config:cache` in production.
 */
class Turnstile
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(private SecuritySettings $settings) {}

    public function verify(?string $token, ?string $ip = null): bool
    {
        if (! $this->settings->turnstileEnabled()) {
            return app()->environment('local', 'testing')
                || (bool) config('services.turnstile.allow_unconfigured', false);
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
