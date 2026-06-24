<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\ProductEmbedding;
use App\Services\Search\EmbeddingProvider;
use App\Services\Search\ImageEmbedder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * (Re)builds a product's text + image search vectors (M2.3). Only live products
 * carry an embedding; non-live/deleted ones are pruned so semantic results
 * never surface stale or hidden items.
 */
class EmbedProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $productId)
    {
        $this->onQueue('search');
    }

    public function handle(EmbeddingProvider $embedder, ImageEmbedder $imageEmbedder): void
    {
        if (! config('search.enabled', true)) {
            return;
        }

        $product = Product::with(['category', 'metafields', 'media'])->find($this->productId);

        if ($product === null || ! $product->isLive()) {
            ProductEmbedding::where('product_id', $this->productId)->delete();

            return;
        }

        $imagePath = $product->getFirstMedia('images')?->getPath();

        ProductEmbedding::updateOrCreate(
            ['product_id' => $product->id],
            [
                'text_vector' => $embedder->embedText($product->embeddingText()),
                'image_vector' => $imagePath !== null ? $imageEmbedder->embed($imagePath) : null,
                'model' => $embedder->model(),
                'dimensions' => $embedder->dimensions(),
            ],
        );
    }
}
