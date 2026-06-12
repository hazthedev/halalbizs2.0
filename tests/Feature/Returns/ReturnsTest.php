<?php

use App\Enums\ActorType;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReturnStatus;
use App\Enums\SubOrderStatus;
use App\Livewire\Admin\Orders\Returns as AdminReturns;
use App\Livewire\Seller\Orders\Detail as SellerOrderDetail;
use App\Livewire\Seller\Orders\Index as SellerOrdersIndex;
use App\Livewire\Storefront\Account\OrderDetail;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnReason;
use App\Models\ReturnRequest;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use App\Notifications\SubOrderStatusNotification;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\OrderService;
use App\Services\SubOrderStatusService;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(fn () => $this->seed(RoleSeeder::class));

function returnsReason(): ReturnReason
{
    return ReturnReason::firstOrCreate(
        ['position' => 0],
        ['label' => ['en' => 'Item is damaged or defective'], 'is_active' => true],
    );
}

function returnsSeller(): User
{
    $user = User::factory()->create();
    $user->assignRole('seller');

    Store::factory()->approved()->create(['user_id' => $user->id]);

    return $user;
}

function returnsAdmin(): User
{
    $user = User::factory()->create(['two_factor_method' => 'email']); // admins need 2FA (EnsureAdmin)
    $user->assignRole('admin');

    return $user;
}

/**
 * Factory fixture (status set at insert is test setup — production
 * transitions still go through the service).
 */
function returnsSubOrder(
    SubOrderStatus $status = SubOrderStatus::Delivered,
    ?Store $store = null,
    array $attributes = [],
    array $orderAttributes = [],
): SubOrder {
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');

    $order = Order::factory()->create(array_merge(['user_id' => $buyer->id], $orderAttributes));

    return SubOrder::factory()->status($status)->create(array_merge([
        'order_id' => $order->id,
        'store_id' => $store?->id ?? Store::factory()->approved()->create()->id,
        'items_subtotal_sen' => 10000,
        'shipping_fee_sen' => 500,
        'shop_discount_sen' => 0,
        'total_sen' => 10500,
        'commission_rate' => '5.00',
    ], $attributes));
}

function returnsRequestFor(SubOrder $subOrder, ReturnStatus $status = ReturnStatus::Requested, array $attributes = []): ReturnRequest
{
    return ReturnRequest::create(array_merge([
        'sub_order_id' => $subOrder->id,
        'return_reason_id' => returnsReason()->id,
        'description' => 'Arrived with a cracked lid.',
        'status' => $status,
        'respond_by' => now()->addHours(48),
    ], $attributes));
}

/**
 * Walk a real iPay88/COD order through checkout to completed so the ledger
 * completion entries exist (sale 20500, commission 1000 → balance 19500).
 */
function returnsCompletedSubOrder(PaymentMethod $method = PaymentMethod::Ipay88): SubOrder
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);

    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->store->update(['commission_rate' => 5.0, 'shipping_flat_fee_sen' => 500]);
    $product->variants->first()->update(['price_sen' => 10000, 'sale_price_sen' => null, 'stock' => 10]);

    app(CartService::class)->addItem($buyer, $product->variants->first(), 2);
    $order = app(CheckoutService::class)->place($buyer, $address, $method);

    $subOrder = $order->subOrders->first();
    $statusService = app(SubOrderStatusService::class);

    if ($method === PaymentMethod::Ipay88) {
        $statusService->transition($subOrder, SubOrderStatus::Confirmed, ActorType::System);
    }

    $statusService->transition($subOrder->fresh(), SubOrderStatus::Processing, ActorType::Seller);
    $statusService->transition($subOrder->fresh(), SubOrderStatus::Shipped, ActorType::Seller);
    app(OrderService::class)->markDelivered($subOrder->fresh(), ActorType::System);
    app(OrderService::class)->confirmReceived($subOrder->fresh(), $buyer->id);

    return $subOrder->fresh();
}

test('return request is hidden and blocked server-side outside the return window', function () {
    $subOrder = returnsSubOrder(SubOrderStatus::Delivered); // delivered_at = now()
    $buyer = $subOrder->order->user;
    $reason = returnsReason();

    $this->travel(8)->days(); // return_window_days default is 7

    Livewire::actingAs($buyer)
        ->test(OrderDetail::class, ['subOrder' => $subOrder])
        ->assertDontSee(__('Request return'))
        ->set('returnReasonId', $reason->id)
        ->call('submitReturn');

    expect(ReturnRequest::count())->toBe(0)
        ->and($subOrder->refresh()->status)->toBe(SubOrderStatus::Delivered);
});

