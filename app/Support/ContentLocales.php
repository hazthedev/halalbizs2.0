<?php

namespace App\Support;

use Closure;

/** The locales available on every translatable catalogue/CMS writer. */
final class ContentLocales
{
    public const FALLBACK = 'en';

    public const ALL = ['en', 'ms', 'vi'];

    public const OPTIONAL = ['ms', 'vi'];

    /** @return array{en: string, ms: string, vi: string} */
    public static function blank(): array
    {
        return array_fill_keys(self::ALL, '');
    }

    /** @return array{en: string, ms: string, vi: string} */
    public static function read(object $model, string $field): array
    {
        return collect(self::ALL)
            ->mapWithKeys(fn (string $locale): array => [
                $locale => (string) ($model->getTranslation($field, $locale, false) ?? ''),
            ])
            ->all();
    }

    /**
     * Persist a translated field using English as the fallback. Optional
     * translations are removed when cleared so Spatie can fall back cleanly.
     *
     * @param  array<string, mixed>  $values
     */
    public static function write(
        object $model,
        string $field,
        array $values,
        bool $englishRequired = true,
        ?Closure $transform = null,
    ): void {
        $transform ??= fn (string $value): string => trim($value);
        $clean = collect(self::ALL)->mapWithKeys(function (string $locale) use ($values, $transform): array {
            $value = trim((string) ($values[$locale] ?? ''));

            return [$locale => $value === '' ? '' : $transform($value)];
        });

        if (! $englishRequired && $clean->every(fn (string $value): bool => $value === '')) {
            foreach (self::ALL as $locale) {
                $model->forgetTranslation($field, $locale);
            }

            return;
        }

        $model->setTranslation($field, self::FALLBACK, $clean[self::FALLBACK]);

        foreach (self::OPTIONAL as $locale) {
            if ($clean[$locale] !== '') {
                $model->setTranslation($field, $locale, $clean[$locale]);
            } else {
                $model->forgetTranslation($field, $locale);
            }
        }
    }

    /** @param array<string, mixed> $values */
    public static function payload(array $values): array
    {
        return collect(self::ALL)
            ->mapWithKeys(fn (string $locale): array => [$locale => trim((string) ($values[$locale] ?? ''))])
            ->filter(fn (string $value, string $locale): bool => $locale === self::FALLBACK || $value !== '')
            ->all();
    }
}
