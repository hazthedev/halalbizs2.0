<?php

namespace App\Observers;

use App\Jobs\EmbedProductJob;
use App\Models\Product;
use App\Models\ProductEmbedding;

/**
 * Keeps a product's search vectors fresh (M2.3). Re-embeds on creation or when
 * a field that feeds the embedding changes (name, description, status,
 * category), and prunes the vector on delete. Guarded by config so it's inert
 * when semantic search is off.
 */
class ProductEmbeddingObserver
{
    public function saved(Product $product): void
    {
        if (! config('search.enabled', true)) {
            return;
        }

        if ($product->wasRecentlyCreated || $product->wasChanged(['name', 'description', 'status', 'category_id'])) {
            EmbedProductJob::dispatch($product->id);
        }
    }

    public function deleted(Product $product): void
    {
        ProductEmbedding::where('product_id', $product->id)->delete();
    }
}