test('buyer return request creates the row, transitions the sub-order and notifies the seller', function () {
    Notification::fake();
    Storage::fake('public');
    $this->freezeTime();

    $subOrder = returnsSubOrder(SubOrderStatus::Delivered);
    $buyer = $subOrder->order->user;
    $reason = returnsReason();

    Livewire::actingAs($buyer)
        ->test(OrderDetail::class, ['subOrder' => $subOrder])
        ->assertSee(__('Request return'))
        ->set('requestingReturn', true)
        ->set('returnReasonId', $reason->id)
        ->set('returnDescription', 'Handle snapped on first use.')
        ->set('returnPhotos', [UploadedFile::fake()->image('crack.jpg'), UploadedFile::fake()->image('lid.jpg')])
        ->call('submitReturn')
        ->assertHasNoErrors();

    $request = ReturnRequest::firstOrFail();
    expect($request->sub_order_id)->toBe($subOrder->id)
        ->and($request->status)->toBe(ReturnStatus::Requested)
        ->and($request->return_reason_id)->toBe($reason->id)
        ->and($request->description)->toBe('Handle snapped on first use.')
        ->and($request->respond_by->timestamp)->toBe(now()->addHours(48)->timestamp) // return_seller_response_hours
        ->and($request->getMedia('photos'))->toHaveCount(2)
        ->and($subOrder->refresh()->status)->toBe(SubOrderStatus::ReturnRequested);

    $history = $subOrder->statusHistories()->get()->last();
    expect($history->to_status)->toBe('return_requested')
        ->and($history->actor_type)->toBe(ActorType::Buyer)
        ->and($history->actor_id)->toBe($buyer->id);

    Notification::assertSentTo(
        $subOrder->store->user,
        SubOrderStatusNotification::class,
        fn ($notification) => $notification->status === SubOrderStatus::ReturnRequested
            && $notification->audience === 'seller',
    );
});

test('a second return request on the same sub-order is blocked', function () {
    $subOrder = returnsSubOrder(SubOrderStatus::Delivered);
    returnsRequestFor($subOrder);
    $buyer = $subOrder->order->user;

    Livewire::actingAs($buyer)
        ->test(OrderDetail::class, ['subOrder' => $subOrder])
        ->assertDontSee(__('Request return'))
        ->set('returnReasonId', returnsReason()->id)
        ->call('submitReturn');

    expect(ReturnRequest::count())->toBe(1);
});

test('photo count above the limit is rejected', function () {
    Storage::fake('public');

    $subOrder = returnsSubOrder(SubOrderStatus::Delivered);

    Livewire::actingAs($subOrder->order->user)
        ->test(OrderDetail::class, ['subOrder' => $subOrder])
        ->set('returnReasonId', returnsReason()->id)
        ->set('returnPhotos', collect(range(1, 6))->map(fn ($i) => UploadedFile::fake()->image("photo-{$i}.jpg"))->all())
        ->call('submitReturn')
        ->assertHasErrors(['returnPhotos' => 'max']);

    expect(ReturnRequest::count())->toBe(0);
});

test('seller accepts the return, then marks the item received → returned', function () {
    $seller = returnsSeller();
    $subOrder = returnsSubOrder(SubOrderStatus::ReturnRequested, $seller->store);
    $request = returnsRequestFor($subOrder);

    $component = Livewire::actingAs($seller)
        ->test(SellerOrderDetail::class, ['subOrder' => $subOrder])
        ->call('acceptReturn');

    expect($request->refresh()->status)->toBe(ReturnStatus::Accepted)
        ->and($subOrder->refresh()->status)->toBe(SubOrderStatus::ReturnRequested);

    $component->call('confirmItemReceived');

    $subOrder->refresh();
    expect($subOrder->status)->toBe(SubOrderStatus::Returned)
        ->and($request->refresh()->status)->toBe(ReturnStatus::Accepted); // awaiting the admin refund

    $history = $subOrder->statusHistories()->get()->last();
    expect($history->to_status)->toBe('returned')
        ->and($history->actor_type)->toBe(ActorType::Seller)
        ->and($history->actor_id)->toBe($seller->id);
});

