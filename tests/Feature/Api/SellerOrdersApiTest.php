<?php

use App\Enums\ActorType;
use App\Enums\ReturnStatus;
use App\Enums\SubOrderStatus;
use App\Jobs\RegisterAfterShipTracking;
use App\Models\SubOrder;
use App\Services\ShipmentService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/SellerApiHelpers.php';

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('GET seller/orders lists only this store, newest first, filtered by status, with the resource shape', function () {
    $seller = sellerApiUser();
    $older = sellerApiSubOrder($seller->store, SubOrderStatus::Confirmed);
    $older->forceFill(['created_at' => now()->subHour()])->saveQuietly();
    $newer = sellerApiSubOrder($seller->store, SubOrderStatus::Processing);
    sellerApiSubOrder(sellerApiUser()->store);

    $response = sellerApiAs($seller)->getJson('/api/v1/seller/orders')
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.1.id', $older->id)
        ->assertJsonPath('data.1.status', 'confirmed')
        ->assertJsonPath('data.1.status_label', 'Confirmed')
        ->assertJsonPath('data.1.subtotal_sen', 10000)
        ->assertJsonPath('data.1.shipping_sen', 500)
        ->assertJsonPath('data.1.total_sen', 10500)
        ->assertJsonPath('data.1.commission_rate', '5.00')
        ->assertJsonPath('data.1.payment.method', 'cod')
        ->assertJsonPath('data.1.payment.status', 'pending')
        ->assertJsonPath('data.1.buyer.phone', '+60123456789')
        ->assertJsonPath('data.1.items.0.qty', 2)
        ->assertJsonPath('data.1.items.0.unit_price_sen', 5000)
        ->assertJsonPath('data.1.return_request', null)
        ->assertJsonMissingPath('data.1.history');

    expect($response->json('data.1'))->toHaveKeys(['sub_order_no', 'order_no', 'placed_at', 'address', 'discount_sen', 'tax_sen', 'commission_sen', 'tracking'])
        ->and($response->json('data.1.address.state'))->toBe('Selangor');

    sellerApiAs($seller)->getJson('/api/v1/seller/orders?status=processing')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $newer->id);

    sellerApiAs($seller)->getJson('/api/v1/seller/orders?status=bogus')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');
});

