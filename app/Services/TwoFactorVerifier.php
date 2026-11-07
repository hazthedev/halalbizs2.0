<?php

namespace App\Services;

use App\Enums\TwoFactorMethod;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Support\Facades\Cache;

/**
 * The one second-factor code check, shared by the web challenge screen and
 * the mobile API (mobile plan §4). Returns null when the code passes, or the
 * user-facing reason it did not — the caller decides how to show it.
 */
class TwoFactorVerifier
{
    public function __construct(private Totp $totp, private OtpService $otp) {}

    public function check(User $user, string $code): ?string
    {
        $code = trim($code);

        if ($user->two_factor_method === TwoFactorMethod::Totp) {
            if (! $this->totp->verify((string) $user->two_factor_secret, $code)) {
                return __('That code isn\'t right — open your authenticator app and enter the current code.');
            }

            // Totp::verify() checks a ±1 period window but has no memory of
            // its own — the same 6-digit code would otherwise work again for
            // as long as it stays inside that window (replay). There is no
            // 2FA DB column for this (outside this fix's surface), so the
            // memo lives in cache keyed by user + code, TTL'd comfortably
            // past the verification window.
            if (! $this->rememberTotpCodeUnused($user->id, $code)) {
                return __('That code has already been used — wait for your authenticator app to show a new one.');
            }

            return null;
        }

        if (! $this->otp->verify($user, OtpService::PURPOSE_2FA_EMAIL, $code)) {
            return $this->otp->hasActiveCode($user, OtpService::PURPOSE_2FA_EMAIL)
                ? __('That code isn\'t right — check the latest email and try again.')
                : __('That code no longer works — request a new code and enter it within 10 minutes.');
        }

        return null;
    }

    /**
     * True the first time this exact code is seen for this user; false (and
     * memoized either way is a no-op) on replay. TTL of 120s comfortably
     * covers Totp::verify()'s ±1 period (30s) window plus clock drift.
     */
    private function rememberTotpCodeUnused(int $userId, string $code): bool
    {
        $key = 'totp-used:'.$userId.':'.preg_replace('/\s+/', '', $code);

        if (Cache::has($key)) {
            return false;
        }

        Cache::put($key, true, 120);

        return true;
    }
}
