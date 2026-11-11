<?php

/**
 * Product page extras for the app (gap item 9): COD flag, shipping row, seller
 * card, specifications and the Q&A tab. Mirrors the web PDP and ProductQuestions.
 */

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\Store;
use App\Models\User;
use App\Notifications\ProductQuestionAsked;
use App\Settings\CodSettings;
use App\Settings\GeneralSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(fn () => $this->seed(RoleSeeder::class));

function pxProduct(array $attrs = [], array $store = []): Product
{
    return Product::factory()->create(array_merge([
        'store_id' => Store::factory()->approved()->create(array_merge(['state' => 'Selangor'], $store))->id,
    ], $attrs));
}

function pxCod(bool $platform, bool $purchasing = true): void
{
    $general = app(GeneralSettings::class);
    $general->purchasing_enabled = $purchasing;
    $general->save();
    $cod = app(CodSettings::class);
    $cod->enabled = $platform;
    $cod->save();
}

test('detail carries the cod flag only when purchasing, the product and the platform all allow it', function () {
    $product = pxProduct(['cod_enabled' => true]);

    pxCod(true);
    $this->getJson("/api/v1/products/{$product->slug}")->assertJsonPath('data.cod_available', true);

    pxCod(false);
    $this->getJson("/api/v1/products/{$product->slug}")->assertJsonPath('data.cod_available', false);

    pxCod(true);
    $product->update(['cod_enabled' => false]);
    $this->getJson("/api/v1/products/{$product->slug}")->assertJsonPath('data.cod_available', false);

    pxCod(true, purchasing: false);
    $product->update(['cod_enabled' => true]);
    $this->getJson("/api/v1/products/{$product->slug}")->assertJsonPath('data.cod_available', false);
});

test('detail carries ships_from and the specification rows', function () {
    $brand = Brand::factory()->create(['name' => 'Madu Kita']);
    $product = pxProduct(['brand_id' => $brand->id, 'weight_grams' => 750, 'condition' => 'used']);

    $this->getJson("/api/v1/products/{$product->slug}")
        ->assertOk()
        ->assertJsonPath('data.ships_from', 'Selangor')
        ->assertJsonPath('data.specs.brand', 'Madu Kita')
        ->assertJsonPath('data.specs.condition', 'Used')
        ->assertJsonPath('data.specs.weight_grams', 750)
        ->assertJsonPath('data.specs.category', $product->category->getTranslation('name', 'en'));
});

test('ships_from falls back to Malaysia and a brandless product has a null brand', function () {
    $product = pxProduct(['brand_id' => null], ['state' => null]);

    $this->getJson("/api/v1/products/{$product->slug}")
        ->assertJsonPath('data.ships_from', 'Malaysia')
        ->assertJsonPath('data.specs.brand', null);
});

test('detail carries the seller card, list rows stay lean', function () {
    $store = Store::factory()->approved()->create([
        'state' => 'Johor', 'rating_avg' => 4.5, 'rating_count' => 12,
        'service_rating_avg' => 4.2, 'service_rating_count' => 3,
    ]);
    $product = Product::factory()->create(['store_id' => $store->id]);

    $this->getJson("/api/v1/products/{$product->slug}")
        ->assertJsonPath('data.store.slug', $store->slug)
        ->assertJsonPath('data.store.verified', true)
        ->assertJsonPath('data.store.rating_avg', 4.5)
        ->assertJsonPath('data.store.rating_count', 12)
        ->assertJsonPath('data.store.service_rating_avg', 4.2)
        ->assertJsonPath('data.store.service_rating_count', 3)
        ->assertJsonPath('data.store.state', 'Johor')
        ->assertJsonPath('data.store.product_count', $store->products()->live()->count())
        ->assertJsonPath('data.store.joined_at', $store->approved_at->toIso8601String());

    $list = $this->getJson('/api/v1/products')->json('data.0.store');
    expect($list)->not->toHaveKey('verified')->and($list)->toHaveKeys(['name', 'slug']);
});

test('questions list shows visible ones newest first with the answer, hides hidden', function () {
    $product = pxProduct();
    $asker = User::factory()->create(['name' => 'Aisyah Yusof']);
    $mk = fn (array $a) => ProductQuestion::create(array_merge(['product_id' => $product->id, 'store_id' => $product->store_id, 'user_id' => $asker->id], $a));
    $mk(['question' => 'Is this halal certified?', 'answer' => 'Yes, JAKIM.', 'answered_at' => now()]);
    $mk(['question' => 'Ships to Sabah please?']);
    $mk(['question' => 'Hidden one here ok', 'is_hidden' => true]);

    $res = $this->getJson("/api/v1/products/{$product->slug}/questions")->assertOk();

    $res->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.question', 'Ships to Sabah please?')
        ->assertJsonPath('data.0.answer', null)
        ->assertJsonPath('data.0.asker_name', 'Aisyah Y.')
        ->assertJsonPath('data.1.answer', 'Yes, JAKIM.');
});

test('a non-live product has no questions endpoint', function () {
    $product = pxProduct(['status' => 'draft']);

    $this->getJson("/api/v1/products/{$product->slug}/questions")->assertNotFound();
});

test('asking needs a sign in, 10 to 500 characters, and notifies the seller', function () {
    Notification::fake();
    $product = pxProduct();
    $url = "/api/v1/products/{$product->slug}/questions";

    $this->postJson($url, ['question' => 'Does it ship to Sabah?'])->assertUnauthorized();

    $this->actingAs($user = User::factory()->create(), 'sanctum');
    $this->postJson($url, [])->assertStatus(422)->assertJsonPath('error.fields.question.0', 'Type your question first.');
    $this->postJson($url, ['question' => 'too short'])->assertStatus(422)
        ->assertJsonPath('error.fields.question.0', 'Add a little more detail (at least 10 characters).');
    $this->postJson($url, ['question' => str_repeat('a', 501)])->assertStatus(422);

    $this->postJson($url, ['question' => '  Does it ship to Sabah?  '])
        ->assertCreated()
        ->assertJsonPath('data.question', 'Does it ship to Sabah?')
        ->assertJsonPath('data.answer', null);

    $this->assertDatabaseHas('product_questions', ['product_id' => $product->id, 'store_id' => $product->store_id, 'user_id' => $user->id, 'question' => 'Does it ship to Sabah?']);
    Notification::assertSentTo($product->store->user, ProductQuestionAsked::class);
});

test('asking is limited to 3 a minute per buyer', function () {
    $product = pxProduct();
    $user = User::factory()->create();
    RateLimiter::clear('ask-question:'.$user->id);
    $this->actingAs($user, 'sanctum');

    foreach (range(1, 3) as $i) {
        $this->postJson("/api/v1/products/{$product->slug}/questions", ['question' => "Question number {$i} here"])->assertCreated();
    }

    $this->postJson("/api/v1/products/{$product->slug}/questions", ['question' => 'One question too many'])
        ->assertStatus(429)->assertJsonPath('error.code', 'too_many_attempts');
    RateLimiter::clear('ask-question:'.$user->id);
});
