<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\NewDeviceLoginNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Records the device a successful login came from and alerts the user
 * (mail + database) when it's one we haven't seen before.
 *
 * - Fingerprint: sha256 of the user agent + the /24 IP block, so a DHCP
 *   lease renewal inside the same network doesn't re-alert.
 * - The very first device is recorded silently — the login right after
 *   sign-up must not greet the user with a security alert.
 */
class DeviceGuard
{
    public function record(User $user, Request $request): void
    {
        $fingerprint = $this->fingerprint($request);

        $existing = $user->knownDevices()->where('fingerprint', $fingerprint)->first();

        if ($existing !== null) {
            $existing->update(['last_seen_at' => now()]);

            return;
        }

        $isFirstDevice = ! $user->knownDevices()->exists();

        $device = $user->knownDevices()->create([
            'fingerprint' => $fingerprint,
            'label' => $this->describe($request->userAgent()),
            'last_seen_at' => now(),
        ]);

        if (! $isFirstDevice) {
            $user->notify(new NewDeviceLoginNotification($device->label, (string) $request->ip()));
        }
    }

    public function fingerprint(Request $request): string
    {
        return hash('sha256', ($request->userAgent() ?? '').'|'.$this->ipBlock((string) $request->ip()));
    }

    /** "Chrome on Windows" from a raw user-agent string. */
    public function describe(?string $userAgent): string
    {
        $agent = (string) $userAgent;

        $browser = match (true) {
            Str::contains($agent, 'Edg/') => 'Edge',
            Str::contains($agent, 'OPR/') => 'Opera',
            Str::contains($agent, 'Firefox/') => 'Firefox',
            Str::contains($agent, 'Chrome/') => 'Chrome',
            Str::contains($agent, 'Safari/') => 'Safari',
            default => null,
        };

        $platform = match (true) {
            Str::contains($agent, 'Windows') => 'Windows',
            Str::contains($agent, 'Android') => 'Android',
            Str::contains($agent, ['iPhone', 'iPad', 'iOS']) => 'iOS',
            Str::contains($agent, ['Macintosh', 'Mac OS']) => 'macOS',
            Str::contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        if ($browser === null && $platform === null) {
            return __('Unknown device');
        }

        if ($browser !== null && $platform !== null) {
            return __(':browser on :platform', ['browser' => $browser, 'platform' => $platform]);
        }

        return $browser ?? $platform;
    }

    /** /24 block for IPv4; the first four hextets for IPv6. */
    private function ipBlock(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return implode('.', array_slice(explode('.', $ip), 0, 3));
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return implode(':', array_slice(explode(':', $ip), 0, 4));
        }

        return $ip;
    }
}
