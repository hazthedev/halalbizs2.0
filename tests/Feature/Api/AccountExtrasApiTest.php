<?php

use App\Enums\ProductStatus;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use App\Notifications\SubOrderStatusNotification;
use App\Services\CoinService;
use App\Support\DisplayTime;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestCase;

beforeEach(fn () => $this->seed(RoleSeeder::class));

/**
 * withToken() sets a default header on the ONE TestCase, so a test with two
 * callers must re-arm the token before each request: apiExtrasAs($token).
 *
 * @return array{0: User, 1: TestCase}
 */
function apiExtrasUser(string $role = 'buyer'): array
{
    $user = User::factory()->create();
    $user->assignRole($role);

    app('auth')->forgetGuards();

    return [$user, apiExtrasAs(apiExtrasToken($user))];
}

function apiExtrasToken(User $user): string
{
    return $user->createToken('pest-phone')->plainTextToken;
}

function apiExtrasAs(string $token): Illuminate\Foundation\Testing\TestCase
{
    // Sanctum's RequestGuard caches the resolved user for the app's lifetime.
    app('auth')->forgetGuards();

    return test()->withToken($token);
}

test('wishlist toggles and lists', function () {
    [, $http] = apiExtrasUser();
    $product = Product::factory()->create();

    $http->getJson('/api/v1/wishlist')->assertOk()->assertJsonPath('data', []);

    $http->postJson("/api/v1/wishlist/{$product->id}")->assertOk()->assertJsonPath('data.wishlisted', true);

    $http->getJson('/api/v1/wishlist')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $product->id)
        ->assertJsonPath('data.0.slug', $product->slug);

    $http->postJson("/api/v1/wishlist/{$product->id}")->assertOk()->assertJsonPath('data.wishlisted', false);
    $http->getJson('/api/v1/wishlist')->assertJsonPath('data', []);
    $http->postJson('/api/v1/wishlist/999999')->assertNotFound();

    // A product that is no longer live cannot be saved, but one already saved can still be removed.
    $http->postJson("/api/v1/wishlist/{$product->id}")->assertOk()->assertJsonPath('data.wishlisted', true);
    $product->update(['status' => ProductStatus::Draft]);
    $http->postJson("/api/v1/wishlist/{$product->id}")->assertOk()->assertJsonPath('data.wishlisted', false);
    $http->postJson("/api/v1/wishlist/{$product->id}")->assertNotFound();
});

test('coins show the wallet and check-in once a day', function () {
    [$user, $http] = apiExtrasUser();

    $http->getJson('/api/v1/coins')
        ->assertOk()
        ->assertJsonPath('data.balance', 0)
        ->assertJsonPath('data.can_check_in', true)
        ->assertJsonPath('data.ledger', [])
        ->assertJsonPath('meta.total', 0);

    $http->postJson('/api/v1/coins/check-in')
        ->assertOk()
        ->assertJsonPath('data.coins', 5)
        ->assertJsonPath('data.streak', 1)
        ->assertJsonPath('data.day', 1);

    expect(app(CoinService::class)->balance($user))->toBe(5);

    $http->postJson('/api/v1/coins/check-in')
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'already_checked_in');

    $http->getJson('/api/v1/coins')
        ->assertOk()
        ->assertJsonPath('data.balance', 5)
        ->assertJsonPath('data.streak', 1)
        ->assertJsonPath('data.can_check_in', false)
        ->assertJsonPath('data.ledger.0.type', 'checkin')
        ->assertJsonPath('data.ledger.0.amount', 5)
        ->assertJsonStructure(['data' => ['lifetime_earned', 'expiring_at', 'ledger' => [['id', 'type', 'type_label', 'amount', 'note', 'created_at']]]]);

    expect(DisplayTime::today())->not->toBeNull();

    config(['coins.enabled' => false]);
    $http->getJson('/api/v1/coins')->assertNotFound();
    $http->postJson('/api/v1/coins/check-in')->assertNotFound();
});

test('notifications list with app links and mark as read', function () {
    [$user, $http] = apiExtrasUser();
    $subOrder = SubOrder::factory()->create(['order_id' => Order::factory()->create(['user_id' => $user->id])->id]);

    Notification::fake();
    $user->notify(new SubOrderStatusNotification($subOrder, $subOrder->status, 'buyer'));
    Notification::assertSentTo($user, SubOrderStatusNotification::class);

    // Write the database rows directly (the fake swallowed the channel).
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => SubOrderStatusNotification::class,
        'data' => ['message' => 'Order confirmed — '.$subOrder->sub_order_no, 'detail' => 'The seller is packing it.', 'url' => url("/account/orders/{$subOrder->id}")],
    ]);
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => SubOrderStatusNotification::class,
        'data' => ['message' => 'Older one', 'url' => url('/somewhere/else')],
        'read_at' => now(),
        'created_at' => now()->subMinute(),
    ]);

    $list = $http->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.unread', 1)
        ->assertJsonPath('data.0.message', 'Order confirmed — '.$subOrder->sub_order_no)
        ->assertJsonPath('data.0.detail', 'The seller is packing it.')
        ->assertJsonPath('data.0.app_url', "/orders/{$subOrder->id}")
        ->assertJsonPath('data.0.read_at', null)
        ->assertJsonPath('data.1.app_url', null)
        ->assertJsonStructure(['data' => [['id', 'type', 'message', 'detail', 'url', 'app_url', 'read_at', 'created_at']]])
        ->json('data');

    $http->postJson("/api/v1/notifications/{$list[0]['id']}/read")->assertOk();
    expect($user->unreadNotifications()->count())->toBe(0);

    $user->notifications()->update(['read_at' => null]);
    $http->postJson('/api/v1/notifications/read-all')->assertOk();
    expect($user->unreadNotifications()->count())->toBe(0);

    [, $stranger] = apiExtrasUser();
    $stranger->postJson("/api/v1/notifications/{$list[0]['id']}/read")->assertNotFound();
});

