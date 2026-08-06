<?php

/**
 * QA pass — buyer isolation + the sub-order lifecycle.
 *
 * Every test here is written to FAIL if the defect it names is real. A green
 * test in this file is a "verified correct", not a finding.
 */

use App\Enums\ActorType;
use App\Enums\GroupBuyStatus;
use App\Enums\PaymentMethod;
use App\Enums\SubOrderStatus;
use App\Enums\SubscriptionStatus;
use App\Livewire\Storefront\Account\Addresses;
use App\Livewire\Storefront\Account\Messages;
use App\Livewire\Storefront\Account\Notifications as NotificationsPage;
use App\Livewire\Storefront\Account\OrderDetail;
use App\Livewire\Storefront\Account\Orders;
use App\Livewire\Storefront\Account\ReviewOrder;
use App\Livewire\Storefront\Account\Subscriptions;
use App\Livewire\Storefront\GroupBuy\Panel;
use App\Models\Address;
use App\Models\CancellationReason;
use App\Models\Conversation;
use App\Models\GroupBuy;
use App\Models\GroupBuyTeam;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubOrderStatusNotification;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\OrderService;
use App\Services\SubOrderStatusService;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function qaBuyer(): User
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');

    return $buyer;
}

function qaSubOrder(User $buyer, SubOrderStatus $status = SubOrderStatus::Confirmed, array $orderAttributes = []): SubOrder
{
    $order = Order::factory()->create(array_merge(['user_id' => $buyer->id], $orderAttributes));

    return SubOrder::factory()->status($status)->create(['order_id' => $order->id]);
}

/** Place a real COD order so stock movements are the real ones. */
function qaPlaceCodOrder(User $buyer, int $stock = 5, int $qty = 2): array
{
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);
    $product = Product::factory()->create(['cod_enabled' => true]);
    $variant = $product->variants->first();
    $variant->update(['price_sen' => 10000, 'sale_price_sen' => null, 'stock' => $stock]);

    app(CartService::class)->addItem($buyer, $variant, $qty);
    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod);

    return [$order, $order->subOrders->first(), $variant];
}

// ─────────────────────────────────────────────────────────────────────────────
// PART A — buyer isolation
// ─────────────────────────────────────────────────────────────────────────────

test('A1 OrderDetail mount refuses another buyer', function () {
    $victim = qaBuyer();
    $attacker = qaBuyer();
    $subOrder = qaSubOrder($victim);

    $this->actingAs($attacker)->get(route('account.orders.show', $subOrder))->assertForbidden();
    $this->actingAs($victim)->get(route('account.orders.show', $subOrder))->assertOk();
});

test('A2 OrderDetail actions take no client-supplied sub-order id', function () {
    // cancel()/confirmReceived()/submitReturn() act on the MOUNTED model only.
    $refl = new ReflectionClass(OrderDetail::class);
    $clientIdParams = [];

    foreach (['cancel', 'confirmReceived', 'submitReturn'] as $method) {
        foreach ($refl->getMethod($method)->getParameters() as $p) {
            $type = $p->getType();
            // A service injected by the container is not client input; a scalar is.
            if ($type instanceof ReflectionNamedType && $type->isBuiltin()) {
                $clientIdParams[] = "{$method}(\${$p->getName()})";
            }
        }
    }

    expect($clientIdParams)->toBe([]);
});

