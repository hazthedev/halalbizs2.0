<?php

use App\Enums\ActorType;
use App\Enums\SubOrderStatus;
use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Http\Controllers\Api\OrderController;
use App\Models\Order;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use App\Services\OrderService;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/*
 * Gate R2 on #225 (2026-11-17): two unpaid sub-orders of one order cancelled at
 * the same moment each saw the other as still active, so neither gave back the
 * order-level (platform / shipping) voucher use. cancel() must lock the parent
 * order before the sub-order (the order-first order used by payment confirm and
 * expiry) and re-read the siblings with a current (locking) read, so the last
 * cancel to commit releases the order-level use exactly once.
 *
 * MySQL only, like LockDisciplineTest: SQLite compiles lockForUpdate() to
 * nothing. A real two-connection run of the interleave is recorded in PR #225.
 *
 * Gate R3: the API buyer cancel locked the sub-order and then entered cancel(),
 * which locks the parent: InnoDB 1213 against a service cancel holding the
 * parent. One lock order everywhere: parent order, then sub-orders, then
 * variants (SubOrder::lockForUpdateWithOrder).
 */
beforeEach(function () {
    expect(DB::connection()->getDriverName())->toBe('mysql');
});

test('cancel locks the parent order first and re-reads siblings under a lock', function () {
    $order = Order::factory()->awaitingIpay88()->create();
    [$a, $b] = collect(range(1, 2))->map(fn () => SubOrder::factory()->status(SubOrderStatus::PendingPayment)->create([
        'order_id' => $order->id, 'store_id' => Store::factory()->approved()->create()->id,
    ]))->all();
    $voucher = Voucher::create([
        'code' => 'RACE5', 'scope' => VoucherScope::Platform, 'type' => VoucherType::Fixed, 'value_sen' => 500,
        'min_spend_sen' => 0, 'quota' => 1, 'per_user_limit' => 1, 'used_count' => 1,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'is_active' => true,
    ]);
    VoucherUsage::create(['voucher_id' => $voucher->id, 'user_id' => $order->user_id, 'order_id' => $order->id, 'sub_order_id' => null, 'discount_sen' => 500]);

    $sql = [];
    DB::listen(function ($query) use (&$sql) {
        $sql[] = strtolower($query->sql);
    });

    app(OrderService::class)->cancel($a, ActorType::Admin);

    $orderLock = collect($sql)->search(fn ($q) => preg_match('/from `orders` .*for update/', $q));
    $subOrderLock = collect($sql)->search(fn ($q) => preg_match('/from `sub_orders` .*for update/', $q));
    $siblingRead = collect($sql)->first(fn ($q) => str_contains($q, '`sub_orders`') && str_contains($q, '`status` !='));

    expect($orderLock)->not->toBeFalse('cancel() never locked the parent order row')
        ->and($orderLock)->toBeLessThan($subOrderLock, 'order lock must come before the sub-order lock')
        ->and($siblingRead)->toContain('for update');

    // Sequential sanity: the last sibling gives the order-level use back once.
    expect($voucher->fresh()->used_count)->toBe(1);
    app(OrderService::class)->cancel($b, ActorType::Admin);
    expect($voucher->fresh()->used_count)->toBe(0)
        ->and(VoucherUsage::where('order_id', $order->id)->count())->toBe(0);
});

test('API buyer cancel takes the parent order lock before the sub-order lock', function () {
    $this->seed(RoleSeeder::class);
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $order = Order::factory()->create(['user_id' => $buyer->id]);
    $subOrder = SubOrder::factory()->status(SubOrderStatus::Confirmed)->create(['order_id' => $order->id]);

    $sql = [];
    DB::listen(function ($query) use (&$sql) {
        $sql[] = strtolower($query->sql);
    });

    $this->withToken($buyer->createToken('pest')->plainTextToken)
        ->postJson("/api/v1/orders/{$subOrder->id}/cancel")
        ->assertOk();

    $orderLock = collect($sql)->search(fn ($q) => preg_match('/from `orders` .*for update/', $q));
    $subOrderLock = collect($sql)->search(fn ($q) => preg_match('/from `sub_orders` .*for update/', $q));

    expect($orderLock)->not->toBeFalse()
        ->and($orderLock)->toBeLessThan($subOrderLock, 'sub-order locked before its parent order: deadlocks against cancel()');
});

test('buyer cancel returns a conflict when a service cancel commits before its parent lock', function () {
    $default = DB::getDefaultConnection();
    $connection = config("database.connections.$default");
    config(['database.connections.cancel_api_race' => $connection, 'database.connections.cancel_service_race' => $connection]);
    DB::setDefaultConnection('cancel_api_race');
    $buyer = $order = null;

    try {
        // Committed fixtures are visible to both independent connections;
        // the suite's RefreshDatabase transaction stays on the default one.
        [$buyer, $order, $subOrder] = User::withoutEvents(function () {
            $buyer = User::factory()->create();
            $order = Order::factory()->create(['user_id' => $buyer->id]);
            $store = Store::factory()->approved()->create(['user_id' => $buyer->id, 'slug' => 'cancel-race-'.$buyer->id]);
            $subOrder = SubOrder::factory()->status(SubOrderStatus::Confirmed)->create(['order_id' => $order->id, 'store_id' => $store->id]);

            return [$buyer, $order, $subOrder];
        });
        Event::fake();

        $interleaved = false;
        DB::connection()->beforeExecuting(function ($sql) use (&$interleaved, $subOrder) {
            if ($interleaved || ! str_contains(strtolower($sql), 'from `orders`') || ! str_contains(strtolower($sql), 'for update')) {
                return;
            }

            $interleaved = true;
            DB::setDefaultConnection('cancel_service_race');
            try {
                app(OrderService::class)->cancel(SubOrder::findOrFail($subOrder->id), ActorType::Admin);
            } finally {
                DB::setDefaultConnection('cancel_api_race');
            }
        });

        $request = Request::create('/api/v1/orders/'.$subOrder->id.'/cancel', 'POST');
        app()->instance('request', $request);
        $request->setUserResolver(fn () => $buyer);
        $response = app(OrderController::class)->cancel($request, $subOrder->id, app(OrderService::class));

        expect($interleaved)->toBeTrue()
            ->and($response->getStatusCode())->toBe(409)
            ->and($subOrder->fresh()->status)->toBe(SubOrderStatus::Cancelled);
    } finally {
        // These fixtures were committed outside RefreshDatabase: remove them.
        if ($order !== null) {
            DB::connection('cancel_api_race')->table('orders')->where('id', $order->id)->delete();
        }
        if ($buyer !== null) {
            DB::connection('cancel_api_race')->table('users')->where('id', $buyer->id)->delete();
        }
        DB::setDefaultConnection($default);
        DB::purge('cancel_api_race');
        DB::purge('cancel_service_race');
    }
});
