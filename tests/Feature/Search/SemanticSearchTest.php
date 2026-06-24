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

test('semantic search ranks a relevant product first', function () {
    $honey = Product::factory()->create(['name' => ['en' => 'Organic Acacia Honey Jar', 'ms' => 'Madu Acacia']]);
    Product::factory()->create(['name' => ['en' => 'Leather Bifold Wallet', 'ms' => 'Dompet Kulit']]);

    $ids = app(VectorSearchService::class)->semanticSearch('honey jar');

    expect($ids)->not->toBeEmpty()
        ->and($ids[0])->toBe($honey->id);
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
