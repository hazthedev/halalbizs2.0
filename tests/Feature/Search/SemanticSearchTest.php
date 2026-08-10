<?php

use App\Enums\ProductStatus;
use App\Livewire\Storefront\Listing;
use App\Livewire\Storefront\VisualSearch;
use App\Models\Product;
use App\Models\ProductEmbedding;
use App\Services\Search\EmbeddingProvider;
use App\Services\Search\ImageEmbedder;
use App\Services\VectorSearchService;
use Livewire\Livewire;

beforeEach(fn () => config(['search.enabled' => true]));

function tmpImage(int $r, int $g, int $b): string
{
    $img = imagecreatetruecolor(40, 40);
    imagefill($img, 0, 0, imagecolorallocate($img, $r, $g, $b));
    $path = tempnam(sys_get_temp_dir(), 'vs').'.png';
    imagepng($img, $path);
    imagedestroy($img);

    return $path;
}

test('the local text embedder is deterministic and L2-normalised', function () {
    $embedder = app(EmbeddingProvider::class);

    $a = $embedder->embedText('halal honey jar');
    $b = $embedder->embedText('halal honey jar');

    expect($a)->toBe($b)
        ->and(count($a))->toBe($embedder->dimensions())
        ->and(round(array_sum(array_map(fn ($x) => $x * $x, $a)), 5))->toBe(1.0);
});

test('creating a live product builds its embedding; a draft gets none', function () {
    $live = Product::factory()->create();
    $draft = Product::factory()->create(['status' => ProductStatus::Draft]);

    expect(ProductEmbedding::where('product_id', $live->id)->exists())->toBeTrue()
        ->and(ProductEmbedding::where('product_id', $draft->id)->exists())->toBeFalse();
});

/**
 * ⚠ The descriptions are pinned deliberately. `embeddingText()` folds name +
 * description + category + metafields into one string, and LocalHashEmbedder
 * buckets every token with `crc32($token) % 256`. A random factory description
 * therefore throws ~40 extra tokens into a 256-slot space, and often enough one
 * collides with a "honey"/"jar" bucket and lifts the WALLET above the honey —
 * measured at ~2% of runs, which is exactly the intermittent failure recorded
 * as F-001 (and it is not parallel-only, as first thought). Pinning the text
 * removes the randomness this assertion was never about; the collision floor
 * itself is a property of the local embedder, not a bug in this test.
 */
test('semantic search ranks a relevant product first', function () {
    $honey = Product::factory()->create([
        'name' => ['en' => 'Organic Acacia Honey Jar', 'ms' => 'Madu Acacia'],
        'description' => ['en' => 'Honey jar.', 'ms' => 'Balang madu.'],
    ]);
    Product::factory()->create([
        'name' => ['en' => 'Leather Bifold Wallet', 'ms' => 'Dompet Kulit'],
        'description' => ['en' => 'Leather wallet.', 'ms' => 'Dompet kulit.'],
    ]);

    $ids = app(VectorSearchService::class)->semanticSearch('honey jar');

    expect($ids)->not->toBeEmpty()
        ->and($ids[0])->toBe($honey->id);
});

/**
 * The guards below exist because the dot product used to truncate to
 * min(count(a), count(b)). That made two different failures produce confident,
 * meaningless rankings with nothing in the logs:
 *
 *   1. a dimension change without re-running `search:embed`, and
 *   2. RemoteEmbedder falling back to the local hash embedder on a timeout,
 *      scoring a hash vector against stored semantic vectors.
 *
 * Both now exclude the row instead. An empty result is recoverable; a wrong
 * order nobody can detect is not.
 */
test('a stale index (wrong dimensions) is excluded rather than scored on a truncated prefix', function () {
    $product = Product::factory()->create(['name' => ['en' => 'Madu Tulen', 'ms' => 'Madu Tulen']]);

    // Simulate the index built before a dimension change: right model, old size.
    //
    // ⚠ The query term is "tulen" ON PURPOSE. crc32('tulen') % 4096 = 69, i.e.
    // inside the first 256 entries — so the OLD truncating dot() really does
    // score this row above zero. An earlier version of this test used a
    // 256-length stub and a 'honey jar' query whose live buckets all sat above
    // index 255; the dot came to 0.0, the row was dropped by the score > 0
    // filter, and the test passed against the unguarded code. It proved nothing.
    $embedder = app(EmbeddingProvider::class);
    $stale = array_fill(0, 256, 0.0);
    $stale[69] = 1.0;

    ProductEmbedding::where('product_id', $product->id)->update([
        'text_vector' => $stale,
        'dimensions' => 256,
        'model' => $embedder->model(),
    ]);

    expect($embedder->dimensions())->not->toBe(256) // guard the guard
        ->and(app(VectorSearchService::class)->semanticSearch('tulen'))->toBe([]);
});

