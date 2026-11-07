<?php

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\User;
use App\Settings\GeneralSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Testing\TestCase;

beforeEach(fn () => $this->seed(RoleSeeder::class));

/** @return array{0: User, 1: TestCase} */
function apiCartUser(): array
{
    $user = User::factory()->create();
    $user->assignRole('buyer');

    app('auth')->forgetGuards();

    return [$user, test()->withToken($user->createToken('pest-phone')->plainTextToken)];
}

function apiCartProduct(int $stock = 5, int $priceSen = 1000): Product
{
    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->variants->first()->update(['price_sen' => $priceSen, 'sale_price_sen' => null, 'stock' => $stock]);

    return $product;
}

test('cart add, update, select and remove round-trip with totals in sen', function () {
    [, $http] = apiCartUser();
    $product = apiCartProduct(stock: 5, priceSen: 1250);
    $variant = $product->variants->first();

    $http->getJson('/api/v1/cart')->assertOk()
        ->assertJsonPath('data.groups', [])
        ->assertJsonPath('data.item_count', 0)
        ->assertJsonPath('data.purchasing_enabled', true);

    $http->postJson('/api/v1/cart/items', ['variant_id' => $variant->id, 'qty' => 2])
        ->assertCreated()
        ->assertJsonPath('data.item_count', 2)
        ->assertJsonPath('data.selected_count', 2)
        ->assertJsonPath('data.selected_subtotal_sen', 2500)
        ->assertJsonPath('data.groups.0.store.id', $product->store_id)
        ->assertJsonPath('data.groups.0.subtotal_sen', 2500)
        ->assertJsonPath('data.groups.0.lines.0.variant_id', $variant->id)
        ->assertJsonPath('data.groups.0.lines.0.unit_price_sen', 1250)
        ->assertJsonPath('data.groups.0.lines.0.list_price_sen', 1250)
        ->assertJsonPath('data.groups.0.lines.0.stock', 5)
        ->assertJsonPath('data.groups.0.lines.0.selected', true)
        ->assertJsonPath('data.groups.0.lines.0.cod_enabled', true)
        ->assertJsonStructure(['data' => ['groups' => [['store' => ['id', 'slug', 'name', 'logo'], 'lines' => [['product' => ['id', 'slug', 'name', 'thumb'], 'variant_label', 'qty']]]]]]);

    $http->patchJson("/api/v1/cart/items/{$variant->id}", ['qty' => 3])
        ->assertOk()
        ->assertJsonPath('data.groups.0.lines.0.qty', 3)
        ->assertJsonPath('data.selected_subtotal_sen', 3750);

    $http->patchJson("/api/v1/cart/items/{$variant->id}", ['selected' => false])
        ->assertOk()
        ->assertJsonPath('data.groups.0.lines.0.selected', false)
        ->assertJsonPath('data.selected_count', 0)
        ->assertJsonPath('data.selected_subtotal_sen', 0)
        ->assertJsonPath('data.groups.0.subtotal_sen', 0);

    $http->postJson('/api/v1/cart/select-all', ['selected' => true])
        ->assertOk()
        ->assertJsonPath('data.selected_count', 3);

    $http->deleteJson("/api/v1/cart/items/{$variant->id}")
        ->assertOk()
        ->assertJsonPath('data.groups', [])
        ->assertJsonPath('data.item_count', 0);
});

test('asking for more than the stock is a 422 with the message', function () {
    [, $http] = apiCartUser();
    $variant = apiCartProduct(stock: 2)->variants->first();

    $http->postJson('/api/v1/cart/items', ['variant_id' => $variant->id, 'qty' => 3])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonPath('error.fields.qty.0', 'Only 2 left in stock.');

    $http->postJson('/api/v1/cart/items', ['variant_id' => $variant->id, 'qty' => 2])->assertCreated();

    $http->patchJson("/api/v1/cart/items/{$variant->id}", ['qty' => 9])
        ->assertStatus(422)
        ->assertJsonPath('error.fields.qty.0', 'Only 2 left in stock.');

    $variant->update(['stock' => 0]);

    $http->postJson('/api/v1/cart/items', ['variant_id' => $variant->id])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonPath('error.message', __('Sorry — this item just sold out.'));

    // Unpublished products are refused by CartService::addItem, same as the web button.
    $variant->update(['stock' => 3]);
    $variant->product->update(['status' => ProductStatus::Draft]);
    $http->postJson('/api/v1/cart/items', ['variant_id' => $variant->id])
        ->assertStatus(422)
        ->assertJsonPath('error.message', __('This product is not available right now.'));
});

test('purchasing off is a 409 purchasing_disabled on writes while the cart still reads', function () {
    [, $http] = apiCartUser();
    $variant = apiCartProduct()->variants->first();

    $settings = app(GeneralSettings::class);
    $settings->purchasing_enabled = false;
    $settings->save();

    $http->postJson('/api/v1/cart/items', ['variant_id' => $variant->id])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'purchasing_disabled');

    $http->getJson('/api/v1/cart')->assertOk()->assertJsonPath('data.purchasing_enabled', false);
});

test('the cart needs a token', function () {
    $this->getJson('/api/v1/cart')->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated');
    $this->postJson('/api/v1/cart/items', ['variant_id' => 1])->assertUnauthorized();
});
