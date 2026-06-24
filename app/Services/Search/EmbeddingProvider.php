<?php

namespace App\Services\Search;

/**
 * Text embedding contract (M2.3). Implementations return a NORMALISED float
 * vector so similarity is a plain dot product. A deterministic local driver
 * keeps dev/tests offline; a remote driver plugs a real model in production.
 */
interface EmbeddingProvider
{
    /** @return array<int, float> normalised embedding of the text */
    public function embedText(string $text): array;

    public function dimensions(): int;

    public function model(): string;
}