test('vectors from a different model are excluded even at the same dimensions', function () {
    $product = Product::factory()->create(['name' => ['en' => 'Organic Acacia Honey Jar', 'ms' => 'Madu Acacia']]);

    $embedder = app(EmbeddingProvider::class);
    ProductEmbedding::where('product_id', $product->id)->update([
        'model' => 'voyage-3', // as if embedded remotely, then the query fell back to local
    ]);

    expect(app(VectorSearchService::class)->semanticSearch('honey jar'))->toBe([]);
});

test('visual search still works — the model filter must not leak onto the image column', function () {
    // Regression guard: `model`/`dimensions` describe the TEXT embedder, so
    // applying that filter to image_vector would exclude every row and silently
    // kill visual search while every text test stayed green.
    $red = Product::factory()->create(['name' => ['en' => 'Red Kurma Pack', 'ms' => 'Kurma Merah']]);
    $blue = Product::factory()->create(['name' => ['en' => 'Blue Kurma Pack', 'ms' => 'Kurma Biru']]);

    ProductEmbedding::where('product_id', $red->id)
        ->update(['image_vector' => app(ImageEmbedder::class)->embed(tmpImage(255, 0, 0))]);
    ProductEmbedding::where('product_id', $blue->id)
        ->update(['image_vector' => app(ImageEmbedder::class)->embed(tmpImage(0, 0, 255))]);

    $ids = app(VectorSearchService::class)->visualSearch(tmpImage(250, 10, 10));

    expect($ids)->not->toBeEmpty()
        ->and($ids[0])->toBe($red->id);
});

test('the listing smart mode renders semantic results', function () {
    config(['scout.driver' => 'collection']);
    $honey = Product::factory()->create(['name' => ['en' => 'Pure Honey Delight', 'ms' => 'Madu Tulen']]);

    // Keyword Scout would miss this (no "honey" token), but the semantic vector
    // still relates it — proving smart mode runs the vector path, not Scout.
    Livewire::test(Listing::class)
        ->set('q', 'honey')
        ->set('mode', 'smart')
        ->assertSee('Pure Honey Delight');

    expect(app(VectorSearchService::class)->semanticSearch('honey'))->toContain($honey->id);
});

test('the image embedder produces a 64-bin histogram', function () {
    $vector = app(ImageEmbedder::class)->embed(tmpImage(255, 0, 0));

    expect($vector)->toHaveCount(64)
        ->and(round(array_sum(array_map(fn ($x) => $x * $x, $vector)), 5))->toBe(1.0);
});

test('visual search ranks the closest-coloured product first', function () {
    $red = Product::factory()->create();
    $blue = Product::factory()->create();
    $embedder = app(ImageEmbedder::class);

    ProductEmbedding::where('product_id', $red->id)->update(['image_vector' => $embedder->embed(tmpImage(255, 0, 0))]);
    ProductEmbedding::where('product_id', $blue->id)->update(['image_vector' => $embedder->embed(tmpImage(0, 0, 255))]);

    $ids = app(VectorSearchService::class)->visualSearch(tmpImage(250, 12, 9), 10);

    expect($ids[0])->toBe($red->id);
});

test('the backfill command embeds live products', function () {
    config(['search.enabled' => false]); // suppress the create-time observer
    $product = Product::factory()->create();
    expect(ProductEmbedding::count())->toBe(0);

    config(['search.enabled' => true]);
    $this->artisan('search:embed')->assertSuccessful();

    expect(ProductEmbedding::where('product_id', $product->id)->exists())->toBeTrue();
});

test('semantic search is inert when disabled', function () {
    config(['search.enabled' => false]);
    Product::factory()->create(['name' => ['en' => 'Honey Jar', 'ms' => 'Madu']]);

    expect(app(VectorSearchService::class)->semanticSearch('honey'))->toBe([]);
});

test('the visual search page renders and gates on config', function () {
    Livewire::test(VisualSearch::class)->assertOk()->assertSee(__('Search by image'));

    config(['search.enabled' => false]);
    Livewire::test(VisualSearch::class)->assertStatus(404);
});
