<?php

namespace App\Observers;

use App\Jobs\EmbedProductJob;
use App\Models\ProductMetafield;

/**
 * Re-embeds a product when its searchable metafields change (M2.3) so trust
 * signals (ingredients, halal body, SIRIM …) flow into semantic search.
 */
class ProductMetafieldObserver
{
    public function saved(ProductMetafield $metafield): void
    {
        $this->reembed($metafield);
    }

    public function deleted(ProductMetafield $metafield): void
    {
        $this->reembed($metafield);
    }

    private function reembed(ProductMetafield $metafield): void
    {
        if (config('search.enabled', true)) {
            EmbedProductJob::dispatch($metafield->product_id);
        }
    }
}
