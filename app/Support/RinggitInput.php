<?php

namespace App\Support;

/**
 * Parses RM form input ("12.50") into integer sen with integer math only —
 * no floats anywhere near money (CLAUDE.md hard rule 1).
 */
class RinggitInput
{
    /**
     * "12.50" → 1250 · "12" → 1200 · "0.05" → 5 · "1,250.00" → 125000.
     * Empty or unparseable input → null.
     */
    public static function toSen(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $clean = preg_replace('/[^0-9.]/', '', trim($value)) ?? '';

        if ($clean === '' || $clean === '.' || substr_count($clean, '.') > 1) {
            return null;
        }

        [$ringgit, $sen] = array_pad(explode('.', $clean, 2), 2, '');

        // Pad/truncate the minor part to exactly two digits: "5" → "50", "505" → "50".
        $sen = substr(str_pad($sen, 2, '0'), 0, 2);

        return ((int) $ringgit) * 100 + (int) $sen;
    }

    /**
     * 1250 → "12.50" for populating form inputs. Null → "".
     */
    public static function fromSen(?int $sen): string
    {
        if ($sen === null) {
            return '';
        }

        $negative = $sen < 0;
        $abs = abs($sen);

        return ($negative ? '-' : '')
            .intdiv($abs, 100)
            .'.'
            .str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }
}
