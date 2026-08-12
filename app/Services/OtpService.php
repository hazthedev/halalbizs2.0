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

    private const MAX_SENDS_PER_HOUR = 5;

    public function __construct(private SmsSender $sms) {}

    /**
     * Generate + deliver a 6-digit code. Returns false when throttled
     * (max one issue per purpose per minute) — the previous code stays valid.
     */
    public function issue(User $user, string $purpose): bool
    {
        $key = $this->throttleKey($user, $purpose);
        $hourKey = $this->hourlyThrottleKey($user, $purpose);

        // Two caps, both keyed on the DESTINATION account rather than the
        // requester: one per minute so a double-click doesn't send twice, and a
        // ceiling per hour. Without the second, a cooldown alone lets anyone who
        // knows an email send 60 codes an hour to that person's inbox (or SMS
        // bill) forever. Profile.php already did this for phone sends by hand —
        // it belongs here so every caller gets it.
        if (RateLimiter::tooManyAttempts($key, 1) || RateLimiter::tooManyAttempts($hourKey, self::MAX_SENDS_PER_HOUR)) {
            return false;
        }

        RateLimiter::hit($key, 60);
        RateLimiter::hit($hourKey, 3600);

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
        $hourKey = $this->hourlyThrottleKey($user, $purpose);

        // Report whichever cap is actually holding them up — quoting the 60s
        // cooldown to someone who has hit the hourly ceiling is a lie the UI
        // then repeats every minute.
        return max(
            RateLimiter::tooManyAttempts($key, 1) ? RateLimiter::availableIn($key) : 0,
            RateLimiter::tooManyAttempts($hourKey, self::MAX_SENDS_PER_HOUR) ? RateLimiter::availableIn($hourKey) : 0,
        );
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

    private function hourlyThrottleKey(User $user, string $purpose): string
    {
        return "otp-issue-hour:{$purpose}:{$user->id}";
    }
}