test('A3 a forged Livewire snapshot cannot repoint OrderDetail at another buyer order', function () {
    $victim = qaBuyer();
    $attacker = qaBuyer();
    // BOTH delivered, so confirmReceived() is a legal, side-effecting call —
    // if the repoint worked the victim's row WOULD move to completed.
    $victimSubOrder = qaSubOrder($victim, SubOrderStatus::Delivered);
    $attackerSubOrder = qaSubOrder($attacker, SubOrderStatus::Delivered);

    $component = Livewire::actingAs($attacker)->test(OrderDetail::class, ['subOrder' => $attackerSubOrder]);
    $json = json_encode($component->snapshot);

    // Livewire dehydrates a model prop as {"class":...,"key":N,"s":"mdl"}.
    expect($json)->toContain('"key":'.$attackerSubOrder->id);
    $tampered = str_replace('"key":'.$attackerSubOrder->id, '"key":'.$victimSubOrder->id, $json);
    expect($tampered)->toContain('"key":'.$victimSubOrder->id);

    // Control: the SAME call on an untampered snapshot really does complete
    // the attacker's own order — so a null result below is the guard, not a
    // broken request.
    $post = fn (string $snapshot) => $this->actingAs($attacker)->postJson(route('default-livewire.update'), [
        '_token' => csrf_token(),
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => [],
            'calls' => [['path' => '', 'method' => 'confirmReceived', 'params' => []]],
        ]],
    ], ['X-Livewire' => '1']);

    $post($json)->assertOk();
    expect($attackerSubOrder->fresh()->status)->toBe(SubOrderStatus::Completed);

    $post($tampered);

    expect($victimSubOrder->fresh()->status)->toBe(SubOrderStatus::Delivered);
});

test('A4 Orders component id-taking actions are scoped to the caller', function () {
    $victim = qaBuyer();
    $attacker = qaBuyer();

    $delivered = qaSubOrder($victim, SubOrderStatus::Delivered);
    $unpaid = Order::factory()->awaitingIpay88()->create(['user_id' => $victim->id]);
    SubOrder::factory()->status(SubOrderStatus::PendingPayment)->create(['order_id' => $unpaid->id]);

    $as = fn () => Livewire::actingAs($attacker)->test(Orders::class);

    expect(fn () => $as()->call('confirmReceived', $delivered->id))
        ->toThrow(ModelNotFoundException::class);
    expect(fn () => $as()->call('buyAgain', $delivered->id))
        ->toThrow(ModelNotFoundException::class);
    expect(fn () => $as()->call('cancelUnpaidOrder', $unpaid->id))
        ->toThrow(ModelNotFoundException::class);

    expect($delivered->fresh()->status)->toBe(SubOrderStatus::Delivered)
        ->and($unpaid->subOrders()->first()->status)->toBe(SubOrderStatus::PendingPayment);
});

test('A5 Addresses id-taking actions are scoped to the caller', function () {
    $victim = qaBuyer();
    $attacker = qaBuyer();
    $address = Address::factory()->create(['user_id' => $victim->id, 'is_default' => false]);

    $as = fn () => Livewire::actingAs($attacker)->test(Addresses::class);

    foreach (['edit', 'setDefault', 'delete'] as $method) {
        expect(fn () => $as()->call($method, $address->id))
            ->toThrow(ModelNotFoundException::class);
    }

    expect(Address::whereKey($address->id)->exists())->toBeTrue()
        ->and((bool) $address->fresh()->is_default)->toBeFalse();
});

test('A6 Subscriptions id-taking actions are scoped to the caller', function () {
    config(['subscriptions.enabled' => true]);
    $victim = qaBuyer();
    $attacker = qaBuyer();

    $product = Product::factory()->create();
    $address = Address::factory()->default()->create(['user_id' => $victim->id]);
    $subscription = Subscription::create([
        'user_id' => $victim->id,
        'address_id' => $address->id,
        'product_variant_id' => $product->variants->first()->id,
        'qty' => 1,
        'interval_days' => 30,
        'payment_method' => PaymentMethod::Cod,
        'status' => SubscriptionStatus::Active,
        'next_run_at' => now()->addDays(30),
    ]);

    $as = fn () => Livewire::actingAs($attacker)->test(Subscriptions::class);
    foreach (['pause', 'resume', 'cancel'] as $method) {
        $as()->call($method, $subscription->id);
    }

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active);
});

test('A7 Notifications markRead is scoped to the caller', function () {
    $victim = qaBuyer();
    $attacker = qaBuyer();
    $victim->notify(new SubOrderStatusNotification(qaSubOrder($victim), SubOrderStatus::Confirmed, 'buyer'));
    $notification = $victim->notifications()->first();

    Livewire::actingAs($attacker)->test(NotificationsPage::class)->call('markRead', $notification->id);

    expect($notification->fresh()->read_at)->toBeNull();
});

