<?php

use App\Enums\SubOrderStatus;
use App\Models\Review;
use App\Models\SubOrder;
use Database\Seeders\RoleSeeder;

require_once __DIR__.'/SellerApiHelpers.php';

beforeEach(fn () => $this->seed(RoleSeeder::class));

function sellerApiReview(SubOrder $subOrder, array $attributes = []): Review
{
    $item = $subOrder->items()->first();

    return Review::create(array_merge([
        'order_item_id' => $item->id,
        'product_id' => $item->product_id,
        'store_id' => $subOrder->store_id,
        'user_id' => $subOrder->order->user_id,
        'rating' => 4,
        'comment' => 'Solid quality and fast delivery, very pleased.',
    ], $attributes));
}

test('GET seller/reviews lists this store only with the masked buyer name and reply state', function () {
    $seller = sellerApiUser();
    $subOrder = sellerApiSubOrder($seller->store, SubOrderStatus::Completed);
    $subOrder->order->user->update(['name' => 'Nurul Aina']);
    $review = sellerApiReview($subOrder);
    sellerApiReview(sellerApiSubOrder(sellerApiUser()->store, SubOrderStatus::Completed));

    $response = sellerApiAs($seller)->getJson('/api/v1/seller/reviews')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $review->id)
        ->assertJsonPath('data.0.rating', 4)
        ->assertJsonPath('data.0.buyer_name', 'Nurul A.')
        ->assertJsonPath('data.0.product.id', $review->product_id)
        ->assertJsonPath('data.0.reply', null)
        ->assertJsonPath('data.0.can_reply', true);

    expect($response->json('data.0'))->toHaveKeys(['body', 'photos', 'created_at', 'product']);
});

test('reply posts once, edits inside 24 hours, then locks with 409 reply_locked', function () {
    $seller = sellerApiUser();
    $review = sellerApiReview(sellerApiSubOrder($seller->store, SubOrderStatus::Completed));

    sellerApiAs($seller)->postJson("/api/v1/seller/reviews/{$review->id}/reply", ['body' => 'Hi'])
        ->assertStatus(422)
        ->assertJsonPath('error.fields.body.0', __('Write at least 5 characters.'));

    sellerApiAs($seller)->postJson("/api/v1/seller/reviews/{$review->id}/reply", ['body' => 'Thank you for the support!'])
        ->assertOk()
        ->assertJsonPath('data.reply.body', 'Thank you for the support!')
        ->assertJsonPath('data.can_reply', true);

    $firstRepliedAt = $review->fresh()->seller_replied_at;
    expect($firstRepliedAt)->not->toBeNull();

    $this->travel(2)->hours();

    sellerApiAs($seller)->postJson("/api/v1/seller/reviews/{$review->id}/reply", ['body' => 'Thank you — updated with care instructions.'])
        ->assertOk();

    expect($review->fresh()->seller_replied_at->equalTo($firstRepliedAt))->toBeTrue();

    $this->travel(23)->hours();

    sellerApiAs($seller)->getJson('/api/v1/seller/reviews')->assertJsonPath('data.0.can_reply', false);

    sellerApiAs($seller)->postJson("/api/v1/seller/reviews/{$review->id}/reply", ['body' => 'Sneaky late edit.'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'reply_locked');

    expect($review->fresh()->seller_reply)->toBe('Thank you — updated with care instructions.');
});

test("another store's review is a 404", function () {
    $seller = sellerApiUser();
    $foreign = sellerApiReview(sellerApiSubOrder(sellerApiUser()->store, SubOrderStatus::Completed));

    sellerApiAs($seller)->postJson("/api/v1/seller/reviews/{$foreign->id}/reply", ['body' => 'Not my review to answer.'])
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');

    expect($foreign->fresh()->seller_reply)->toBeNull();
});