test("GET seller/orders/{id} includes the history; another store's sub-order is a 404", function () {
    $seller = sellerApiUser();
    $own = sellerApiSubOrder($seller->store);
    $foreign = sellerApiSubOrder(sellerApiUser()->store);

    sellerApiAs($seller)->getJson("/api/v1/seller/orders/{$own->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $own->id)
        ->assertJsonPath('data.history.0.status', 'confirmed')
        ->assertJsonPath('data.history.0.actor', 'system');

    sellerApiAs($seller)->getJson("/api/v1/seller/orders/{$foreign->id}")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');

    sellerApiAs($seller)->postJson("/api/v1/seller/orders/{$foreign->id}/pack")->assertNotFound();

    $foreignProcessing = sellerApiSubOrder(sellerApiUser()->store, SubOrderStatus::Processing);

    sellerApiAs($seller)->postJson("/api/v1/seller/orders/{$foreignProcessing->id}/ship", ['courier' => 'GDEX', 'tracking_no' => 'GD123456'])
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');

    expect($foreign->fresh()->status)->toBe(SubOrderStatus::Confirmed)
        ->and($foreignProcessing->fresh()->status)->toBe(SubOrderStatus::Processing)
        ->and($foreignProcessing->fresh()->tracking_no)->toBeNull();
});

test('a second ship racing the first is refused: tracking kept, status shipped once, EasyParcel booked once', function () {
    Queue::fake();
    config(['shipping.easyparcel.enabled' => true, 'shipping.easyparcel.api_key' => 'test']);
    Http::fake([
        '*/api/v1/order-submission' => Http::response(['result' => [['awb' => 'EP123', 'awb_id_link' => 'https://easyparcel.test/label/EP123']]]),
        '*' => Http::response([]),
    ]);

    $seller = sellerApiUser();
    $seller->store->update(['shipping_mode' => 'easyparcel']);
    $subOrder = sellerApiSubOrder($seller->store, SubOrderStatus::Processing);

    // The loser's copy, loaded while the row was still `processing`.
    $stale = SubOrder::find($subOrder->id);

    $service = app(ShipmentService::class);
    $service->ship($subOrder, 'GDEX', null, 'GD123456', $seller->id);

    expect(fn () => $service->ship($stale, 'Pos Laju', null, 'PL999999', $seller->id))
        ->toThrow(InvalidArgumentException::class, __('This order can no longer be shipped from its current status.'));

    $subOrder->refresh();
    expect($subOrder->status)->toBe(SubOrderStatus::Shipped)
        ->and($subOrder->tracking_courier)->toBe('GDEX')
        ->and($subOrder->tracking_no)->toBe('GD123456')
        ->and($subOrder->awb_no)->toBe('EP123')
        ->and($subOrder->statusHistories()->where('to_status', 'shipped')->count())->toBe(1);

    Http::assertSentCount(1);
    Queue::assertPushed(RegisterAfterShipTracking::class, 1);

    sellerApiAs($seller)->postJson("/api/v1/seller/orders/{$subOrder->id}/ship", ['courier' => 'Pos Laju', 'tracking_no' => 'PL999999'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'invalid_transition');

    expect($subOrder->fresh()->tracking_no)->toBe('GD123456');
});

test('pack moves confirmed → processing with a seller history row; from shipped it is a 409', function () {
    $seller = sellerApiUser();
    $subOrder = sellerApiSubOrder($seller->store, SubOrderStatus::Confirmed);

    sellerApiAs($seller)->postJson("/api/v1/seller/orders/{$subOrder->id}/pack")
        ->assertOk()
        ->assertJsonPath('data.status', 'processing');

    $history = $subOrder->statusHistories()->get()->last();
    expect($subOrder->fresh()->status)->toBe(SubOrderStatus::Processing)
        ->and($history->from_status)->toBe('confirmed')
        ->and($history->to_status)->toBe('processing')
        ->and($history->actor_type)->toBe(ActorType::Seller)
        ->and($history->actor_id)->toBe($seller->id);

    $shipped = sellerApiSubOrder($seller->store, SubOrderStatus::Shipped);

    sellerApiAs($seller)->postJson("/api/v1/seller/orders/{$shipped->id}/pack")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'invalid_transition');

    expect($shipped->fresh()->status)->toBe(SubOrderStatus::Shipped);
});

test('GET seller/couriers lists the fixed courier names', function () {
    sellerApiAs(sellerApiUser())->getJson('/api/v1/seller/couriers')
        ->assertOk()
        ->assertJsonPath('data.0', 'J&T Express')
        ->assertJsonCount(6, 'data');
});

test('ship validates, writes tracking, transitions to shipped and queues AfterShip registration', function () {
    Queue::fake();
    Http::fake();

    $seller = sellerApiUser();
    $subOrder = sellerApiSubOrder($seller->store, SubOrderStatus::Processing);

    sellerApiAs($seller)->postJson("/api/v1/seller/orders/{$subOrder->id}/ship", ['courier' => 'J&T Express', 'tracking_no' => 'JT12'])
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['fields' => ['tracking_no']]]);

    sellerApiAs($seller)->postJson("/api/v1/seller/orders/{$subOrder->id}/ship", ['courier' => 'Skynet', 'tracking_no' => 'SKY00012345'])
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['fields' => ['courier']]]);

    sellerApiAs($seller)->postJson("/api/v1/seller/orders/{$subOrder->id}/ship", ['courier' => 'other', 'tracking_no' => 'SKY00012345'])
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['fields' => ['courier_other']]]);

    expect($subOrder->fresh()->status)->toBe(SubOrderStatus::Processing);

    sellerApiAs($seller)->postJson("/api/v1/seller/orders/{$subOrder->id}/ship", [
        'courier' => 'other', 'courier_other' => 'Skynet Express', 'tracking_no' => 'SKY00012345',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'shipped')
        ->assertJsonPath('data.tracking.courier', 'Skynet Express')
        ->assertJsonPath('data.tracking.no', 'SKY00012345');

    $subOrder->refresh();
    expect($subOrder->status)->toBe(SubOrderStatus::Shipped)
        ->and($subOrder->shipped_at)->not->toBeNull()
        ->and($subOrder->statusHistories()->get()->last()->to_status)->toBe('shipped')
        ->and($subOrder->statusHistories()->get()->last()->actor_id)->toBe($seller->id);

    Queue::assertPushed(RegisterAfterShipTracking::class, fn ($job) => $job->subOrderId === $subOrder->id);

    // Already shipped: the service refuses before touching tracking.
    sellerApiAs($seller)->postJson("/api/v1/seller/orders/{$subOrder->id}/ship", ['courier' => 'GDEX', 'tracking_no' => 'GD123456'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'invalid_transition');

    expect($subOrder->fresh()->tracking_courier)->toBe('Skynet Express');
});

