<?php

/**
 * QA sweep: seller isolation / cross-tenant writes.
 *
 * Method arguments and non-#[Locked] public scalars are client-controlled in
 * Livewire; a mounted Eloquent model is not (signed snapshot). So every test
 * here drives the CLIENT-CONTROLLED surface: a foreign id passed as a method
 * argument, or written onto an unlocked public property, then the mutating
 * action called. Each assertion is on a DB row, not on component internals.
 */

use App\Enums\BoostStatus;
use App\Enums\GroupBuyStatus;
use App\Enums\LiveSessionStatus;
use App\Enums\ProductStatus;
use App\Enums\SubOrderStatus;
use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Livewire\Seller\Boosts;
use App\Livewire\Seller\GroupBuys;
use App\Livewire\Seller\LiveSessions;
use App\Livewire\Seller\Orders\Detail;
use App\Livewire\Seller\Products\Form;
use App\Livewire\Seller\Vouchers\Index;
use App\Livewire\Storefront\Account\OrderDetail;
use App\Models\CancellationReason;
use App\Models\GroupBuy;
use App\Models\LiveSession;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductBoost;
use App\Models\ProductQuestion;
use App\Models\Review;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function sisoSeller(): User
{
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('seller');
    Store::factory()->approved()->create(['user_id' => $user->id]);

    return $user->fresh();
}

function sisoSubOrder(User $seller, SubOrderStatus $status = SubOrderStatus::Confirmed): SubOrder
{
    return SubOrder::factory()->status($status)->create([
        'store_id' => $seller->store->id,
        'order_id' => Order::factory(),
    ]);
}

function sisoLiveSession(User $seller, ?Product $railProduct = null): LiveSession
{
    $session = LiveSession::create([
        'store_id' => $seller->store->id,
        'title' => 'Session '.uniqid(),
        'slug' => 'sess-'.uniqid(),
        'status' => LiveSessionStatus::Scheduled,
    ]);

    if ($railProduct !== null) {
        $session->products()->attach($railProduct->id, ['position' => 0]);
    }

    return $session;
}

// ─────────────────────────────────────────────────────────────────────────
// Orders\Detail — the mounted model, and the unlocked scalars around it
// ─────────────────────────────────────────────────────────────────────────

test('(a) mounting the seller order detail with another store\'s sub-order aborts 403', function () {
    $victim = sisoSeller();
    $attacker = sisoSeller();
    $subOrder = sisoSubOrder($victim);

    Livewire::actingAs($attacker)
        ->test(Detail::class, ['subOrder' => $subOrder])
        ->assertForbidden();

    expect($subOrder->refresh()->status)->toBe(SubOrderStatus::Confirmed);
});

test('(b) ship() refuses a foreign sub-order id written onto the unlocked shippingSubOrderId property', function () {
    $victim = sisoSeller();
    $attacker = sisoSeller();

    $foreign = sisoSubOrder($victim, SubOrderStatus::Processing);
    $own = sisoSubOrder($attacker, SubOrderStatus::Processing);

    expect(fn () => Livewire::actingAs($attacker)
        ->test(Detail::class, ['subOrder' => $own])
        ->set('shippingSubOrderId', $foreign->id)
        ->set('courier', 'J&T Express')
        ->set('trackingNo', 'JT1234567890MY')
        ->call('ship'))
        ->toThrow(ModelNotFoundException::class);

    $foreign->refresh();
    expect($foreign->status)->toBe(SubOrderStatus::Processing)
        ->and($foreign->tracking_no)->toBeNull();
});

test('(b) openShipModal() refuses a foreign sub-order id passed as a method argument', function () {
    $victim = sisoSeller();
    $attacker = sisoSeller();

    $foreign = sisoSubOrder($victim, SubOrderStatus::Processing);
    $own = sisoSubOrder($attacker, SubOrderStatus::Processing);

    expect(fn () => Livewire::actingAs($attacker)
        ->test(Detail::class, ['subOrder' => $own])
        ->call('openShipModal', $foreign->id))
        ->toThrow(ModelNotFoundException::class);
});

