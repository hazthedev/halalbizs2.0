<?php

namespace App\Support;

/**
 * Pure-PHP RFC 6238 TOTP (SHA-1, 6 digits, 30-second period) — no package
 * needed for the standard profile every authenticator app speaks (hard
 * rule 10: zero dependencies beating a package was the rationale).
 */
class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    private const PERIOD = 30;

    private const DIGITS = 6;

    /**
     * 160-bit random secret, base32-encoded (32 chars) — RFC 4226 §4
     * recommends 160 bits for HMAC-SHA1.
     */
    public function generateSecret(): string
    {
        $bytes = random_bytes(20);
        $secret = '';

        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        foreach (str_split($bits, 5) as $chunk) {
            $secret .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $secret;
    }

    /**
     * Current code for a secret (or for an explicit timestamp — used by
     * tests and the ±window check).
     */
    public function code(string $secret, ?int $timestamp = null): string
    {
        $counter = intdiv($timestamp ?? time(), self::PERIOD);

        return $this->hotp($this->base32Decode($secret), $counter);
    }

    /**
     * Accepts the current period plus ±$window periods of clock drift.
     */
    public function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (! preg_match('/^\d{'.self::DIGITS.'}$/', $code)) {
            return false;
        }

        $now = time();

        foreach (range(-$window, $window) as $offset) {
            $candidate = $this->code($secret, $now + ($offset * self::PERIOD));

            if (hash_equals($candidate, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * otpauth:// provisioning URI — rendered as selectable text (no QR
     * package in v1); authenticator apps accept manual secret entry too.
     */
    public function otpauthUri(string $secret, string $email, string $issuer = 'HalalBizs'): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($email),
            $secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD,
        );
    }

    private function hotp(string $key, int $counter): string
    {
        $binaryCounter = pack('N*', 0).pack('N*', $counter); // 64-bit big-endian

        $hash = hash_hmac('sha1', $binaryCounter, $key, true);

        $offset = ord($hash[19]) & 0x0F;

        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        $secret = strtoupper(str_replace([' ', '='], '', $secret));

        $bits = '';
        foreach (str_split($secret) as $char) {
            $index = strpos(self::ALPHABET, $char);

            if ($index === false) {
                continue;
            }

            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }

        return $bytes;
    }
}
