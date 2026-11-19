<?php

use App\Enums\ActorType;
use App\Enums\GatewayPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Livewire\Admin\Orders\Detail;
use App\Livewire\Admin\Orders\Index;
use App\Livewire\Admin\Orders\Payments;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use App\Settings\Ipay88Settings;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

function oversightAdmin(): User
{
    test()->seed(RoleSeeder::class);

    $user = User::factory()->create(['two_factor_method' => 'email']); // admins need 2FA (EnsureAdmin)
    makeAdmin($user);

    return $user;
}

/**
 * Order + sub-order + one item (qty 2) fixture. Status set at insert is
 * test setup — production transitions still go through the service.
 */
function oversightSubOrder(SubOrderStatus $status = SubOrderStatus::Confirmed, array $orderAttributes = []): SubOrder
{
    $order = Order::factory()->create($orderAttributes);
    $store = Store::factory()->approved()->create();
    $product = Product::factory()->create(['store_id' => $store->id]);
    $variant = $product->variants->first();

    $subOrder = SubOrder::factory()->status($status)->create([
        'order_id' => $order->id,
        'store_id' => $store->id,
        'items_subtotal_sen' => 10000,
        'shipping_fee_sen' => 500,
        'shop_discount_sen' => 0,
        'total_sen' => 10500,
        'commission_rate' => '5.00',
    ]);

    $subOrder->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'product_name' => $product->getTranslation('name', 'en'),
        'variant_label' => $variant->options_label,
        'unit_price_sen' => 5000,
        'qty' => 2,
        'line_total_sen' => 10000,
    ]);

    return $subOrder;
}

test('orders oversight is admin-only', function () {
    $this->seed(RoleSeeder::class);

    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');

    $subOrder = oversightSubOrder();

    $this->actingAs($buyer)->get(route('admin.orders.index'))->assertForbidden();
    $this->actingAs($buyer)->get(route('admin.orders.show', $subOrder))->assertForbidden();
    $this->actingAs($buyer)->get(route('admin.payments.index'))->assertForbidden();

    $admin = oversightAdmin();
    $this->actingAs($admin)->get(route('admin.orders.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.orders.show', $subOrder))->assertOk()->assertSee($subOrder->sub_order_no);
    $this->actingAs($admin)->get(route('admin.payments.index'))->assertOk();
});

test('index filters by status, store and search', function () {
    $admin = oversightAdmin();
    $confirmed = oversightSubOrder(SubOrderStatus::Confirmed);
    $shipped = oversightSubOrder(SubOrderStatus::Shipped);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee($confirmed->sub_order_no)
        ->assertSee($shipped->sub_order_no)
        ->set('status', SubOrderStatus::Confirmed->value)
        ->assertSee($confirmed->sub_order_no)
        ->assertDontSee($shipped->sub_order_no)
        ->set('status', '')
        ->set('store', (string) $shipped->store_id)
        ->assertSee($shipped->sub_order_no)
        ->assertDontSee($confirmed->sub_order_no)
        ->set('store', '')
        ->set('search', $confirmed->order->order_no)
        ->assertSee($confirmed->sub_order_no)
        ->assertDontSee($shipped->sub_order_no);
});

test('force-cancel requires a reason, restocks and writes an admin history row', function () {
    $admin = oversightAdmin();
    $subOrder = oversightSubOrder(SubOrderStatus::Processing);

    $variant = $subOrder->items->first()->variant;
    $variant->update(['stock' => 3]);

    $component = Livewire::actingAs($admin)->test(Detail::class, ['subOrder' => $subOrder]);

    // No reason → validation error, nothing changes.
    $component->call('forceCancel')->assertHasErrors(['cancelReason' => 'required']);
    expect($subOrder->refresh()->status)->toBe(SubOrderStatus::Processing);

    $component
        ->set('cancelReason', 'Fraudulent order — buyer card disputed')
        ->call('forceCancel')
        ->assertHasNoErrors();

    $subOrder->refresh();
    expect($subOrder->status)->toBe(SubOrderStatus::Cancelled)
        ->and($subOrder->cancel_reason)->toBe('Fraudulent order — buyer card disputed')
        ->and($subOrder->cancelled_at)->not->toBeNull()
        ->and($variant->refresh()->stock)->toBe(5); // 3 + qty 2 restocked

    $history = $subOrder->statusHistories()->get()->last();
    expect($history->to_status)->toBe('cancelled')
        ->and($history->actor_type)->toBe(ActorType::Admin)
        ->and($history->actor_id)->toBe($admin->id);
});