test('seller dispute escalates the request to the admin queue without moving the sub-order', function () {
    $seller = returnsSeller();
    $subOrder = returnsSubOrder(SubOrderStatus::ReturnRequested, $seller->store);
    $request = returnsRequestFor($subOrder);

    $component = Livewire::actingAs($seller)->test(SellerOrderDetail::class, ['subOrder' => $subOrder]);

    $component->call('disputeReturn')->assertHasErrors(['disputeReason' => 'required']);
    expect($request->refresh()->status)->toBe(ReturnStatus::Requested);

    $component
        ->set('disputeReason', 'The item was returned used and damaged by the buyer.')
        ->call('disputeReturn')
        ->assertHasNoErrors();

    $request->refresh();
    expect($request->status)->toBe(ReturnStatus::Disputed)
        ->and($request->escalated_at)->not->toBeNull()
        ->and($request->seller_response)->toContain('used and damaged')
        ->and($subOrder->refresh()->status)->toBe(SubOrderStatus::ReturnRequested);
});

test('returns:auto-escalate flips only overdue requested requests', function () {
    $overdue = returnsRequestFor(returnsSubOrder(SubOrderStatus::ReturnRequested), attributes: ['respond_by' => now()->subHour()]);
    $fresh = returnsRequestFor(returnsSubOrder(SubOrderStatus::ReturnRequested), attributes: ['respond_by' => now()->addHour()]);
    $accepted = returnsRequestFor(returnsSubOrder(SubOrderStatus::ReturnRequested), ReturnStatus::Accepted, ['respond_by' => now()->subHour()]);

    $this->artisan('returns:auto-escalate')->assertSuccessful();

    expect($overdue->refresh()->status)->toBe(ReturnStatus::Escalated)
        ->and($overdue->escalated_at)->not->toBeNull()
        ->and($fresh->refresh()->status)->toBe(ReturnStatus::Requested)
        ->and($fresh->escalated_at)->toBeNull()
        ->and($accepted->refresh()->status)->toBe(ReturnStatus::Accepted);
});

test('admin queue lists disputed and escalated requests and is admin-only', function () {
    $admin = returnsAdmin();
    $escalated = returnsRequestFor(returnsSubOrder(SubOrderStatus::ReturnRequested), ReturnStatus::Escalated, ['escalated_at' => now()]);
    $requested = returnsRequestFor(returnsSubOrder(SubOrderStatus::ReturnRequested));

    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $this->actingAs($buyer)->get(route('admin.orders.returns'))->assertForbidden();

    $this->actingAs($admin)->get(route('admin.orders.returns'))->assertOk();

    Livewire::actingAs($admin)
        ->test(AdminReturns::class)
        ->assertSet('tab', 'queue')
        ->assertSee($escalated->subOrder->sub_order_no)
        ->assertDontSee($requested->subOrder->sub_order_no)
        ->set('tab', 'all')
        ->assertSee($requested->subOrder->sub_order_no)
        ->assertSee($escalated->subOrder->sub_order_no);
});

test('admin refund after completion reverses sale and commission exactly and sets every refunded status', function () {
    $admin = returnsAdmin();
    $subOrder = returnsCompletedSubOrder(PaymentMethod::Ipay88);
    $store = $subOrder->store;

    // Completion wrote +20500 sale, −1000 commission.
    expect($store->availableBalanceSen())->toBe(19500)
        ->and($subOrder->commission_sen)->toBe(1000);

    // Buyer asks, seller accepts and receives the item back.
    app(SubOrderStatusService::class)->transition($subOrder, SubOrderStatus::ReturnRequested, ActorType::Buyer, $subOrder->order->user_id);
    $request = returnsRequestFor($subOrder->fresh(), ReturnStatus::Accepted);
    app(SubOrderStatusService::class)->transition($subOrder->fresh(), SubOrderStatus::Returned, ActorType::Seller);

    $component = Livewire::actingAs($admin)
        ->test(AdminReturns::class)
        ->call('openResolve', $request->id);

    // iPay88 refunds need the portal reference.
    $component->call('refundBuyer')->assertHasErrors(['refundReference' => 'required']);
    expect($subOrder->fresh()->status)->toBe(SubOrderStatus::Returned);

    $component
        ->set('refundReference', 'IP88-RFND-4521')
        ->call('refundBuyer')
        ->assertHasNoErrors();

    $subOrder = $subOrder->fresh();
    $request->refresh();

    expect($subOrder->status)->toBe(SubOrderStatus::Refunded)
        ->and($subOrder->order->payment_status)->toBe(PaymentStatus::Refunded)
        ->and($request->status)->toBe(ReturnStatus::Refunded)
        ->and($request->resolved_at)->not->toBeNull()
        ->and($request->resolved_by)->toBe($admin->id);

    $history = $subOrder->statusHistories()->get()->last();
    expect($history->to_status)->toBe('refunded')
        ->and($history->actor_type)->toBe(ActorType::Admin)
        ->and($history->note)->toContain('IP88-RFND-4521');

    // Exact net: −(items 20000 + shipping 500 − discount 0) + commission 1000 = −19500.
    $adjustment = $store->ledgerEntries()->where('type', LedgerEntryType::Adjustment)->sole();
    expect($adjustment->amount_sen)->toBe(-19500)
        ->and($adjustment->sub_order_id)->toBe($subOrder->id)
        ->and($adjustment->description)->toBe('Refund '.$subOrder->sub_order_no)
        ->and($store->availableBalanceSen())->toBe(0); // back to pre-completion
});

