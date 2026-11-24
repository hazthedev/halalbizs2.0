<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\RateLimiter;

/**
 * A send limit that counts only what was saved (HB-G240). The slot is taken
 * first with one atomic cache increment, so two sends at the same moment
 * cannot both pass a check-then-count; it is given back when the limit was
 * already reached or the save throws. RateLimiter::attempt() alone checks and
 * counts in two steps, so it is not used here.
 */
class SendLimit
{
    /** False (nothing saved) when over $max; otherwise runs $save. */
    public static function attempt(string $key, int $max, int $decaySeconds, Closure $save): bool
    {
        if (RateLimiter::hit($key, $decaySeconds) > $max) {
            RateLimiter::decrement($key, $decaySeconds);

            return false;
        }

        try {
            $save();
        } catch (\Throwable $e) {
            RateLimiter::decrement($key, $decaySeconds);

            throw $e;
        }

        return true;
    }
}
