<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\ProductEmbedding;
use App\Services\Search\EmbeddingProvider;
use App\Services\Search\ImageEmbedder;
use Illuminate\Support\Facades\Log;

/**
 * Semantic + visual product ranking (M2.3). Embeds the query and scores it
 * against stored product vectors by dot product (cosine, since vectors are
 * normalised), newest-relevance first. Runs the comparison in PHP over live
 * products' vectors — fine for SQLite/dev; production would push this into a
 * vector index. Checkout-safe: read-only over live catalogue data only.
 */
class VectorSearchService
{
    public function __construct(
        private EmbeddingProvider $embedder,
        private ImageEmbedder $imageEmbedder,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('search.enabled', true);
    }

    /**
     * @return array<int, int> live product ids, most-relevant first
     */
    public function semanticSearch(string $query, int $limit = 60): array
    {
        $query = trim($query);

        if (! $this->enabled() || $query === '') {
            return [];
        }

        return $this->rankByVector($this->embedder->embedText($query), 'text_vector', $limit, matchTextEmbedder: true);
    }

    /**
     * @return array<int, int> live product ids ranked by visual similarity
     */
    public function visualSearch(?string $imagePath, int $limit = 40): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $vector = $this->imageEmbedder->embed($imagePath);

        return $vector === null ? [] : $this->rankByVector($vector, 'image_vector', $limit);
    }

    /**
     * @param  array<int, float>  $query
     * @return array<int, int>
     */
    private function rankByVector(array $query, string $column, int $limit, bool $matchTextEmbedder = false): array
    {
        if ($query === []) {
            return [];
        }

        $rows = ProductEmbedding::query()
            ->whereNotNull($column)
            // Only compare vectors from the SAME embedding space. Without this
            // the dot product silently produced numbers for incompatible pairs:
            // dot() truncated to the shorter vector, so a 4096-dim query scored
            // fine against stale 256-dim rows, and a query that fell back to the
            // local hash embedder (RemoteEmbedder does that on any timeout)
            // scored against remote semantic vectors. Both give plausible,
            // meaningless rankings with nothing in the logs. An excluded row is
            // recoverable; a wrong ranking nobody can see is not.
            //
            // ⚠ Text only. `model` and `dimensions` on this table describe the
            // TEXT embedder (EmbedProductJob writes them from $embedder), while
            // image_vector is a colour histogram in its own space — filtering
            // visual search by the text model would exclude every row. The
            // image side is protected by dot()'s equal-length refusal instead.
            ->when($matchTextEmbedder, fn ($q) => $q
                ->where('dimensions', count($query))
                ->where('model', $this->embedder->model()))
            ->whereHas('product', fn ($q) => $q->where('status', ProductStatus::Live))
            ->get(['product_id', $column]);

        // A live catalogue with vectors, none of which match the current
        // embedder, means the index is stale — a dimension or driver change
        // without a re-run of `search:embed`. Say so: the symptom otherwise is
        // "smart search returns nothing" with no cause anywhere.
        if ($matchTextEmbedder && $rows->isEmpty()) {
            $total = ProductEmbedding::whereNotNull($column)->count();

            if ($total > 0) {
                Log::warning('Semantic search index is stale — no vectors match the active embedder. Run `php artisan search:embed`.', [
                    'expected_model' => $this->embedder->model(),
                    'expected_dimensions' => count($query),
                    'stored_vectors' => $total,
                ]);
            }

            return [];
        }

        return $rows
            ->map(fn (ProductEmbedding $row) => [
                'id' => $row->product_id,
                'score' => $this->dot($query, (array) $row->{$column}),
            ])
            ->filter(fn (array $row) => $row['score'] > 0.0)
            ->sortByDesc('score')
            ->take($limit)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    private function dot(array $a, array $b): float
    {
        // Length mismatch is now a caller bug — rankByVector filters on
        // dimensions — so refuse rather than truncate. Truncating is what let
        // mismatched vectors score for months without a single error.
        if (count($a) !== count($b)) {
            return 0.0;
        }

        $sum = 0.0;

        for ($i = 0, $n = count($a); $i < $n; $i++) {
            $sum += (float) $a[$i] * (float) $b[$i];
        }

        return $sum;
    }
}
