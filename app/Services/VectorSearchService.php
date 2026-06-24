<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\ProductEmbedding;
use App\Services\Search\EmbeddingProvider;
use App\Services\Search\ImageEmbedder;

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

        return $this->rankByVector($this->embedder->embedText($query), 'text_vector', $limit);
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
    private function rankByVector(array $query, string $column, int $limit): array
    {
        if ($query === []) {
            return [];
        }

        return ProductEmbedding::query()
            ->whereNotNull($column)
            ->whereHas('product', fn ($q) => $q->where('status', ProductStatus::Live))
            ->get(['product_id', $column])
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
        $sum = 0.0;
        $n = min(count($a), count($b));

        for ($i = 0; $i < $n; $i++) {
            $sum += (float) $a[$i] * (float) $b[$i];
        }

        return $sum;
    }
}
