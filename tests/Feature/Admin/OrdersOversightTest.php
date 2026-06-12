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

function oversightAdmin(): User
{
    test()->seed(RoleSeeder::class);

    $user = User::factory()->create(['two_factor_method' => 'email']); // admins need 2FA (EnsureAdmin)
    $user->assignRole('admin');

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
    expect($subOrder->status)->toBe(SubOrderStatus::Refunded)
        ->and($subOrder->order->payment_status)->toBe(PaymentStatus::Refunded)
        ->and($payment->refresh()->requery_result)->toBe('refunded: IP88-RFND-4521');

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
