<?php

use App\Enums\GatewayPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\SubOrderStatus;
use App\Enums\ProductStatus;
use App\Livewire\Admin\Buyers\Index as Buyers;
use App\Livewire\Admin\Catalog\Moderation;
use App\Livewire\Admin\Catalog\Reviews;
use App\Livewire\Admin\Notifications;
use App\Livewire\Admin\Orders\Payments;
use App\Livewire\Admin\Sellers\Stores;
use App\Livewire\Admin\System\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Review;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

// HB-G156: admin lists on the standard table (sort, search in a card, skeleton, one footer).

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $admin = User::factory()->create(['two_factor_method' => 'email']);
    makeAdmin($admin);
    $this->admin = $admin;
    $this->actingAs($admin);
});


function stdReview(int $rating): Review
{
    $buyer = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $buyer->id]);
    $subOrder = SubOrder::factory()->status(SubOrderStatus::Completed)->create(['order_id' => $order->id]);
    $product = Product::factory()->create(['store_id' => $subOrder->store_id]);
    $item = $subOrder->items()->create([
        'product_id' => $product->id, 'product_variant_id' => $product->variants()->first()->id,
        'product_name' => $product->getTranslation('name', 'en'), 'unit_price_sen' => 2500, 'qty' => 1, 'line_total_sen' => 2500,
    ]);

    return Review::create([
        'order_item_id' => $item->id, 'product_id' => $product->id, 'store_id' => $subOrder->store_id,
        'user_id' => $buyer->id, 'rating' => $rating, 'comment' => 'Fine.',
    ]);
}

function stdPayment(string $ref): Payment
{
    $order = Order::factory()->create(['payment_method' => PaymentMethod::Ipay88]);

    return Payment::create([
        'order_id' => $order->id, 'gateway' => PaymentMethod::Ipay88, 'ref_no' => $ref,
        'amount_sen' => 5000, 'currency' => 'MYR', 'status' => GatewayPaymentStatus::Pending, 'refunded_sen' => 0,
    ]);
}

function stdAdminAssert($html, array $sorts): void
{
    expect($html)->toContain('<thead')->toContain('aria-sort=')->toContain('type="search"')
        ->toContain('wire:loading')->toContain('wire:key=')->toContain('of ');
    foreach ($sorts as $field) {
        expect($html)->toContain("sortBy('{$field}')");
    }
}

test('buyers is a standard table', function () {
    User::factory()->create(['name' => 'Zed Buyer']);
    User::factory()->create(['name' => 'Amy Buyer']);

    $c = Livewire::test(Buyers::class);
    stdAdminAssert($c->html(), ['name', 'email', 'orders', 'joined', 'status']);
    $c->call('sortBy', 'name')->assertSeeInOrder(['Amy Buyer', 'Zed Buyer'])
        ->call('sortBy', 'name')->assertSeeInOrder(['Zed Buyer', 'Amy Buyer'])
        ->call('sortBy', 'password; drop')->assertSet('sort', 'name')
        ->set('search', 'amy')->assertSee('Amy Buyer')->assertDontSee('Zed Buyer')
        ->set('search', '%')->assertDontSee('Amy Buyer');
});

test('stores is a standard table', function () {
    Store::factory()->approved()->create(['name' => 'Alpha Store']);
    Store::factory()->approved()->create(['name' => 'Zulu Store']);

    $c = Livewire::test(Stores::class);
    stdAdminAssert($c->html(), ['store', 'state', 'status', 'rating', 'products', 'commission']);
    $c->call('sortBy', 'store')->call('sortBy', 'store')->assertSeeInOrder(['Zulu Store', 'Alpha Store'])
        ->call('sortBy', 'nope')->assertSet('sort', 'store')
        ->set('search', 'alpha')->assertSee('Alpha Store')->assertDontSee('Zulu Store');
});

test('reviews is a standard table sortable by rating and posted', function () {
    $low = stdReview(1);
    $high = stdReview(5);

    $c = Livewire::test(Reviews::class);
    stdAdminAssert($c->html(), ['rating', 'posted']);
    $c->call('sortBy', 'rating')->assertSet('direction', 'asc')
        ->call('sortBy', 'rating')->assertSet('direction', 'desc')
        ->call('sortBy', 'comment')->assertSet('sort', 'rating')
        ->set('search', $high->store->name)->assertSee($high->product->getTranslation('name', 'en'))
        ->set('rating', '1')->assertSet('rating', '1');
    expect($low->id)->not->toBe($high->id);
});

test('audit log has search, footer and a plain order label', function () {
    $subOrder = SubOrder::factory()->create();
    activity()->causedBy($this->admin)->performedOn($subOrder)->event('created')->log('created');
    activity()->causedBy($this->admin)->event('updated')->log('review.hidden');

    $c = Livewire::test(AuditLog::class);
    stdAdminAssert($c->html(), ['when']);
    $c->assertSee($subOrder->sub_order_no)->assertDontSeeHtml('SubOrder <span')
        ->assertSee('review.hidden')
        ->set('search', 'review.hidden')->assertSee('review.hidden')->assertDontSee($subOrder->sub_order_no)
        ->call('sortBy', 'bogus')->assertSet('sort', '');
});

test('audit log drops a description that only repeats the event', function () {
    $subOrder = SubOrder::factory()->create();
    activity()->performedOn($subOrder)->event('created')->log('created');

    expect(Livewire::test(AuditLog::class)->html())->not->toContain('>Description<');
});

test('payments search by order no and ref, sort, footer', function () {
    $mine = stdPayment('REFAAA111');
    $other = stdPayment('REFBBB222');

    $c = Livewire::test(Payments::class);
    stdAdminAssert($c->html(), ['ref', 'amount', 'status', 'paid']);
    $c->set('search', 'AAA111')->assertSee('REFAAA111')->assertDontSee('REFBBB222')
        ->set('search', $other->order->order_no)->assertSee('REFBBB222')->assertDontSee('REFAAA111')
        ->set('search', '')->call('sortBy', 'ref')->assertSeeInOrder(['REFAAA111', 'REFBBB222'])
        ->call('sortBy', 'x')->assertSet('sort', 'ref');
    expect($mine->status)->toBeInstanceOf(GatewayPaymentStatus::class);
});

test('moderation hides its footer when empty and the banned empty state is a component', function () {
    $html = Livewire::test(Moderation::class)->html();
    expect($html)->not->toContain('Showing 0 to 0')->toContain('No banned products')->not->toContain('px-4 py-6 text-[13px] text-ink-faint');

    Product::factory()->create(['status' => ProductStatus::PendingReview]);
    expect(Livewire::test(Moderation::class)->html())->toContain('Showing 1 to 1 of 1');
});

test('notifications empty state sits in a card', function () {
    $html = Livewire::test(Notifications::class)->html();
    expect($html)->toContain('No notifications')->toMatch('/shadow-card.*No notifications/s');
});