test('conversations open, send and read from both sides; strangers get a 404', function () {
    Notification::fake();

    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $owner = User::factory()->create();
    $owner->assignRole('seller');
    $store = Store::factory()->approved()->create(['user_id' => $owner->id]);
    $product = Product::factory()->create(['store_id' => $store->id]);

    app('auth')->forgetGuards();
    $asBuyer = apiExtrasToken($buyer);
    $asSeller = apiExtrasToken($owner);

    $conversationId = apiExtrasAs($asBuyer)->postJson('/api/v1/conversations', ['store_id' => $store->id, 'product_id' => $product->id])
        ->assertCreated()
        ->assertJsonPath('data.side', 'buyer')
        ->assertJsonPath('data.store.id', $store->id)
        ->assertJsonPath('data.product.id', $product->id)
        ->assertJsonPath('data.last_message', null)
        ->assertJsonPath('data.unread_count', 0)
        ->json('data.id');

    // Idempotent per buyer+store pair (ChatService::openConversation).
    apiExtrasAs($asBuyer)->postJson('/api/v1/conversations', ['store_id' => $store->id])->assertCreated()->assertJsonPath('data.id', $conversationId);
    expect(Conversation::count())->toBe(1);

    apiExtrasAs($asBuyer)->postJson("/api/v1/conversations/{$conversationId}/messages", ['body' => 'Is this halal certified?', 'product_id' => $product->id])
        ->assertCreated()
        ->assertJsonPath('data.side', 'buyer')
        ->assertJsonPath('data.body', 'Is this halal certified?')
        ->assertJsonPath('data.product.id', $product->id);

    apiExtrasAs($asBuyer)->postJson("/api/v1/conversations/{$conversationId}/messages", ['body' => ''])
        ->assertStatus(422)
        ->assertJsonPath('error.fields.body.0', __('Type a message first.'));

    // Seller side: unread 1, reading clears it, reply lands as 'seller'.
    apiExtrasAs($asSeller)->getJson('/api/v1/conversations')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.side', 'seller')
        ->assertJsonPath('data.0.buyer.name', $buyer->name)
        ->assertJsonPath('data.0.unread_count', 1)
        ->assertJsonPath('data.0.last_message.side', 'buyer');

    apiExtrasAs($asSeller)->getJson("/api/v1/conversations/{$conversationId}")
        ->assertOk()
        ->assertJsonPath('meta.conversation.side', 'seller')
        ->assertJsonPath('meta.conversation.unread_count', 0)
        ->assertJsonPath('data.0.side', 'buyer')
        ->assertJsonPath('meta.total', 1);

    apiExtrasAs($asSeller)->postJson("/api/v1/conversations/{$conversationId}/messages", ['body' => 'Yes, JAKIM certified.'])
        ->assertCreated()
        ->assertJsonPath('data.side', 'seller');

    apiExtrasAs($asBuyer)->getJson('/api/v1/conversations')
        ->assertOk()
        ->assertJsonPath('data.0.unread_count', 1)
        ->assertJsonPath('data.0.last_message.body', 'Yes, JAKIM certified.');

    apiExtrasAs($asBuyer)->getJson("/api/v1/conversations/{$conversationId}")
        ->assertOk()
        ->assertJsonPath('data.0.body', 'Yes, JAKIM certified.')
        ->assertJsonPath('data.1.body', 'Is this halal certified?');

    expect(Conversation::find($conversationId)->unreadCountFor('buyer'))->toBe(0);

    // A stranger — buyer or another seller — sees nothing.
    [, $stranger] = apiExtrasUser();
    $stranger->getJson("/api/v1/conversations/{$conversationId}")->assertNotFound();
    $stranger->postJson("/api/v1/conversations/{$conversationId}/messages", ['body' => 'hi'])->assertNotFound();
    $stranger->getJson('/api/v1/conversations')->assertOk()->assertJsonPath('data', []);

    // The owner cannot open a thread with their own shop.
    apiExtrasAs($asSeller)->postJson('/api/v1/conversations', ['store_id' => $store->id])->assertStatus(422);
});
