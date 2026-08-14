<?php

use App\Support\Translation;

/**
 * The guard rail for bulk translation. A translated line that drops a `:name`
 * placeholder does not throw and does not fail any other test — the page simply
 * renders the literal ":amount" to the shopper. These files are about to be
 * filled by a model, so the parity check has to exist before the filling does.
 */
function localeJsonFiles(): array
{
    return collect(glob(lang_path('*.json')))
        ->reject(fn (string $path) => basename($path) === 'persistent-strings.json')
        ->all();
}

test('every translation keeps the placeholders its English source declared', function () {
    $offenders = [];

    foreach (localeJsonFiles() as $path) {
        foreach (json_decode(file_get_contents($path), true) as $source => $translation) {
            if (! Translation::placeholdersMatch($source, $translation)) {
                $offenders[] = sprintf(
                    '%s: "%s" [%s] → "%s" [%s]',
                    basename($path),
                    $source,
                    implode(',', Translation::placeholders($source)),
                    $translation,
                    implode(',', Translation::placeholders($translation)),
                );
            }
        }
    }

    expect($offenders)->toBe([]);
});

test('no translation file is missing keys the others have', function () {
    $sets = collect(localeJsonFiles())
        ->mapWithKeys(fn (string $p) => [basename($p) => array_keys(json_decode(file_get_contents($p), true))]);

    // Every locale file is written by the same exporter run, so a divergence
    // means one was hand-edited or an export was only run for one language.
    expect($sets->map(fn (array $keys) => count($keys))->unique()->count())->toBe(1);
});

test('the persistent-strings list keeps the framework strings the exporter cannot see', function () {
    // These live in Laravel's own notification/pagination/error views, which the
    // exporter does not scan — without this file every export silently drops them
    // and password-reset mail reverts to English.
    $persistent = json_decode(file_get_contents(lang_path('persistent-strings.json')), true);

    expect($persistent)->toContain('Whoops!', 'Regards,', 'Reset Password', 'Pagination Navigation');

    foreach (localeJsonFiles() as $path) {
        $keys = array_keys(json_decode(file_get_contents($path), true));
        expect(array_diff($persistent, $keys))->toBe([], basename($path).' lost persistent strings');
    }
});

test('placeholder detection reads names, not punctuation', function () {
    expect(Translation::placeholders('Current streak: :n days'))->toBe(['n'])
        ->and(Translation::placeholders('Closes at 12:30'))->toBe([])
        ->and(Translation::placeholders(':joined of :target joined'))->toBe(['joined', 'target'])
        ->and(Translation::placeholdersMatch(':amount off', 'Giảm :amount'))->toBeTrue()
        ->and(Translation::placeholdersMatch(':amount off', 'Giảm giá'))->toBeFalse();
});
