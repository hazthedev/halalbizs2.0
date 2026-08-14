<?php

namespace App\Support;

class Translation
{
    /**
     * The `:name` placeholders Laravel will substitute at render time.
     *
     * A translation that loses one does not error — the page just prints the
     * literal ":amount" at the shopper, which is why this needs a test and not
     * a code review. Matches `:word` only, so "Current streak: :n days" yields
     * [n] and a clock like "12:30" yields nothing.
     *
     * @return array<int, string> lower-cased, sorted, de-duplicated
     */
    public static function placeholders(string $line): array
    {
        preg_match_all('/:([a-zA-Z][a-zA-Z0-9_]*)/', $line, $m);

        $found = array_map('strtolower', $m[1]);
        sort($found);

        return array_values(array_unique($found));
    }

    /** Does the translation carry exactly the placeholders the source had? */
    public static function placeholdersMatch(string $source, string $translation): bool
    {
        return self::placeholders($source) === self::placeholders($translation);
    }
}
