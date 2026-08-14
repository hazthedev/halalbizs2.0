<?php

namespace App\Console\Commands;

use App\Services\Ai\ClaudeClient;
use App\Support\Translation;
use Illuminate\Console\Command;

/**
 * Fill the untranslated entries of lang/<locale>.json via the shared Claude
 * transport (the same one ListingCopyService uses, so timeout/model/mocking
 * stay in one place).
 *
 * Untranslated means value === key, which is what `translatable:export` writes
 * for a newly-discovered string — so the normal cycle is export, then this.
 *
 * A returned line that has lost a `:placeholder` is REFUSED and left in
 * English. That failure is silent at runtime (the page prints ":amount" at the
 * shopper), so it has to be caught here rather than reviewed later.
 */
class TranslateLang extends Command
{
    protected $signature = 'translate:lang
        {locale : Target locale code, e.g. vi}
        {--batch=40 : Strings per request}
        {--limit= : Stop after this many strings (for a costed trial run)}';

    protected $description = 'Machine-translate the untranslated strings in lang/<locale>.json';

    public function handle(ClaudeClient $claude): int
    {
        $locale = $this->argument('locale');
        $path = lang_path("{$locale}.json");

        if (! is_file($path)) {
            $this->error("No {$path} — run `php artisan translatable:export {$locale}` first.");

            return self::FAILURE;
        }

        if (! $claude->configured()) {
            $this->error('ANTHROPIC_API_KEY is not set, so there is nothing to translate with.');

            return self::FAILURE;
        }

        $language = config("locales.{$locale}.name", $locale);
        $strings = json_decode(file_get_contents($path), true);
        $pending = array_keys(array_filter($strings, fn ($value, $key) => $value === $key, ARRAY_FILTER_USE_BOTH));

        if ($limit = $this->option('limit')) {
            $pending = array_slice($pending, 0, (int) $limit);
        }

        if ($pending === []) {
            $this->info("Nothing pending — {$locale} is fully translated.");

            return self::SUCCESS;
        }

        $this->info(sprintf('%d strings to translate into %s (%s).', count($pending), $language, $claude->model()));

        $done = 0;
        $refused = 0;
        $batches = array_chunk($pending, (int) $this->option('batch'));
        $bar = $this->output->createProgressBar(count($batches));

        foreach ($batches as $batch) {
            $translated = $this->translateBatch($claude, $batch, $language);

            foreach ($translated as $source => $translation) {
                // Only accept a string we actually asked for, and only when the
                // placeholders survived the round trip.
                if (! in_array($source, $batch, true) || ! is_string($translation) || trim($translation) === '') {
                    continue;
                }

                if (! Translation::placeholdersMatch($source, $translation)) {
                    $refused++;

                    continue;
                }

                $strings[$source] = $translation;
                $done++;
            }

            // Write after every batch: a failure halfway through keeps the work.
            file_put_contents($path, json_encode($strings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Translated {$done}. Refused {$refused} for dropped placeholders (left in English).");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $batch
     * @return array<string, string>
     */
    private function translateBatch(ClaudeClient $claude, array $batch, string $language): array
    {
        $prompt = "You are translating the interface of HalalBizs, a Malaysian halal e-commerce marketplace, into {$language}.\n\n"
            ."Rules:\n"
            ."- Keep every :placeholder token exactly as written. They are substituted at runtime.\n"
            ."- Keep the tone short and plain, as interface labels, not prose.\n"
            ."- Do not translate the brand name HalalBizs, currency codes, or product brand names.\n"
            ."- Keep any HTML tags, &nbsp; entities and typographic quotes as they appear.\n"
            ."- If a string is already correct in the target language, return it unchanged.\n\n"
            ."Return STRICT JSON only, no markdown fence: an object mapping each English string to its {$language} translation.\n\n"
            .'Strings: '.json_encode($batch, JSON_UNESCAPED_UNICODE);

        $response = $claude->complete($prompt, ['max_tokens' => 8000]);

        if ($response === null) {
            return [];
        }

        // The model occasionally wraps the object in a fence despite the
        // instruction; take the outermost braces rather than failing the batch.
        $start = strpos($response, '{');
        $end = strrpos($response, '}');

        if ($start === false || $end === false) {
            return [];
        }

        return json_decode(substr($response, $start, $end - $start + 1), true) ?: [];
    }
}