test('A8 Messages openConversation is scoped to the caller', function () {
    $victim = qaBuyer();
    $attacker = qaBuyer();
    $store = Store::factory()->approved()->create();
    $conversation = Conversation::create([
        'store_id' => $store->id,
        'buyer_id' => $victim->id,
        'last_message_at' => now(),
    ]);

    $component = Livewire::actingAs($attacker)->test(Messages::class)->call('openConversation', $conversation->id);

    expect($component->get('conversationId'))->toBeNull();
});

test('A9 ReviewOrder refuses another buyer at mount and at submit', function () {
    $victim = qaBuyer();
    $attacker = qaBuyer();
    $victimSubOrder = qaSubOrder($victim, SubOrderStatus::Completed);
    $attackerSubOrder = qaSubOrder($attacker, SubOrderStatus::Completed);

    // Mount against the victim's sub-order.
    Livewire::actingAs($attacker)->test(ReviewOrder::class, ['subOrder' => $victimSubOrder])->assertForbidden();

    // submit() takes a client-supplied order_item_id — is it scoped to the mounted sub-order?
    $victimItem = $victimSubOrder->items()->create([
        'product_id' => ($p = Product::factory()->create())->id,
        'product_variant_id' => $p->variants->first()->id,
        'product_name' => 'Victim item',
        'variant_label' => null,
        'unit_price_sen' => 2500,
        'qty' => 1,
        'line_total_sen' => 2500,
    ]);

    expect(fn () => Livewire::actingAs($attacker)
        ->test(ReviewOrder::class, ['subOrder' => $attackerSubOrder])
        ->call('submit', $victimItem->id))
        ->toThrow(ModelNotFoundException::class);

    expect(Review::query()->count())->toBe(0);
});

test('A10 GroupBuy Panel::start accepts a deal that belongs to a different product', function () {
    config(['groupbuy.enabled' => true]);

    $make = function (): GroupBuy {
        $product = Product::factory()->create();
        $product->variants->first()->update(['price_sen' => 10000, 'sale_price_sen' => null]);

        return GroupBuy::create([
            'store_id' => $product->store_id,
            'product_id' => $product->id,
            'product_variant_id' => $product->variants->first()->id,
            'group_price_sen' => 6000,
            'target_size' => 2,
            'team_window_hours' => 24,
            'status' => GroupBuyStatus::Active,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addWeek(),
        ]);
    };

    $dealA = $make();
    $dealB = $make();
    $buyer = qaBuyer();

    // Panel is bound to product A, but we pass deal B's id.
    Livewire::actingAs($buyer)
        ->test(Panel::class, ['product' => $dealA->product])
        ->call('start', $dealB->id);

    expect(GroupBuyTeam::where('group_buy_id', $dealB->id)->count())
        ->toBe(0, 'Panel bound to product A started a team on product B\'s deal');
});

test('A11 GroupBuy Panel::start refuses a non-live deal', function () {
    config(['groupbuy.enabled' => true]);

    $product = Product::factory()->create();
    $deal = GroupBuy::create([
        'store_id' => $product->store_id,
        'product_id' => $product->id,
        'product_variant_id' => $product->variants->first()->id,
        'group_price_sen' => 6000,
        'target_size' => 2,
        'team_window_hours' => 24,
        'status' => GroupBuyStatus::Active,
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->subDay(), // window closed
    ]);

    Livewire::actingAs(qaBuyer())
        ->test(Panel::class, ['product' => $product])
        ->call('start', $deal->id);

    expect(GroupBuyTeam::where('group_buy_id', $deal->id)->count())->toBe(0);
});

// ─────────────────────────────────────────────────────────────────────────────
// PART B — the order lifecycle
// ─────────────────────────────────────────────────────────────────────────────