test('cancelOrder() accepts a cancellation reason an admin has DEACTIVATED', function () {
    $seller = sisoSeller();
    $subOrder = sisoSubOrder($seller, SubOrderStatus::Confirmed);

    $retired = CancellationReason::create([
        'label' => ['en' => 'Retired internal reason', 'ms' => 'Sebab lama'],
        'is_active' => false,
        'position' => 99,
    ]);

    Livewire::actingAs($seller)
        ->test(Detail::class, ['subOrder' => $subOrder])
        ->set('cancelReasonId', $retired->id)
        ->call('cancelOrder');

    // The picker only ever renders active()->get(); an inactive reason should
    // not be selectable. If this cancel went through, the validation is
    // `exists:` only and the is_active flag is decorative.
    $subOrder->refresh();

    expect($subOrder->cancel_reason)
        ->not->toBe('Retired internal reason', 'a deactivated cancellation reason was written to the sub-order')
        ->and($subOrder->status)->not->toBe(SubOrderStatus::Cancelled);
});

/**
 * CONTROL for the test above: the BUYER cancel path is the same feature written
 * by a different author, and it rejects the identical inactive reason twice
 * over (Rule::exists()->where('is_active', true) AND ->active()->findOrFail()).
 * So the seller path's omission is a divergence, not the intended design.
 */
test('CONTROL: the buyer cancel path rejects the same deactivated reason', function () {
    $seller = sisoSeller();
    $subOrder = sisoSubOrder($seller, SubOrderStatus::Confirmed);
    $buyer = $subOrder->order->user;

    $retired = CancellationReason::create([
        'label' => ['en' => 'Retired internal reason', 'ms' => 'Sebab lama'],
        'is_active' => false,
        'position' => 99,
    ]);

    Livewire::actingAs($buyer)
        ->test(OrderDetail::class, ['subOrder' => $subOrder])
        ->set('cancelling', true)
        ->set('cancelReasonId', $retired->id)
        ->call('cancel')
        ->assertHasErrors('cancelReasonId');

    $subOrder->refresh();

    expect($subOrder->status)->toBe(SubOrderStatus::Confirmed)
        ->and($subOrder->cancel_reason)->toBeNull();
});

// ─────────────────────────────────────────────────────────────────────────
// Products\Form — the #[Locked] productId claim
// ─────────────────────────────────────────────────────────────────────────