test('refund before completion writes no ledger adjustment', function () {
    $admin = returnsAdmin();
    $subOrder = returnsSubOrder(SubOrderStatus::ReturnRequested); // COD order, never completed
    $request = returnsRequestFor($subOrder, ReturnStatus::Escalated, ['escalated_at' => now()]);

    Livewire::actingAs($admin)
        ->test(AdminReturns::class)
        ->call('openResolve', $request->id)
        ->call('refundBuyer')
        ->assertHasNoErrors(); // COD: no portal reference required

    expect($subOrder->refresh()->status)->toBe(SubOrderStatus::Refunded)
        ->and($request->refresh()->status)->toBe(ReturnStatus::Refunded)
        ->and($subOrder->ledgerEntries()->count())->toBe(0);
});

test('side with seller restores a completed sub-order and rejects the request', function () {
    $admin = returnsAdmin();
    $subOrder = returnsSubOrder(SubOrderStatus::ReturnRequested, attributes: [
        'delivered_at' => now()->subDays(2),
        'completed_at' => now()->subDay(),
    ]);
    $request = returnsRequestFor($subOrder, ReturnStatus::Escalated, ['escalated_at' => now()]);

    Livewire::actingAs($admin)
        ->test(AdminReturns::class)
        ->call('sideWithSeller', $request->id);

    $request->refresh();
    expect($subOrder->refresh()->status)->toBe(SubOrderStatus::Completed)
        ->and($request->status)->toBe(ReturnStatus::Rejected)
        ->and($request->resolved_at)->not->toBeNull()
        ->and($request->resolved_by)->toBe($admin->id);
});

test('side with seller restores a never-completed sub-order to delivered', function () {
    $admin = returnsAdmin();
    $subOrder = returnsSubOrder(SubOrderStatus::ReturnRequested, attributes: ['delivered_at' => now()->subDay()]);
    $request = returnsRequestFor($subOrder, ReturnStatus::Disputed, ['escalated_at' => now()]);

    Livewire::actingAs($admin)
        ->test(AdminReturns::class)
        ->call('sideWithSeller', $request->id);

    expect($subOrder->refresh()->status)->toBe(SubOrderStatus::Delivered)
        ->and($request->refresh()->status)->toBe(ReturnStatus::Rejected);
});

test('seller orders index shows the returns tab with the open request', function () {
    $seller = returnsSeller();
    $subOrder = returnsSubOrder(SubOrderStatus::ReturnRequested, $seller->store);
    returnsRequestFor($subOrder);

    Livewire::actingAs($seller)
        ->test(SellerOrdersIndex::class)
        ->set('tab', 'returns')
        ->assertSee($subOrder->sub_order_no);
});

test('a seller cannot act on another store\'s return', function () {
    $sellerA = returnsSeller();
    $subOrder = returnsSubOrder(SubOrderStatus::ReturnRequested, $sellerA->store);
    $request = returnsRequestFor($subOrder);

    $sellerB = returnsSeller();

    $this->actingAs($sellerB)->get(route('seller.orders.show', $subOrder))->assertForbidden();

    // The component itself 403s at mount, so the return actions are unreachable.
    try {
        Livewire::actingAs($sellerB)
            ->test(SellerOrderDetail::class, ['subOrder' => $subOrder])
            ->assertForbidden();
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }

    expect($request->refresh()->status)->toBe(ReturnStatus::Requested)
        ->and($subOrder->refresh()->status)->toBe(SubOrderStatus::ReturnRequested);
});