test('delivered moves shipped → delivered through OrderService (COD settles) and refuses otherwise', function () {
    $seller = sellerApiUser();
    $subOrder = sellerApiSubOrder($seller->store, SubOrderStatus::Shipped);

    sellerApiAs($seller)->postJson("/api/v1/seller/orders/{$subOrder->id}/delivered")
        ->assertOk()
        ->assertJsonPath('data.status', 'delivered')
        ->assertJsonPath('data.payment.status', 'paid');

    $subOrder->refresh();
    expect($subOrder->delivered_at)->not->toBeNull()
        ->and($subOrder->auto_complete_at)->not->toBeNull();

    $confirmed = sellerApiSubOrder($seller->store, SubOrderStatus::Confirmed);

    sellerApiAs($seller)->postJson("/api/v1/seller/orders/{$confirmed->id}/delivered")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'invalid_transition');
});

test('return accept then received → returned; received before accept and dispute afterwards are 409', function () {
    $seller = sellerApiUser();
    $subOrder = sellerApiSubOrder($seller->store, SubOrderStatus::ReturnRequested);
    $request = sellerApiReturn($subOrder);

    sellerApiAs($seller)->postJson("/api/v1/seller/orders/{$subOrder->id}/return/received")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'invalid_transition');

    sellerApiAs($seller)->postJson("/api/v1/seller/orders/{$subOrder->id}/return/accept")
        ->assertOk()
        ->assertJsonPath('data.status', 'return_requested')
        ->assertJsonPath('data.return_request.status', 'accepted')
        ->assertJsonPath('data.return_request.reason', 'Item is damaged or defective');

    expect($request->fresh()->status)->toBe(ReturnStatus::Accepted);

    sellerApiAs($seller)->postJson("/api/v1/seller/orders/{$subOrder->id}/return/dispute", ['reason' => 'Returned used and damaged by the buyer.'])
        ->assertStatus(409);

    sellerApiAs($seller)->postJson("/api/v1/seller/orders/{$subOrder->id}/return/received")
        ->assertOk()
        ->assertJsonPath('data.status', 'returned');

    $subOrder->refresh();
    $history = $subOrder->statusHistories()->get()->last();
    expect($subOrder->status)->toBe(SubOrderStatus::Returned)
        ->and($request->fresh()->status)->toBe(ReturnStatus::Accepted) // awaiting the admin refund
        ->and($history->to_status)->toBe('returned')
        ->and($history->actor_type)->toBe(ActorType::Seller)
        ->and($history->actor_id)->toBe($seller->id);
});

test('return dispute needs a reason, escalates the request and leaves the sub-order status alone', function () {
    $seller = sellerApiUser();
    $subOrder = sellerApiSubOrder($seller->store, SubOrderStatus::ReturnRequested);
    $request = sellerApiReturn($subOrder);

    sellerApiAs($seller)->postJson("/api/v1/seller/orders/{$subOrder->id}/return/dispute", ['reason' => 'short'])
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['fields' => ['reason']]]);

    sellerApiAs($seller)->postJson("/api/v1/seller/orders/{$subOrder->id}/return/dispute", ['reason' => 'The item was returned used and damaged by the buyer.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'return_requested')
        ->assertJsonPath('data.return_request.status', 'disputed');

    $request->refresh();
    expect($request->status)->toBe(ReturnStatus::Disputed)
        ->and($request->escalated_at)->not->toBeNull()
        ->and($request->seller_response)->toContain('used and damaged');
});