test('#[Locked] productId cannot be repointed at another store\'s product from the client', function () {
    $victim = sisoSeller();
    $attacker = sisoSeller();

    $foreign = Product::factory()->create(['store_id' => $victim->store->id]);
    $own = Product::factory()->create(['store_id' => $attacker->store->id]);

    expect(fn () => Livewire::actingAs($attacker)
        ->test(Form::class, ['product' => $own])
        ->set('productId', $foreign->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    expect($foreign->refresh()->store_id)->toBe($victim->store->id);
});

test('removeMedia() cannot delete an image belonging to another store\'s product', function () {
    $victim = sisoSeller();
    $attacker = sisoSeller();

    $foreign = Product::factory()->create(['store_id' => $victim->store->id]);
    $own = Product::factory()->create(['store_id' => $attacker->store->id]);

    $file = tempnam(sys_get_temp_dir(), 'qa').'.png';
    file_put_contents($file, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    $media = $foreign->addMedia($file)->preservingOriginal()->toMediaCollection('images');

    Livewire::actingAs($attacker)
        ->test(Form::class, ['product' => $own])
        ->call('removeMedia', $media->id);

    expect($foreign->fresh()->getMedia('images'))->toHaveCount(1);
});

// ─────────────────────────────────────────────────────────────────────────
// LiveSessions — the two unscoped Product::find() calls
// ─────────────────────────────────────────────────────────────────────────

test('feature() cannot spotlight another store\'s product on my own session', function () {
    $victim = sisoSeller();
    $attacker = sisoSeller();

    $ownProduct = Product::factory()->create(['store_id' => $attacker->store->id]);
    $foreignProduct = Product::factory()->create(['store_id' => $victim->store->id]);
    $session = sisoLiveSession($attacker, $ownProduct);

    Livewire::actingAs($attacker)
        ->test(LiveSessions::class)
        ->call('feature', $session->id, $foreignProduct->id);

    expect($session->refresh()->featured_product_id)->toBeNull();
});

test('addProduct() cannot pull another store\'s product into my rail', function () {
    $victim = sisoSeller();
    $attacker = sisoSeller();

    $foreignProduct = Product::factory()->create(['store_id' => $victim->store->id]);
    $session = sisoLiveSession($attacker);

    Livewire::actingAs($attacker)
        ->test(LiveSessions::class)
        ->set('managingId', $session->id)
        ->set('addProductId', $foreignProduct->id)
        ->call('addProduct');

    expect($session->refresh()->products()->count())->toBe(0);
});

test('removeProduct() cannot strip a product from another store\'s session', function () {
    $victim = sisoSeller();
    $attacker = sisoSeller();

    $victimProduct = Product::factory()->create(['store_id' => $victim->store->id]);
    $victimSession = sisoLiveSession($victim, $victimProduct);

    Livewire::actingAs($attacker)
        ->test(LiveSessions::class)
        ->call('removeProduct', $victimSession->id, $victimProduct->id);

    expect($victimSession->refresh()->products()->count())->toBe(1);
});

test('goLive() and end() cannot drive another store\'s session', function () {
    $victim = sisoSeller();
    $attacker = sisoSeller();

    $victimSession = sisoLiveSession($victim);

    Livewire::actingAs($attacker)
        ->test(LiveSessions::class)
        ->call('goLive', $victimSession->id)
        ->call('end', $victimSession->id);

    expect($victimSession->refresh()->status)->toBe(LiveSessionStatus::Scheduled)
        ->and($victimSession->started_at)->toBeNull()
        ->and($victimSession->ended_at)->toBeNull();
});

// ─────────────────────────────────────────────────────────────────────────
// The rest of the seller panel: every mutating action taking a client id
// ─────────────────────────────────────────────────────────────────────────

test('Vouchers editingId is #[Locked], so save() cannot be repointed at another store\'s voucher', function () {
    $victim = sisoSeller();
    $attacker = sisoSeller();

    $foreign = Voucher::create([
        'scope' => VoucherScope::Shop,
        'store_id' => $victim->store->id,
        'code' => 'VICTIM10',
        'type' => VoucherType::Fixed,
        'value_sen' => 1000,
        'min_spend_sen' => 0,
        'per_user_limit' => 1,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addWeek(),
        'is_active' => true,
    ]);

    expect(fn () => Livewire::actingAs($attacker)
        ->test(Index::class)
        ->set('editingId', $foreign->id)
        ->set('code', 'STOLEN')
        ->set('type', VoucherType::Fixed->value)
        ->set('value', '99.00')
        ->set('perUserLimit', '1')
        ->set('startsAt', now()->format('Y-m-d\TH:i'))
        ->set('endsAt', now()->addWeek()->format('Y-m-d\TH:i'))
        ->call('save'))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    $foreign->refresh();
    expect($foreign->code)->toBe('VICTIM10')
        ->and($foreign->value_sen)->toBe(1000)
        ->and($foreign->store_id)->toBe($victim->store->id);
});

test('Questions hide() and saveAnswer() cannot touch another store\'s question', function () {
    $victim = sisoSeller();
    $attacker = sisoSeller();
    $buyer = User::factory()->create();

    $product = Product::factory()->create(['store_id' => $victim->store->id]);
    $question = ProductQuestion::create([
        'product_id' => $product->id,
        'store_id' => $victim->store->id,
        'user_id' => $buyer->id,
        'question' => 'Is this halal certified?',
    ]);

    Livewire::actingAs($attacker)
        ->test(App\Livewire\Seller\Questions\Index::class)
        ->call('hide', $question->id)
        ->assertNotFound();

    Livewire::actingAs($attacker)
        ->test(App\Livewire\Seller\Questions\Index::class)
        ->set('answeringId', $question->id)
        ->set('answerText', 'Answered by the wrong shop.')
        ->call('saveAnswer')
        ->assertNotFound();

    $question->refresh();
    expect($question->is_hidden)->toBeFalse()
        ->and($question->answer)->toBeNull();
});

test('Reviews saveReply() cannot reply on another store\'s review through replyingId', function () {
    $victim = sisoSeller();
    $attacker = sisoSeller();
    $buyer = User::factory()->create();

    $product = Product::factory()->create(['store_id' => $victim->store->id]);
    $subOrder = sisoSubOrder($victim, SubOrderStatus::Completed);
    $item = OrderItem::create([
        'sub_order_id' => $subOrder->id,
        'product_id' => $product->id,
        'product_variant_id' => $product->variants->first()->id,
        'product_name' => 'Item',
        'unit_price_sen' => 1000,
        'list_price_sen' => 1000,
        'qty' => 1,
        'line_total_sen' => 1000,
    ]);

    $review = Review::create([
        'order_item_id' => $item->id,
        'product_id' => $product->id,
        'store_id' => $victim->store->id,
        'user_id' => $buyer->id,
        'rating' => 5,
        'comment' => 'Great product, arrived quickly.',
    ]);

    Livewire::actingAs($attacker)
        ->test(App\Livewire\Seller\Reviews\Index::class)
        ->set('replyingId', $review->id)
        ->set('replyText', 'Thanks from the wrong shop!')
        ->call('saveReply')
        ->assertNotFound();

    expect($review->refresh()->seller_reply)->toBeNull();
});

test('GroupBuys end() cannot end another store\'s deal', function () {
    $victim = sisoSeller();
    $attacker = sisoSeller();

    $product = Product::factory()->create(['store_id' => $victim->store->id]);
    $deal = GroupBuy::create([
        'store_id' => $victim->store->id,
        'product_id' => $product->id,
        'product_variant_id' => $product->variants->first()->id,
        'group_price_sen' => 500,
        'target_size' => 3,
        'team_window_hours' => 24,
        'status' => GroupBuyStatus::Active,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addWeek(),
    ]);

    Livewire::actingAs($attacker)
        ->test(GroupBuys::class)
        ->call('end', $deal->id);

    expect($deal->refresh()->status)->toBe(GroupBuyStatus::Active);
});

test('Boosts cancel() cannot cancel another store\'s boost', function () {
    $victim = sisoSeller();
    $attacker = sisoSeller();

    $product = Product::factory()->create(['store_id' => $victim->store->id]);
    $boost = ProductBoost::create([
        'product_id' => $product->id,
        'store_id' => $victim->store->id,
        'starts_at' => now(),
        'ends_at' => now()->addDays(7),
        'amount_sen' => 700,
        'status' => BoostStatus::Active,
    ]);

    Livewire::actingAs($attacker)
        ->test(Boosts::class)
        ->call('cancel', $boost->id)
        ->assertNotFound();

    expect($boost->refresh()->status)->toBe(BoostStatus::Active);
});

test('Products delist() cannot delist another store\'s live product', function () {
    $victim = sisoSeller();
    $attacker = sisoSeller();

    $foreign = Product::factory()->create([
        'store_id' => $victim->store->id,
        'status' => ProductStatus::Live,
    ]);

    expect(fn () => Livewire::actingAs($attacker)
        ->test(App\Livewire\Seller\Products\Index::class)
        ->call('delist', $foreign->id))
        ->toThrow(ModelNotFoundException::class);

    expect($foreign->refresh()->status)->toBe(ProductStatus::Live);
});
