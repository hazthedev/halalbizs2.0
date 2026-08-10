<?php

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('renders an approved store page with the store name', function () {
    $store = Store::factory()->approved()->create(['name' => 'Kedai Pak Mat']);

    $this->get('/s/'.$store->slug)
        ->assertOk()
        ->assertSee('Kedai Pak Mat');
});

/**
 * The banner was a WRITE-ONLY field: seller settings accepted the upload, stored
 * it in the `banner` collection, rendered a preview back to the seller and told
 * them it appears "on your store page" — and the store page had no banner markup
 * at all. Nothing failed; the image simply went nowhere, which is why it survived
 * to a live report rather than a bug.
 *
 * These two assert the round trip in both directions, because only the pair
 * catches it: "renders when set" alone passes against a banner hardcoded into
 * every page.
 */
it('renders the seller-uploaded banner on the store page', function () {
    Storage::fake('public');

    $store = Store::factory()->approved()->create();
    $store->addMedia(UploadedFile::fake()->image('storefront-strip.jpg', 1600, 400))
        ->toMediaCollection('banner');

    // Assert the EXACT media URL, not a fragment of the filename. The first
    // version of this test used a file called shopfront.jpg and asserted
    // `assertSee('shopfront')` — which passed with the banner markup deleted,
    // because the storefront layout renders <html class="shopfront"> on every
    // page. A substring assertion against a page you have not read is a
    // coin-flip; the URL comes from the model, so it cannot collide.
    $url = $store->getFirstMediaUrl('banner', 'card');

    expect($url)->not->toBe('');

    $this->get('/s/'.$store->slug)
        ->assertOk()
        ->assertSee($url, false);
});

it('renders no banner element when the seller has not uploaded one', function () {
    $store = Store::factory()->approved()->create();

    $this->get('/s/'.$store->slug)
        ->assertOk()
        ->assertDontSee('aspect-[4/1]', false);
});

it('returns 404 for a pending store', function () {
    $store = Store::factory()->create();

    $this->get('/s/'.$store->slug)->assertNotFound();
});

it('shows the holiday banner when holiday mode is active', function () {
    $store = Store::factory()->approved()->create(['holiday_mode' => true]);

    $this->get('/s/'.$store->slug)
        ->assertOk()
        ->assertSee('This shop is on holiday — orders are paused.');
});

it('lists only the store\'s live products', function () {
    $store = Store::factory()->approved()->create();
    $live = Product::factory()->create([
        'store_id' => $store->id,
        'name' => ['en' => 'Visible Product Here', 'ms' => 'Visible Product Here'],
    ]);
    Product::factory()->create([
        'store_id' => $store->id,
        'status' => ProductStatus::Draft,
        'name' => ['en' => 'Hidden Draft Product', 'ms' => 'Hidden Draft Product'],
    ]);

    $this->get('/s/'.$store->slug)
        ->assertOk()
        ->assertSee('Visible Product Here')
        ->assertDontSee('Hidden Draft Product');
});
