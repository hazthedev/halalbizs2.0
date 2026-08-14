<?php

namespace App\Support;

/**
 * Case-insensitive LIKE against a translatable JSON column (audit M-19).
 *
 * MySQL gives a JSON column a BINARY collation, so `LIKE '%beras%'` never
 * matches "Beras Wangi". SQLite stores the same column as TEXT with a
 * case-insensitive LIKE, which is why this is invisible locally and broken on
 * the preview: "Beras" returned 8 results, "beras" returned 0. Store and brand
 * names are ordinary VARCHAR and DO match, which is what makes it look like
 * search is merely "half working" rather than broken.
 *
 * Product::keywordSearch solved this in full and documented it. Three other
 * readers never got the recipe. This is that recipe with one home, because a
 * fourth hand-rolled copy of an escaping rule is how the next one drifts.
 */
final class JsonSearch
{
    /**
     * The needle, lowercased and wrapped, with LIKE metacharacters neutralised.
     *
     * A bare `%` or `_` is a wildcard, so unescaped it matches the whole table.
     * `!` is the escape character rather than `\`, because a backslash inside a
     * SQL string literal means one thing to MySQL and another to SQLite.
     *
     * Pair it with {@see self::clause()} — the ESCAPE must agree.
     */
    public static function pattern(?string $term): string
    {
        $term = mb_strtolower(trim((string) $term));

        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
    }

    /**
     * The matching SQL fragment for one column, e.g. `LOWER(name) LIKE ? ESCAPE '!'`.
     *
     * $column is interpolated, so it must never come from user input — callers
     * pass literals. For a specific locale inside the JSON document, pass an
     * arrow path: `title->en` becomes the JSON extraction MySQL needs.
     */
    public static function clause(string $column): string
    {
        if (str_contains($column, '->')) {
            [$col, $path] = explode('->', $column, 2);
            $column = "json_unquote(json_extract(`{$col}`, '$.\"{$path}\"'))";
        }

        return "LOWER({$column}) LIKE ? ESCAPE '!'";
    }
}
