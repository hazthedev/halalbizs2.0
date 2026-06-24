<?php

namespace App\Services\Search;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Production text embedder (M2.3): calls a configured embedding endpoint
 * (Voyage / OpenAI-compatible) and L2-normalises the result. Config-gated — if
 * no URL/key is set, or the call fails, it transparently falls back to the
 * deterministic local embedder so search never hard-fails.
 */
class RemoteEmbedder implements EmbeddingProvider
{
    public function __construct(private LocalHashEmbedder $fallback) {}

    public function configured(): bool
    {
        return ! empty(config('search.remote.url')) && ! empty(config('search.remote.key'));
    }

    public function dimensions(): int
    {
        return $this->fallback->dimensions();
    }

    public function model(): string
    {
        return $this->configured() ? (string) config('search.remote.model', 'remote') : $this->fallback->model();
    }

    public function embedText(string $text): array
    {
        if (! $this->configured()) {
            return $this->fallback->embedText($text);
        }

        try {
            $response = Http::withHeaders(['Authorization' => 'Bearer '.config('search.remote.key')])
                ->timeout((int) config('search.remote.timeout', 20))
                ->post((string) config('search.remote.url'), [
                    'model' => config('search.remote.model'),
                    'input' => $text,
                ])->throw();

            $vector = $response->json('data.0.embedding') ?? $response->json('embeddings.0') ?? [];

            if (! is_array($vector) || $vector === []) {
                return $this->fallback->embedText($text);
            }

            return $this->normalize(array_map('floatval', $vector));
        } catch (Throwable $e) {
            Log::warning('Remote embedding failed; using local embedder.', ['error' => $e->getMessage()]);

            return $this->fallback->embedText($text);
        }
    }

    /**
     * @param  array<int, float>  $vector
     * @return array<int, float>
     */
    private function normalize(array $vector): array
    {
        $norm = sqrt(array_sum(array_map(fn (float $x) => $x * $x, $vector)));

        return $norm <= 0.0 ? $vector : array_map(fn (float $x) => $x / $norm, $vector);
    }
}