test('force-cancel is blocked outside the transition map', function () {
    $admin = oversightAdmin();
    $subOrder = oversightSubOrder(SubOrderStatus::Shipped);

    Livewire::actingAs($admin)
        ->test(Detail::class, ['subOrder' => $subOrder])
        ->set('cancelReason', 'Too late to cancel')
        ->call('forceCancel');

    expect($subOrder->refresh()->status)->toBe(SubOrderStatus::Shipped);
});

test('mark refunded transitions return_requested → refunded and flips the order payment status', function () {
    $admin = oversightAdmin();
    $subOrder = oversightSubOrder(SubOrderStatus::ReturnRequested, [
        'payment_method' => PaymentMethod::Ipay88,
        'payment_status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);

    $payment = Payment::create([
        'order_id' => $subOrder->order_id,
        'gateway' => PaymentMethod::Ipay88,
        'ref_no' => $subOrder->order->order_no,
        'amount_sen' => $subOrder->order->grand_total_sen,
        'currency' => 'MYR',
        'status' => GatewayPaymentStatus::Success,
        'signature_valid' => true,
        'requery_result' => '00',
        'paid_at' => now(),
    ]);

    $component = Livewire::actingAs($admin)->test(Detail::class, ['subOrder' => $subOrder]);

    // The portal reference is mandatory.
    $component->call('markRefunded')->assertHasErrors(['refundReference' => 'required']);
    expect($subOrder->refresh()->status)->toBe(SubOrderStatus::ReturnRequested);

    $component
        ->set('refundReference', 'IP88-RFND-4521')
        ->call('markRefunded')
        ->assertHasNoErrors();

    $subOrder->refresh();
    // M-6: this used to assert requery_result === 'refunded: IP88-RFND-4521',
    // i.e. the portal reference stuffed into a free-text GATEWAY column, which
    // is the workaround the finding was about. It now goes through
    // RefundService, so the reference lands in the status history (asserted just
    // below) and the money columns actually move — which is what to check.
    expect($subOrder->status)->toBe(SubOrderStatus::Refunded)
        ->and($subOrder->order->payment_status)->toBe(PaymentStatus::Refunded)
        ->and($subOrder->refunded_sen)->toBe((int) $subOrder->total_sen)
        ->and($payment->refresh()->refunded_sen)->toBeGreaterThan(0);

    $history = $subOrder->statusHistories()->get()->last();
    expect($history->to_status)->toBe('refunded')
        ->and($history->actor_type)->toBe(ActorType::Admin)
        ->and($history->note)->toContain('IP88-RFND-4521');
});

test('mark refunded is blocked outside return_requested / returned', function () {
    $admin = oversightAdmin();
    $subOrder = oversightSubOrder(SubOrderStatus::Confirmed, [
        'payment_method' => PaymentMethod::Ipay88,
        'payment_status' => PaymentStatus::Paid,
    ]);

    Livewire::actingAs($admin)
        ->test(Detail::class, ['subOrder' => $subOrder])
        ->set('refundReference', 'IP88-RFND-0001')
        ->call('markRefunded');

    expect($subOrder->refresh()->status)->toBe(SubOrderStatus::Confirmed)
        ->and($subOrder->order->payment_status)->toBe(PaymentStatus::Paid);
});

test('payments grid highlights signature mismatches and can filter to them', function () {
    $admin = oversightAdmin();

    $goodOrder = Order::factory()->create();
    Payment::create([
        'order_id' => $goodOrder->id,
        'gateway' => PaymentMethod::Ipay88,
        'ref_no' => 'MPGOODREF1',
        'amount_sen' => 12500,
        'currency' => 'MYR',
        'status' => GatewayPaymentStatus::Success,
        'signature_valid' => true,
        'paid_at' => now(),
    ]);

    $badOrder = Order::factory()->create();
    Payment::create([
        'order_id' => $badOrder->id,
        'gateway' => PaymentMethod::Ipay88,
        'ref_no' => 'MPBADREF99',
        'amount_sen' => 9900,
        'currency' => 'MYR',
        'status' => GatewayPaymentStatus::Pending,
        'signature_valid' => false,
    ]);

    Livewire::actingAs($admin)
        ->test(Payments::class)
        ->assertSee('MPGOODREF1')
        ->assertSee('MPBADREF99')
        ->assertSeeHtml('bg-danger-tint') // mismatch row highlighted
        ->assertSee(__('Mismatch'))
        ->set('mismatchesOnly', true)
        ->assertSee('MPBADREF99')
        ->assertDontSee('MPGOODREF1');
});

test('requery button confirms a pending payment when iPay88 answers 00', function () {
    $admin = oversightAdmin();

    $settings = app(Ipay88Settings::class);
    $settings->merchant_code = 'M00001';
    $settings->merchant_key = 'TestKey123';
    $settings->sandbox = true;
    $settings->save();

    Http::fake(['*' => Http::response('00')]);

    $subOrder = oversightSubOrder(SubOrderStatus::PendingPayment, [
        'payment_method' => PaymentMethod::Ipay88,
        'payment_status' => PaymentStatus::Pending,
        'expires_at' => now()->addHour(),
    ]);

    $payment = Payment::create([
        'order_id' => $subOrder->order_id,
        'gateway' => PaymentMethod::Ipay88,
        'ref_no' => $subOrder->order->order_no,
        'amount_sen' => $subOrder->order->grand_total_sen,
        'currency' => 'MYR',
        'status' => GatewayPaymentStatus::Pending,
        'signature_valid' => true,
    ]);

    Livewire::actingAs($admin)
        ->test(Payments::class)
        ->call('requery', $payment->id);

    $payment->refresh();
    expect($payment->status)->toBe(GatewayPaymentStatus::Success)
        ->and($payment->requery_result)->toBe('00')
        ->and($payment->paid_at)->not->toBeNull();

    $order = $subOrder->order->fresh();
    expect($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($subOrder->refresh()->status)->toBe(SubOrderStatus::Confirmed);
});

test('the payments grid separates a refund the gateway made from one a human still owes', function () {
    $admin = oversightAdmin();

    /** A refunded payment row with a given gateway outcome. */
    $refunded = function (string $ref, ?bool $gatewayOk) {
        return Payment::create([
            'order_id' => Order::factory()->create()->id,
            'gateway' => PaymentMethod::Ipay88,
            'ref_no' => $ref,
            'amount_sen' => 5000,
            'currency' => 'MYR',
            'status' => GatewayPaymentStatus::Success,
            'refunded_sen' => 5000,
            'refunded_at' => now(),
            'gateway_refund_ok' => $gatewayOk,
            'signature_valid' => true,
        ]);
    };

    $refunded('MPOWED0001', false);  // gateway did not do it — a human must
    $refunded('MPDONE0001', true);   // gateway confirmed
    $refunded('MPCODD0001', null);   // COD: nothing was ever owed at a gateway

    $component = Livewire::actingAs($admin)->test(Payments::class);

    // "Gateway confirmed" appears nowhere but inside a row cell, so seeing it
    // proves the cell rendered — unlike "Needs portal refund", which is also the
    // filter's own label and would pass on a page with no matching rows at all.
    $component->assertSee('MPOWED0001')
        ->assertSee('MPDONE0001')
        ->assertSee('MPCODD0001')
        ->assertSee(__('Gateway confirmed'));

    // The flagged cell, counted rather than merely seen: one filter label plus
    // exactly one flagged row. A hardcoded label would score 1 and fail here.
    expect(substr_count($component->html(), __('Needs portal refund')))->toBe(2);

    $component->set('needsPortalRefund', true)
        ->assertSee('MPOWED0001')
        ->assertDontSee('MPDONE0001')   // control: a confirmed refund is not owed
        ->assertDontSee('MPCODD0001')   // control: neither is a COD one
        ->assertDontSee(__('Gateway confirmed'));

    expect(substr_count($component->html(), __('Needs portal refund')))->toBe(2);
});

test('admin order table sorts safely and combines filters with search', function () {
    $admin = oversightAdmin();
    $small = oversightSubOrder(orderAttributes: ['payment_method' => PaymentMethod::Cod]);
    $large = oversightSubOrder(orderAttributes: ['payment_method' => PaymentMethod::Cod]);
    $small->update(['total_sen' => 1000]);
    $large->update(['total_sen' => 9000]);

    Livewire::actingAs($admin)->test(Index::class)
        ->call('sortBy', 'total')->assertSeeInOrder([$small->sub_order_no, $large->sub_order_no])
        ->assertSeeHtml('aria-sort="ascending"')
        ->call('sortBy', 'total')->assertSeeInOrder([$large->sub_order_no, $small->sub_order_no])
        ->assertSeeHtml('aria-sort="descending"')
        ->call('sortBy', 'unknown')->assertSet('sort', 'total')
        ->set('sort', 'unknown')->set('direction', 'invalid')->assertSee($small->sub_order_no)
        ->set('search', $large->order->order_no)->set('status', 'confirmed')->set('store', (string) $large->store_id)->set('method', 'cod')
        ->assertSee($large->sub_order_no)->assertDontSee($small->sub_order_no)->assertSee('Showing 1 to 1 of 1')
        ->set('search', 'no match')->assertSee('Showing 0 to 0 of 0');
});

test('admin order table pages and resets the page when a filter changes', function () {
    $admin = oversightAdmin();
    $store = Store::factory()->approved()->create();
    SubOrder::factory()->count(21)->create(['store_id' => $store->id]);
    Livewire::actingAs($admin)->test(Index::class)->assertSee('Showing 1 to 20 of 21')
        ->call('gotoPage', 2)->assertSee('Showing 21 to 21 of 21')
        ->set('store', (string) $store->id)->assertSee('Showing 1 to 20 of 21')
        ->set('perPage', 50)->assertSee('Showing 1 to 21 of 21');
});

/** HB-G122: a refunded online payment the gateway never refunded (money owed in the portal). */
function portalOwedPayment(string $ref = 'MPOWED0122'): Payment
{
    return Payment::create([
        'order_id' => Order::factory()->create()->id,
        'gateway' => PaymentMethod::Ipay88,
        'ref_no' => $ref,
        'amount_sen' => 5000,
        'currency' => 'MYR',
        'status' => GatewayPaymentStatus::Success,
        'refunded_sen' => 5000,
        'refunded_at' => now(),
        'gateway_refund_ok' => false,
        'signature_valid' => true,
    ]);
}

test('an admin marks a portal-owed refund settled, and it is audit-logged', function () {
    $admin = oversightAdmin();
    $payment = portalOwedPayment();

    Livewire::actingAs($admin)
        ->test(Payments::class)
        ->call('markRefundSettled', $payment->id, '  PORTAL-REF-9  ')
        ->assertOk();

    expect($payment->fresh()->gateway_refund_ok)->toBeTrue();

    $log = Activity::query()->where('description', 'payment refund settled in portal')->get();
    expect($log)->toHaveCount(1)
        ->and($log->first()->causer_id)->toBe($admin->id)
        ->and($log->first()->subject_id)->toBe($payment->id)
        ->and($log->first()->properties->all())->toBe([
            'payment_id' => $payment->id,
            'reference' => 'PORTAL-REF-9',
            'old' => ['gateway_refund_ok' => false],
            'attributes' => ['gateway_refund_ok' => true],
        ]);

    // The row renders on the audit-log screen with its old -> new diff.
    $this->actingAs($admin)->get(route('admin.system.audit'))
        ->assertOk()
        ->assertSee('payment refund settled in portal')
        ->assertSee('gateway_refund_ok:');

    // Replay on an already-settled payment is a no-op: no second audit row.
    Livewire::actingAs($admin)->test(Payments::class)->call('markRefundSettled', $payment->id, null);
    expect(Activity::query()->where('description', 'payment refund settled in portal')->count())->toBe(1);
});

test('a non-admin cannot mark a portal refund settled', function () {
    $this->seed(RoleSeeder::class);
    $payment = portalOwedPayment();

    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');

    // Admin role but no orders.manage permission: the route gate would stop
    // them, and so must the action.
    $adminNoPerms = User::factory()->create(['two_factor_method' => 'email']);
    $adminNoPerms->assignRole('admin');
    $adminNoPerms->syncPermissions([]);

    foreach ([$buyer, $adminNoPerms] as $user) {
        Livewire::actingAs($user)
            ->test(Payments::class)
            ->call('markRefundSettled', $payment->id, 'X')
            ->assertForbidden();
    }

    expect($payment->fresh()->gateway_refund_ok)->toBeFalse()
        ->and(Activity::query()->where('description', 'payment refund settled in portal')->count())->toBe(0);
});

test('the settle control shows only on a payment flagged as owed in the portal', function () {
    $admin = oversightAdmin();
    portalOwedPayment('MPOWED0122');

    foreach (['MPDONE0122' => true, 'MPCODD0122' => null] as $ref => $ok) {
        Payment::create([
            'order_id' => Order::factory()->create()->id,
            'gateway' => PaymentMethod::Ipay88,
            'ref_no' => $ref,
            'amount_sen' => 5000,
            'currency' => 'MYR',
            'status' => GatewayPaymentStatus::Success,
            'refunded_sen' => 5000,
            'refunded_at' => now(),
            'gateway_refund_ok' => $ok,
            'signature_valid' => true,
        ]);
    }

    $html = Livewire::actingAs($admin)->test(Payments::class)->html();

    // Three refunded rows, one flagged: exactly one control.
    expect(substr_count($html, 'data-settle-control'))->toBe(1)
        ->and($html)->toContain(__('Mark refund settled in portal'));

    Payment::query()->update(['gateway_refund_ok' => true]);

    expect(Livewire::actingAs($admin)->test(Payments::class)->html())
        ->not->toContain('data-settle-control');
});