test('B1 the happy path walks pending_payment → completed, DB row and buyer view following each step', function () {
    $buyer = qaBuyer();
    $subOrder = qaSubOrder($buyer, SubOrderStatus::PendingPayment);
    $service = app(SubOrderStatusService::class);

    $view = fn () => Livewire::actingAs($buyer)->test(OrderDetail::class, ['subOrder' => $subOrder->fresh()]);

    $view()->assertSee(SubOrderStatus::PendingPayment->label())
        ->assertDontSee(__('Cancel order'))
        ->assertDontSee(__('Download invoice'));

    $service->transition($subOrder, SubOrderStatus::Confirmed, ActorType::System);
    expect($subOrder->fresh()->status)->toBe(SubOrderStatus::Confirmed);
    $view()->assertSee(SubOrderStatus::Confirmed->label())
        ->assertSee(__('Cancel order'));

    $service->transition($subOrder->fresh(), SubOrderStatus::Processing, ActorType::Seller);
    expect($subOrder->fresh()->status)->toBe(SubOrderStatus::Processing);
    $view()->assertSee(SubOrderStatus::Processing->label())
        ->assertDontSee(__('Cancel order'));

    $service->transition($subOrder->fresh(), SubOrderStatus::Shipped, ActorType::Seller);
    $fresh = $subOrder->fresh();
    expect($fresh->status)->toBe(SubOrderStatus::Shipped)
        ->and($fresh->shipped_at)->not->toBeNull();
    $view()->assertSee(SubOrderStatus::Shipped->label())
        ->assertSee(__('Waiting for delivery — the seller marked this shipped.'))
        ->assertDontSee(__('Order received'));

    $service->transition($subOrder->fresh(), SubOrderStatus::Delivered, ActorType::Seller);
    $fresh = $subOrder->fresh();
    expect($fresh->status)->toBe(SubOrderStatus::Delivered)
        ->and($fresh->delivered_at)->not->toBeNull()
        ->and($fresh->auto_complete_at)->not->toBeNull();
    $view()->assertSee(SubOrderStatus::Delivered->label())
        ->assertSee(__('Order received'));

    $service->transition($subOrder->fresh(), SubOrderStatus::Completed, ActorType::Buyer, $buyer->id);
    $fresh = $subOrder->fresh();
    expect($fresh->status)->toBe(SubOrderStatus::Completed)
        ->and($fresh->completed_at)->not->toBeNull();
    $view()->assertSee(SubOrderStatus::Completed->label())
        ->assertDontSee(__('Order received'));

    // Every step wrote a history row (initial + 5 transitions).
    expect($subOrder->statusHistories()->count())->toBe(6);
});

test('B2 every illegal transition is refused', function () {
    $allowed = [
        'pending_payment' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered'],
        'delivered' => ['completed', 'return_requested'],
        'completed' => ['return_requested'],
        'return_requested' => ['returned', 'refunded', 'delivered', 'completed'],
        'returned' => ['refunded'],
        'cancelled' => [],
        'refunded' => [],
    ];

    $buyer = qaBuyer();
    $service = app(SubOrderStatusService::class);
    $silentlyAccepted = [];

    foreach ($allowed as $fromValue => $allowedTo) {
        $from = SubOrderStatus::from($fromValue);

        foreach (SubOrderStatus::cases() as $to) {
            if ($to === $from || in_array($to->value, $allowedTo, true)) {
                continue;
            }

            $subOrder = qaSubOrder($buyer, $from);

            try {
                $service->transition($subOrder, $to, ActorType::Admin);
                $silentlyAccepted[] = "{$from->value} → {$to->value} (row is now ".$subOrder->fresh()->status->value.')';
            } catch (InvalidArgumentException) {
                expect($subOrder->fresh()->status)->toBe($from);
            }
        }
    }

    expect($silentlyAccepted)->toBe([]);
});

test('B3 the right notifications fire at each step', function () {
    Notification::fake();

    $buyer = qaBuyer();
    $subOrder = qaSubOrder($buyer, SubOrderStatus::PendingPayment);
    $seller = $subOrder->store->user;
    $service = app(SubOrderStatusService::class);

    expect($seller)->not->toBeNull();

    $steps = [
        [SubOrderStatus::Confirmed, true, true],
        [SubOrderStatus::Processing, true, false],
        [SubOrderStatus::Shipped, true, false],
        [SubOrderStatus::Delivered, true, false],
        [SubOrderStatus::Completed, true, true],
    ];

    foreach ($steps as [$to, $buyerNotified, $sellerNotified]) {
        $service->transition($subOrder->fresh(), $to, ActorType::Seller);

        $matches = fn (User $user, string $audience) => fn (SubOrderStatusNotification $n) => $n->status === $to;

        if ($buyerNotified) {
            Notification::assertSentTo($buyer, SubOrderStatusNotification::class, $matches($buyer, 'buyer'));
        } else {
            Notification::assertNotSentTo($buyer, SubOrderStatusNotification::class, $matches($buyer, 'buyer'));
        }

        if ($sellerNotified) {
            Notification::assertSentTo($seller, SubOrderStatusNotification::class, $matches($seller, 'seller'));
        } else {
            Notification::assertNotSentTo($seller, SubOrderStatusNotification::class, $matches($seller, 'seller'));
        }
    }
});

