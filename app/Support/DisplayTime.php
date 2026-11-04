<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The one place that knows the DISPLAY zone. Storage and app.timezone stay UTC.
 *
 *  - OUT: render a stored time with `$t->display()` (Carbon macro, see AppServiceProvider)
 *    or DisplayTime::show($t).
 *  - IN:  a time a user typed is Malaysia time: DisplayTime::parse() returns it in UTC.
 *  - DAYS: "today" / a day filter is a Malaysia day: dayStart()/dayEnd() return UTC bounds
 *    ready for a query; today() is the Malaysia calendar date as a midnight Carbon (for
 *    comparing against `date` columns, which carry no time).
 *
 * A per-user picker later changes zone() only.
 */
final class DisplayTime
{
    public static function zone(): string
    {
        return config('app.display_timezone');
    }

    /** Stored (UTC) instant -> same instant in the display zone. Never mutates $time. */
    public static function show(CarbonInterface $time): Carbon
    {
        return Carbon::instance($time)->setTimezone(self::zone());
    }

    /** Now, in the display zone (for rendering and for picking the Malaysia date). */
    public static function now(): Carbon
    {
        return Carbon::now(self::zone());
    }

    /** Today's Malaysia calendar date as midnight in app.timezone, so it compares with `date` casts. */
    public static function today(): Carbon
    {
        return Carbon::parse(self::now()->toDateString(), config('app.timezone'));
    }

    /** Typed date/time (no zone) read as Malaysia time -> UTC instant for storage or comparison. */
    public static function parse(string $typed): Carbon
    {
        return Carbon::parse($typed, self::zone())->setTimezone(config('app.timezone'));
    }

    /** UTC instant at which the Malaysia day of $day begins. */
    public static function dayStart(CarbonInterface|string|null $day = null): Carbon
    {
        return self::localDay($day)->startOfDay()->setTimezone(config('app.timezone'));
    }

    /** UTC instant at which the Malaysia day of $day ends (inclusive, 23:59:59.999999). */
    public static function dayEnd(CarbonInterface|string|null $day = null): Carbon
    {
        return self::localDay($day)->endOfDay()->setTimezone(config('app.timezone'));
    }

    /** A string is a typed Malaysia date; a Carbon is an instant that gets converted. */
    private static function localDay(CarbonInterface|string|null $day): Carbon
    {
        return match (true) {
            $day === null => self::now(),
            is_string($day) => Carbon::parse($day, self::zone()),
            default => self::show($day),
        };
    }
}
