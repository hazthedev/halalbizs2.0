<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * A send limit that counts only what was saved (HB-G240). The slot is taken
 * first, so two sends at the same moment cannot both pass a check-then-count;
 * it is given back when the limit was already reached or the save throws.
 * RateLimiter::attempt() alone checks and counts in two steps, so it is not
 * used here.
 *
 * The count changes under a cache lock: the file cache store (live) does an
 * increment as a plain read then write, so two requests at once could both
 * read N and both write N+1. Its lock is an exclusive file lock, so it holds
 * across PHP processes. A lock that cannot be had within 2 s is a refusal.
 */
class SendLimit
{
    /** False (nothing saved) when over $max; otherwise runs $save. */
    public static function attempt(string $key, int $max, int $decaySeconds, Closure $save): bool
    {
        $taken = self::locked($key, function () use ($key, $max, $decaySeconds) {
            if (RateLimiter::hit($key, $decaySeconds) <= $max) {
                return true;
            }

            RateLimiter::decrement($key, $decaySeconds);

            return false;
        });

        if (! $taken) {
            return false;
        }

        try {
            $save();
        } catch (\Throwable $e) {
            // Never below zero: if the window ran out meanwhile, a decrement
            // would start the next window at -1, one free send.
            self::locked($key, fn () => RateLimiter::attempts($key) > 0 && RateLimiter::decrement($key, $decaySeconds));

            throw $e;
        }

        return true;
    }

    private static function locked(string $key, Closure $change): bool
    {
        try {
            return (bool) Cache::lock('send-limit:'.$key, 5)->block(2, $change);
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            return false;
        }
    }
}
