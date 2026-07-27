<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * "Trust this device for 30 days" — lets a user skip the 2FA code on a device
 * they have already verified, without weakening 2FA itself.
 *
 * Trust requires TWO things at once, so neither half is sufficient alone:
 *   1. a secret token, held in a signed+encrypted httpOnly cookie (have)
 *   2. the device fingerprint — UA + /24 IP block (DeviceGuard) (are)
 * Only the token's HASH is stored, so a leaked DB row cannot mint trust, and a
 * stolen cookie replayed from another network fails the fingerprint match.
 */
class DeviceTrust
{
    private const COOKIE = 'device_trust';

    private const DAYS = 30;

    public function __construct(private DeviceGuard $devices) {}

    /** Is THIS request coming from a device the user trusted and not yet expired? */
    public function isTrusted(User $user, Request $request): bool
    {
        $token = $request->cookie(self::COOKIE);

        if (! is_string($token) || $token === '') {
            return false;
        }

        $device = $user->knownDevices()
            ->where('fingerprint', $this->devices->fingerprint($request))
            ->first();

        return $device !== null
            && $device->trustIsLive()
            && Hash::check($token, (string) $device->trust_token_hash);
    }

    /**
     * Trust this device for the window and queue the cookie. Reuses the
     * device's known_devices row (created by DeviceGuard on login) so trust and
     * the login-alert history stay on one record.
     */
    public function remember(User $user, Request $request): void
    {
        $token = Str::random(64);

        $user->knownDevices()->updateOrCreate(
            ['fingerprint' => $this->devices->fingerprint($request)],
            [
                'label' => $this->devices->describe($request->userAgent()),
                'trust_token_hash' => Hash::make($token),
                'trusted_until' => now()->addDays(self::DAYS),
                'last_seen_at' => now(),
            ],
        );

        // Encrypted + httpOnly by the framework's cookie stack; SameSite=Lax so
        // it rides top-level navigations back to the login flow.
        Cookie::queue(Cookie::make(
            self::COOKIE,
            $token,
            minutes: self::DAYS * 24 * 60,
            httpOnly: true,
            sameSite: 'lax',
        ));
    }

    /**
     * Revoke trust everywhere for this user (password reset / "log out of all
     * devices"). Clears the DB side; the orphaned cookie then fails Hash::check.
     */
    public function forgetAll(User $user): void
    {
        $user->knownDevices()
            ->whereNotNull('trust_token_hash')
            ->update(['trust_token_hash' => null, 'trusted_until' => null]);
    }

    /** Drop this browser's trust cookie (on explicit logout of this session). */
    public function forgetCookie(): void
    {
        Cookie::queue(Cookie::forget(self::COOKIE));
    }
}
