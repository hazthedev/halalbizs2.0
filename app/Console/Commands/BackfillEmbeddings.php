<?php

namespace App\Console\Commands;

use App\Jobs\EmbedProductJob;
use App\Models\Product;
use Illuminate\Console\Command;

/**
 * M2.3 — (re)builds search embeddings for every live product. Run after a
 * driver/model change or to seed vectors for existing catalogue.
 */
class BackfillEmbeddings extends Command
{
    protected $signature = 'search:embed';

    protected $description = 'Build semantic + visual search embeddings for live products';

    public function handle(): int
    {
        $count = 0;

        Product::query()->live()->pluck('id')->each(function ($id) use (&$count) {
            EmbedProductJob::dispatchSync((int) $id);
            $count++;
        });

        $this->info("Embedded {$count} products.");

        return self::SUCCESS;
    }
}
