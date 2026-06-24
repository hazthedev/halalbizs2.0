<?php

use App\Enums\ActorType;
use App\Enums\PaymentMethod;
use App\Enums\ProductStatus;
use App\Enums\SubOrderStatus;
use App\Events\OrderPaid;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\WebhookSubscription;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\SubOrderStatusService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => $this->seed(RoleSeeder::class));

// ── Read API ────────────────────────────────────────────────────────────────

test('the catalog API lists live products and hides drafts', function () {
    $live = Product::factory()->create(['status' => ProductStatus::Live]);
    $draft = Product::factory()->create(['status' => ProductStatus::Draft]);

    $this->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'slug', 'name', 'min_price_sen']]]);

    $this->getJson("/api/v1/products/{$live->slug}")->assertOk()->assertJsonPath('data.slug', $live->slug);
    $this->getJson("/api/v1/products/{$draft->slug}")->assertNotFound();
});

test('the categories and search endpoints respond as JSON', function () {
    Product::factory()->create(['status' => ProductStatus::Live]);

    $this->getJson('/api/v1/categories')->assertOk()->assertJsonStructure(['data']);
    $this->getJson('/api/v1/search?q=anything')->assertOk()->assertJsonStructure(['data']);
});

// ── Outbound webhooks ─────────────────────────────────────────────────────────

function webhookOrder(): Order
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);
    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->variants->first()->update(['price_sen' => 5000, 'sale_price_sen' => null, 'stock' => 10]);
    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    return app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);
}

test('an order.paid webhook fires a signed POST to subscribers', function () {
    Http::fake();
    $secret = 'shh-platform';
    WebhookSubscription::create(['url' => 'https://hooks.test/paid', 'secret' => $secret, 'events' => ['order.paid'], 'is_active' => true]);

    $order = webhookOrder();
    OrderPaid::dispatch($order->fresh());

    Http::assertSent(fn ($request) => $request->url() === 'https://hooks.test/paid'
        && $request->header('X-Webhook-Event')[0] === 'order.paid'
        && $request->header('X-Webhook-Signature')[0] === hash_hmac('sha256', $request->body(), $secret));
});

test('a sub_order.shipped webhook fires only for the subscribed store', function () {
    Http::fake();
    $order = webhookOrder();
    $subOrder = $order->subOrders->first();

    $secret = 'shh-store';
    WebhookSubscription::create([
        'store_id' => $subOrder->store_id, 'url' => 'https://hooks.test/shipped',
        'secret' => $secret, 'events' => ['sub_order.shipped'], 'is_active' => true,
    ]);

    $status = app(SubOrderStatusService::class);
    $status->transition($subOrder, SubOrderStatus::Processing, ActorType::Seller); // sub_order.processing — not subscribed
    $status->transition($subOrder->fresh(), SubOrderStatus::Shipped, ActorType::Seller);

    Http::assertSent(fn ($request) => $request->url() === 'https://hooks.test/shipped'
        && $request->header('X-Webhook-Event')[0] === 'sub_order.shipped');
    // The unsubscribed 'sub_order.processing' event must NOT fire a webhook,
    // and the shipped event fires exactly once (no double-registration).
    Http::assertNotSent(fn ($request) => ($request->header('X-Webhook-Event')[0] ?? '') === 'sub_order.processing');
    Http::assertSentCount(1);
});
