<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use App\Services\Sms\SmsSender;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Issues + verifies short-lived one-time codes. Only the bcrypt hash is
 * stored; the plain code travels by mail (2fa-email) or SMS (phone-verify)
 * and is never logged except via the local LogSmsSender stub.
 */
class OtpService
{
    public const PURPOSE_2FA_EMAIL = '2fa-email';

    public const PURPOSE_PHONE_VERIFY = 'phone-verify';

    private const EXPIRY_MINUTES = 10;

    private const MAX_ATTEMPTS = 5;

    public function __construct(private SmsSender $sms) {}

    /**
     * Generate + deliver a 6-digit code. Returns false when throttled
     * (max one issue per purpose per minute) — the previous code stays valid.
     */
    public function issue(User $user, string $purpose): bool
    {
        $key = $this->throttleKey($user, $purpose);

        if (RateLimiter::tooManyAttempts($key, 1)) {
            return false;
        }

        RateLimiter::hit($key, 60);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->otpCodes()->where('purpose', $purpose)->delete();

        $user->otpCodes()->create([
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
        ]);

        match ($purpose) {
            self::PURPOSE_2FA_EMAIL => $user->notify(new TwoFactorCodeNotification($code)),
            self::PURPOSE_PHONE_VERIFY => $this->sms->send(
                (string) $user->phone,
                __('Your HalalBizs verification code is :code. It expires in :minutes minutes.', [
                    'code' => $code,
                    'minutes' => self::EXPIRY_MINUTES,
                ]),
            ),
            default => throw new \InvalidArgumentException("Unknown OTP purpose [{$purpose}]."),
        };

        return true;
    }

    /**
     * Seconds until another code may be issued (0 = ready now).
     */
    public function availableIn(User $user, string $purpose): int
    {
        $key = $this->throttleKey($user, $purpose);

        return RateLimiter::tooManyAttempts($key, 1) ? RateLimiter::availableIn($key) : 0;
    }

    /**
     * Constant-time check via Hash::check. Expired codes are discarded;
     * the 5th wrong attempt burns the code (request a fresh one).
     */
    public function verify(User $user, string $purpose, string $code): bool
    {
        $otp = $user->otpCodes()->where('purpose', $purpose)->latest('id')->first();

        if ($otp === null) {
            return false;
        }

        if ($otp->expires_at->isPast()) {
            $otp->delete();

            return false;
        }

        if (Hash::check($code, $otp->code_hash)) {
            $otp->delete();

            return true;
        }

        $otp->increment('attempts');

        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            $otp->delete();
        }

        return false;
    }

    /**
     * Whether a live (unexpired, unburned) code exists for this purpose.
     */
    public function hasActiveCode(User $user, string $purpose): bool
    {
        return $user->otpCodes()
            ->where('purpose', $purpose)
            ->where('expires_at', '>', now())
            ->exists();
    }

    private function throttleKey(User $user, string $purpose): string
    {
        return "otp-issue:{$purpose}:{$user->id}";
    }
}
