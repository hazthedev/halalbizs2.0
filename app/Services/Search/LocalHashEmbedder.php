<?php

namespace App\Services\Search;

/**
 * Deterministic, network-free text embedder (M2.3). Hashes word unigrams +
 * bigrams into a fixed-dimensional bag-of-features and L2-normalises it, so
 * cosine similarity rewards shared/related vocabulary. Not a true semantic
 * model — it is the offline/test fallback behind the EmbeddingProvider
 * interface; a remote model replaces it in production with no caller changes.
 */
class LocalHashEmbedder implements EmbeddingProvider
{
    public function dimensions(): int
    {
        return max(16, (int) config('search.dimensions', 256));
    }

    public function model(): string
    {
        return 'local-hash-v1';
    }

    public function embedText(string $text): array
    {
        $dim = $this->dimensions();
        $vector = array_fill(0, $dim, 0.0);
        $tokens = $this->tokenize($text);

        foreach ($tokens as $i => $token) {
            $vector[$this->bucket($token, $dim)] += 1.0;

            // Bigrams add a little word-order signal.
            if (isset($tokens[$i + 1])) {
                $vector[$this->bucket($token.'_'.$tokens[$i + 1], $dim)] += 0.5;
            }
        }

        return $this->normalize($vector);
    }

    /** @return array<int, string> */
    private function tokenize(string $text): array
    {
        $text = mb_strtolower(strip_tags($text));
        preg_match_all('/[\p{L}\p{N}]+/u', $text, $matches);

        return array_values(array_filter($matches[0], fn (string $token) => mb_strlen($token) >= 2));
    }

    private function bucket(string $token, int $dim): int
    {
        return (int) (crc32($token) % $dim);
    }

    /**
     * @param  array<int, float>  $vector
     * @return array<int, float>
     */
    private function normalize(array $vector): array
    {
        $norm = sqrt(array_sum(array_map(fn (float $x) => $x * $x, $vector)));

        if ($norm <= 0.0) {
            return $vector;
        }

        return array_map(fn (float $x) => $x / $norm, $vector);
    }
}
