<?php

namespace App\Support;

/**
 * The client address, in the form a rate limiter should key on.
 *
 * IPv6 is collapsed to its /64. A single residential IPv6 allocation is a /64
 * (often a /56 or /48), so keying a limiter on the full /128 hands one attacker
 * millions of distinct buckets — the limit is then decorative. IPv4 has no
 * equivalent problem and is used as-is.
 *
 * ⚠ This makes an IP limiter honest, it does not make it strong. Behind
 * `trustProxies(at: '*')` the address is still attacker-chosen (audit H-1b), and
 * even with a correct address real attackers hold real addresses. Anything worth
 * protecting is keyed on the ACCOUNT being attacked; an IP bucket is only ever
 * the looser secondary.
 */
final class ClientIp
{
    public static function bucket(?string $ip = null): string
    {
        $ip ??= request()->ip();

        if ($ip === null || $ip === '') {
            return 'unknown';
        }

        if (! str_contains($ip, ':')) {
            return $ip;
        }

        $packed = @inet_pton($ip);

        if ($packed === false || strlen($packed) !== 16) {
            return $ip;
        }

        return inet_ntop(substr($packed, 0, 8).str_repeat("\0", 8)).'/64';
    }
}