test('B4 stock is decremented at checkout and restored on cancel', function () {
    $buyer = qaBuyer();
    [, $subOrder, $variant] = qaPlaceCodOrder($buyer, stock: 5, qty: 2);

    expect($variant->fresh()->stock)->toBe(3);

    app(OrderService::class)->cancel($subOrder, ActorType::Buyer, $buyer->id, 'changed my mind');

    expect($subOrder->fresh()->status)->toBe(SubOrderStatus::Cancelled)
        ->and($variant->fresh()->stock)->toBe(5);
});

test('B5 cancelling an already-cancelled sub-order must not restock twice', function () {
    $buyer = qaBuyer();
    [, $subOrder, $variant] = qaPlaceCodOrder($buyer, stock: 5, qty: 2);

    $orderService = app(OrderService::class);
    $orderService->cancel($subOrder, ActorType::Buyer, $buyer->id, 'first');
    // Second call: SubOrderStatusService no-ops (from === to), but does OrderService?
    $orderService->cancel($subOrder->fresh(), ActorType::Buyer, $buyer->id, 'duplicate');

    expect($variant->fresh()->stock)->toBe(5, 'stock was restocked twice by a duplicate cancel');
});

test('B6 cancel after ship is refused, from the service and from the buyer UI', function () {
    $buyer = qaBuyer();
    [, $subOrder, $variant] = qaPlaceCodOrder($buyer, stock: 5, qty: 2);

    $service = app(SubOrderStatusService::class);
    $service->transition($subOrder, SubOrderStatus::Processing, ActorType::Seller);
    $service->transition($subOrder->fresh(), SubOrderStatus::Shipped, ActorType::Seller);

    expect(fn () => app(OrderService::class)->cancel($subOrder->fresh(), ActorType::Buyer, $buyer->id, 'too late'))
        ->toThrow(InvalidArgumentException::class);

    $reason = CancellationReason::query()->active()->first()
        ?? CancellationReason::create(['label' => ['en' => 'Changed my mind'], 'is_active' => true]);

    Livewire::actingAs($buyer)
        ->test(OrderDetail::class, ['subOrder' => $subOrder->fresh()])
        ->set('cancelReasonId', $reason->id)
        ->call('cancel');

    expect($subOrder->fresh()->status)->toBe(SubOrderStatus::Shipped)
        ->and($variant->fresh()->stock)->toBe(3);
});

test('B7 a buyer cannot confirm receipt of a sub-order that is not delivered', function () {
    $buyer = qaBuyer();
    $subOrder = qaSubOrder($buyer, SubOrderStatus::Shipped);

    Livewire::actingAs($buyer)
        ->test(OrderDetail::class, ['subOrder' => $subOrder])
        ->call('confirmReceived');

    expect($subOrder->fresh()->status)->toBe(SubOrderStatus::Shipped);
});

test('B8 the buyer is notified when a return is marked returned', function () {
    Notification::fake();

    $buyer = qaBuyer();
    $subOrder = qaSubOrder($buyer, SubOrderStatus::Delivered);
    $service = app(SubOrderStatusService::class);

    $service->transition($subOrder, SubOrderStatus::ReturnRequested, ActorType::Buyer, $buyer->id);
    $service->transition($subOrder->fresh(), SubOrderStatus::Returned, ActorType::Seller);

    Notification::assertSentTo(
        $buyer,
        SubOrderStatusNotification::class,
        fn (SubOrderStatusNotification $n) => $n->status === SubOrderStatus::Returned,
    );
});
