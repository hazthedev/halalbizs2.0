<?php

use App\Enums\ActorType;
use App\Enums\SubOrderStatus;
use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Models\Order;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use App\Services\OrderService;
use Illuminate\Support\Facades\DB;

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
