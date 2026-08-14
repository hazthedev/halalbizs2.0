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

    /**
     * The branch prefixes of a trans_choice() line: `{0}`, `{1}`, `[2,*]`.
     *
     * Vietnamese has no plural inflection, so both branches carry the same
     * words — but the SELECTORS still have to survive, and if they do not,
     * Laravel prints the raw "{1}" at the shopper or picks the wrong segment.
     * Same failure mode as a dropped placeholder: silent, and visible only on
     * the rendered page.
     *
     * @return array<int, string> empty for an ordinary, non-choice line
     */
    public static function choiceSelectors(string $line): array
    {
        // Only a line that already uses explicit selectors is checked; the bare
        // "singular|plural" form has no selectors to lose.
        preg_match_all('/(\{\d+\}|\[\d+\s*,\s*(?:\*|\d+)\])/', $line, $m);

        return $m[1];
    }

    /** Does the translation keep the choice selectors, in order? */
    public static function choiceSelectorsMatch(string $source, string $translation): bool
    {
        return self::choiceSelectors($source) === self::choiceSelectors($translation);
    }
}
