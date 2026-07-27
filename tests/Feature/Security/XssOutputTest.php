<?php

use App\Enums\SubOrderStatus;
use App\Livewire\Seller\Products\Form;
use App\Livewire\Storefront\Account\ReviewOrder;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Security-audit regression tests (T1 — XSS output encoding + upload
 * hardening). Each test is written to fail against the pre-fix code; see the
 * executor report for the observed failure output.
 */
function xssSeller(): User
{
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('seller');

    Store::factory()->approved()->create(['user_id' => $user->id]);

    return $user;
}

function xssCategory(): Category
{
    return Category::factory()->create();
}

function xssBuyerWithReviewableItem(): array
{
    $buyer = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $buyer->id]);
    $subOrder = SubOrder::factory()->status(SubOrderStatus::Completed)->create(['order_id' => $order->id]);
    $product = Product::factory()->create(['store_id' => $subOrder->store_id]);

    $item = $subOrder->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => $product->variants()->first()->id,
        'product_name' => $product->getTranslation('name', 'en'),
        'unit_price_sen' => 2500,
        'qty' => 1,
        'line_total_sen' => 2500,
    ]);

    return [$buyer, $subOrder, $item];
}

// ── C1: JSON-LD </script> breakout ──────────────────────────────────────

it('cannot break out of the JSON-LD script tag via a malicious product name', function () {
    $maliciousName = 'x</script><script>alert(1)</script>';

    $product = Product::factory()->create([
        'name' => ['en' => $maliciousName, 'ms' => $maliciousName],
    ]);
    $product->variants()->first()->update(['stock' => 5]);

    $html = $this->get('/p/'.$product->slug)->assertOk()->getContent();

    expect($html)->not->toContain('</script><script>alert(1)</script>');

    // The JSON-LD block must still be exactly one well-formed script element
    // whose content parses as valid JSON and round-trips the original name.
    expect($html)->toMatch('#<script type="application/ld\+json">.*?</script>#s');

    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
    $decoded = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);

    expect($decoded['name'])->toBe($maliciousName);
});

// ── C2: description sanitizer must strip event-handler attributes ──────

it('strips event-handler attributes and script tags from a saved description but keeps allowed formatting', function () {
    $seller = xssSeller();
    $category = xssCategory();

    Livewire::actingAs($seller)
        ->test(Form::class)
        ->set('name.en', 'Sanitizer Coverage Item')
        ->set('categoryTop', $category->id)
        ->set('price', '9.90')
        ->set('description.en', '<p>Great <strong>value</strong></p><em onmouseover="alert(1)">hi</em><script>alert(2)</script>')
        ->call('saveDraft')
        ->assertHasNoErrors();

    $product = Product::query()->where('store_id', $seller->store->id)->firstOrFail();
    $description = $product->getTranslation('description', 'en');

    // The <script> TAG must go (it can never execute once unwrapped), but its
    // text content is preserved same as strip_tags()'s existing behaviour —
    // the security property under test is the missing tag/attribute, not the
    // leftover inert text.
    expect($description)->not->toContain('onmouseover')
        ->and($description)->not->toContain('<script')
        ->and($description)->not->toContain('</script')
        ->and($description)->toContain('<p>Great <strong>value</strong></p>')
        ->and($description)->toContain('<em>hi</em>');
});

it('strips markup entirely from the product name even though it only ever renders as plain text', function () {
    $seller = xssSeller();
    $category = xssCategory();

    Livewire::actingAs($seller)
        ->test(Form::class)
        ->set('name.en', 'Clean<script>alert(3)</script> Name')
        ->set('categoryTop', $category->id)
        ->set('price', '9.90')
        ->call('saveDraft')
        ->assertHasNoErrors();

    $product = Product::query()->where('store_id', $seller->store->id)->firstOrFail();

    expect($product->getTranslation('name', 'en'))->not->toContain('<script');
});

// ── AL-M2: uploaded photo extension must come from server-side detection ──

it('does not store a review photo with the client-supplied .html extension', function () {
    Storage::fake('public');

    [$buyer, $subOrder, $item] = xssBuyerWithReviewableItem();

    Livewire::actingAs($buyer)
        ->test(ReviewOrder::class, ['subOrder' => $subOrder])
        ->set("ratings.{$item->id}", 5)
        // Real content is a valid JPEG (Laravel's fake image generator always
        // writes real image bytes); explicitly reporting the real content
        // type mirrors what server-side sniffing sees in production — the
        // fake helper otherwise derives its mime type from the ".html" name,
        // which no real content-based detector would do.
        ->set("photos.{$item->id}", [UploadedFile::fake()->image('evil.html', 100, 100)->mimeType('image/jpeg')])
        ->call('submit', $item->id)
        ->assertHasNoErrors();

    $review = Review::query()->where('order_item_id', $item->id)->firstOrFail();
    $fileName = $review->getMedia('photos')->first()->file_name;

    expect($fileName)->not->toEndWith('.html')
        ->and($fileName)->not->toEndWith('.htm')
        ->and($fileName)->toEndWith('.jpg');
});
