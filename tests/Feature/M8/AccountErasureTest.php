<?php

use App\Enums\PaymentMethod;
use App\Enums\SubOrderStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\SubOrder;
use App\Models\User;
use App\Services\AccountDeletionService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(fn () => $this->seed(RoleSeeder::class));

// HB-DEL2: deletion erases data that serves only the user, not just the users row.

test('deleting an account erases its saved addresses and leaves other users addresses alone', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Address::factory()->count(2)->create(['user_id' => $user->id]);
    Address::factory()->create(['user_id' => $other->id]);

    expect(app(AccountDeletionService::class)->deleteUnlessBlocked($user))->toBeNull();

    expect(Address::where('user_id', $user->id)->count())->toBe(0)
        ->and(Address::where('user_id', $other->id)->count())->toBe(1);
});

/** One row in every personal-only table for $user, so a test can see which ones the deletion empties. */
function hbDel2PersonalRows(User $user, ProductVariant $variant, Review $review): void
{
    $now = now();
    $address = Address::factory()->create(['user_id' => $user->id]);

    DB::table('subscriptions')->insert(['user_id' => $user->id, 'product_variant_id' => $variant->id, 'address_id' => $address->id,
        'interval_days' => 30, 'payment_method' => PaymentMethod::Cod->value, 'created_at' => $now, 'updated_at' => $now]);
    DB::table('wishlists')->insert(['user_id' => $user->id, 'product_id' => $variant->product_id, 'created_at' => $now]);
    $cartId = DB::table('carts')->insertGetId(['user_id' => $user->id, 'created_at' => $now, 'updated_at' => $now]);
    DB::table('cart_items')->insert(['cart_id' => $cartId, 'product_variant_id' => $variant->id, 'qty' => 1]);
    DB::table('product_views')->insert(['user_id' => $user->id, 'product_id' => $variant->product_id, 'viewed_at' => $now]);
    DB::table('stock_subscriptions')->insert(['user_id' => $user->id, 'product_variant_id' => $variant->id]);
    DB::table('store_follows')->insert(['user_id' => $user->id, 'store_id' => $review->store_id]);
    DB::table('review_helpfuls')->insert(['review_id' => $review->id, 'user_id' => $user->id]);
    DB::table('known_devices')->insert(['user_id' => $user->id, 'fingerprint' => str_repeat('a', 64), 'label' => 'Chrome on Mac']);
    DB::table('otp_codes')->insert(['user_id' => $user->id, 'purpose' => 'phone-verify', 'code_hash' => 'x', 'expires_at' => $now]);
    DB::table('sessions')->insert(['id' => 'sess-'.$user->id, 'user_id' => $user->id, 'ip_address' => '203.0.113.9', 'payload' => '', 'last_activity' => 0]);
    DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'x']);
    DB::table('newsletter_subscribers')->insert(['email' => $user->email, 'user_id' => $user->id]);
    DB::table('search_logs')->insert(['term' => 'kurma', 'user_id' => $user->id]);
    DB::table('notifications')->insert(['id' => (string) Str::uuid(), 'type' => 'test', 'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->id, 'data' => '{}']);
    $user->forceFill(['google_id' => 'g-'.$user->id, 'two_factor_method' => 'email', 'phone_verified_at' => $now])->save();
}

/** @return array<string, int> rows per personal table that still point at $user */
function hbDel2RowCounts(User $user, string $email): array
{
    $counts = [];
    foreach (['addresses', 'subscriptions', 'wishlists', 'carts', 'product_views', 'stock_subscriptions', 'store_follows',
        'review_helpfuls', 'known_devices', 'otp_codes', 'sessions', 'search_logs'] as $table) {
        $counts[$table] = DB::table($table)->where('user_id', $user->id)->count();
    }

    $counts['newsletter_subscribers'] = DB::table('newsletter_subscribers')->where('email', $email)->count();
    $counts['password_reset_tokens'] = DB::table('password_reset_tokens')->where('email', $email)->count();
    $counts['notifications'] = DB::table('notifications')->where('notifiable_id', $user->id)->count();

    return $counts;
}

test('deleting an account empties every personal-only table for that user and nobody else', function () {
    $variant = ProductVariant::factory()->create();
    $author = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $author->id]);
    $item = SubOrder::factory()->create(['order_id' => $order->id])->items()->create([
        'product_name' => 'Kurma', 'variant_label' => '1 kg', 'unit_price_sen' => 2500, 'qty' => 1, 'line_total_sen' => 2500,
    ]);
    $review = Review::create(['order_item_id' => $item->id, 'product_id' => $variant->product_id,
        'store_id' => $variant->product->store_id, 'user_id' => $author->id, 'rating' => 5, 'helpful_count' => 2]);

    $user = User::factory()->create();
    $other = User::factory()->create();
    $email = $user->email;
    hbDel2PersonalRows($user, $variant, $review);
    hbDel2PersonalRows($other, $variant, $review);

    expect(app(AccountDeletionService::class)->deleteUnlessBlocked($user))->toBeNull();

    $fresh = User::withTrashed()->find($user->id);
    expect(array_filter(hbDel2RowCounts($user, $email)))->toBe([])
        ->and(DB::table('cart_items')->count())->toBe(1) // the other user's cart line only
        ->and(DB::table('search_logs')->whereNull('user_id')->where('term', 'kurma')->count())->toBe(1) // kept as an anonymous total
        ->and($review->refresh()->helpful_count)->toBe(1)
        ->and($fresh->google_id)->toBeNull()
        ->and($fresh->two_factor_method)->toBeNull()
        ->and($fresh->phone_verified_at)->toBeNull()
        ->and(array_values(array_unique(hbDel2RowCounts($other, $other->email))))->toBe([1])
        ->and($other->fresh()->google_id)->toBe('g-'.$other->id);
});

test('deleting an account keeps orders and their shipping address snapshot', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);
    SubOrder::factory()->status(SubOrderStatus::Completed)->create(['order_id' => $order->id]);
    Address::factory()->create(['user_id' => $user->id]);
    $snapshot = $order->shipping_address;

    expect(app(AccountDeletionService::class)->deleteUnlessBlocked($user))->toBeNull();

    expect(Order::find($order->id)->shipping_address)->toBe($snapshot)
        ->and(SubOrder::where('order_id', $order->id)->count())->toBe(1);
});
