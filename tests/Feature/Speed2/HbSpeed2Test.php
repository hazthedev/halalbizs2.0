<?php

use App\Enums\PayoutStatus;
use App\Enums\SubOrderStatus;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\Finance\Payouts;
use App\Livewire\NotificationBell;
use App\Livewire\Seller\Dashboard as SellerDashboard;
use App\Livewire\Seller\Messages as SellerMessages;
use App\Livewire\Seller\Products\Form as ProductForm;
use App\Livewire\Storefront\Account\Dashboard as BuyerDashboard;
use App\Livewire\Storefront\Account\Messages as BuyerMessages;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\Payout;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use App\Support\CatalogOptions;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
 * HB-SPEED-2: seller/admin screens stop doing hidden work. Each test pairs a
 * query-count ceiling (the BEFORE number, measured on origin/main 8c2a468, is
 * in the comment) with a same-numbers check.
 */

beforeEach(fn () => $this->seed(RoleSeeder::class));

function speed2Queries(callable $run): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $run();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

function speed2Chat(int $messages = 3): array
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $owner = User::factory()->create();
    $owner->assignRole('seller');
    $store = Store::factory()->approved()->create(['user_id' => $owner->id]);
    $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $store->id]);

    for ($i = 0; $i < $messages; $i++) {
        speed2Say($conversation, $i % 2 ? $buyer : $owner, $i % 2 ? 'buyer' : 'seller', sprintf('msg-%03d', $i));
    }

    return [$buyer, $owner, $conversation];
}

function speed2Say(Conversation $conversation, User $sender, string $side, string $body): Message
{
    return Message::create(['conversation_id' => $conversation->id, 'sender_type' => $side, 'sender_id' => $sender->id, 'body' => $body]);
}

// ── Polls ───────────────────────────────────────────────────────────

it('polls only while the tab is visible', function () {
    [$buyer, $owner, $conversation] = speed2Chat();

    expect(Livewire::actingAs($owner)->test(NotificationBell::class)->html())->toContain('wire:poll.60s.visible="tick"')
        ->and(Livewire::actingAs($owner)->test(SellerMessages::class)->call('openConversation', $conversation->id)->html())->toContain('wire:poll.5s.visible="refreshThread"')
        ->and(Livewire::actingAs($buyer)->test(BuyerMessages::class)->call('openConversation', $conversation->id)->html())->toContain('wire:poll.5s.visible="refreshThread"');
});

it('bell tick is one query and skips the render until something changes', function () {
    $user = User::factory()->create();
    $bell = Livewire::actingAs($user)->test(NotificationBell::class);

    // before: 2 queries and a full render on every tick
    expect(speed2Queries(fn () => $bell->call('tick')))->toBe(1);
    $bell->assertDontSeeHtml('data-testid="bell-unread-dot"');

    $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'x', 'data' => ['message' => 'Parcel shipped']]);
    $bell->call('tick')->assertSeeHtml('data-testid="bell-unread-dot"')->assertSee('Parcel shipped');

    // reading it changes the fingerprint too
    $user->unreadNotifications()->update(['read_at' => now()]);
    $bell->call('tick')->assertDontSeeHtml('data-testid="bell-unread-dot"');
});

it('thread tick is one query when idle and brings in only new messages', function () {
    [$buyer, $owner, $conversation] = speed2Chat();

    foreach ([[SellerMessages::class, $owner, 'buyer'], [BuyerMessages::class, $buyer, 'seller']] as [$class, $user, $theirSide]) {
        $thread = Livewire::actingAs($user)->test($class)->call('openConversation', $conversation->id);

        // before: seller 10 queries, buyer 13 per tick
        expect(speed2Queries(fn () => $thread->call('refreshThread')))->toBe(1);

        $sender = $theirSide === 'buyer' ? $buyer : $owner;
        $thread->assertDontSee("new-for-{$class}");
        speed2Say($conversation, $sender, $theirSide, "new-for-{$class}");
        $thread->call('refreshThread')->assertSee("new-for-{$class}");
    }
});

it('a new message from the other side is marked read when the tick picks it up', function () {
    [$buyer, $owner, $conversation] = speed2Chat();
    $thread = Livewire::actingAs($owner)->test(SellerMessages::class)->call('openConversation', $conversation->id);

    $incoming = speed2Say($conversation, $buyer, 'buyer', 'hello seller');
    $thread->call('refreshThread');

    expect($incoming->fresh()->read_at)->not->toBeNull();
});

it('a thread opens on its latest messages only', function () {
    [$buyer, $owner, $conversation] = speed2Chat(Conversation::THREAD_WINDOW + 20);

    $html = Livewire::actingAs($owner)->test(SellerMessages::class)->call('openConversation', $conversation->id)->html();

    expect($html)->toContain(sprintf('msg-%03d', Conversation::THREAD_WINDOW + 19))
        ->toContain(sprintf('msg-%03d', 20))
        ->not->toContain('msg-019');
});

// ── Dashboards: same numbers, fewer queries ─────────────────────────

/** A sub-order for the store created at $createdAt (UTC). */
function speed2SubOrder(Store $store, SubOrderStatus $status, int $totalSen, Carbon $createdAt): SubOrder
{
    $subOrder = SubOrder::factory()->status($status)->create(['store_id' => $store->id, 'total_sen' => $totalSen]);
    $subOrder->forceFill(['created_at' => $createdAt])->saveQuietly();

    return $subOrder;
}

it('seller dashboard: charts built once per request, same revenue and status numbers', function () {
    $owner = User::factory()->create();
    $owner->assignRole('seller');
    $store = Store::factory()->approved()->create(['user_id' => $owner->id]);

    // 17:30 UTC is 01:30 the NEXT Malaysia day: the day bucket must follow display time.
    $lateUtc = now()->subDays(2)->setTime(17, 30);
    speed2SubOrder($store, SubOrderStatus::Completed, 25000, $lateUtc);
    speed2SubOrder($store, SubOrderStatus::Confirmed, 18000, now()->subDay()->setTime(3, 0));
    speed2SubOrder($store, SubOrderStatus::Cancelled, 9900, now()->subDay());
    speed2SubOrder($store, SubOrderStatus::PendingPayment, 7700, now()->subHours(3));

    $component = Livewire::actingAs($owner)->test(SellerDashboard::class);

    // Oracle: the old algorithm (load rows, bucket in PHP by display date).
    $days = collect(range(29, 0))->map(fn (int $back) => App\Support\DisplayTime::now()->subDays($back)->toDateString());
    $perDay = $store->subOrders()
        ->whereIn('status', App\Services\SellerDashboardService::REVENUE_STATUSES)
        ->get(['created_at', 'total_sen'])
        ->groupBy(fn (SubOrder $row) => $row->created_at->display()->toDateString())
        ->map(fn ($rows) => (int) $rows->sum('total_sen'));
    $expected = $days->map(fn (string $day) => intdiv((int) ($perDay[$day] ?? 0), 100))->all();

    expect($component->instance()->revenueData())->toBe($expected)
        ->and(array_sum($expected))->toBe(430)
        ->and($component->instance()->statusCounts())->toHaveCount(4);

    // before (same data): 18 queries on a period click; 16 with an empty store
    expect(speed2Queries(fn () => $component->set('period', '7d')))->toBeLessThanOrEqual(13);
    expect(array_sum($component->instance()->revenueData()))->toBe(430);
    // before (same data): 24 on mount
    expect(speed2Queries(fn () => Livewire::actingAs($owner)->test(SellerDashboard::class)))->toBeLessThanOrEqual(19);
});

it('admin dashboard: same GMV, buyers and status numbers, fewer queries', function () {
    $this->withoutExceptionHandling();
    $admin = User::factory()->create(['two_factor_method' => 'email']);
    makeAdmin($admin);

    Order::factory()->paid()->create(['grand_total_sen' => 12345, 'paid_at' => now()->subDays(2)->setTime(17, 30)]);
    Order::factory()->paid()->create(['grand_total_sen' => 5000, 'paid_at' => now()->subDay()]);
    User::factory()->count(2)->create(['created_at' => now()->subDay()->setTime(17, 45)]);

    $component = Livewire::actingAs($admin)->test(AdminDashboard::class);

    $days = collect(range(29, 0))->map(fn (int $back) => App\Support\DisplayTime::now()->subDays($back)->toDateString());
    $paid = Order::query()->where('payment_status', App\Enums\PaymentStatus::Paid)->get(['paid_at', 'grand_total_sen'])
        ->groupBy(fn (Order $o) => $o->paid_at->display()->toDateString())->map(fn ($o) => (int) $o->sum('grand_total_sen'));
    $joined = User::query()->where('created_at', '>=', now()->subDays(40))->get(['created_at'])
        ->groupBy(fn (User $u) => $u->created_at->display()->toDateString())->map->count();

    $gmv = (fn () => $this->gmvChartPayload())->call($component->instance());
    $buyers = (fn () => $this->buyersChartPayload())->call($component->instance());

    expect($gmv['series'][0]['data'])->toBe($days->map(fn ($d) => intdiv((int) ($paid[$d] ?? 0), 100))->all())
        ->and(array_sum($gmv['series'][0]['data']))->toBe(173)
        ->and($buyers['series'][0]['data'])->toBe($days->map(fn ($d) => (int) ($joined[$d] ?? 0))->all());

    // before: 26 queries on mount and on every period click
    expect(speed2Queries(fn () => $component->call('setPeriod', '7d')))->toBeLessThanOrEqual(13);
});

it('admin dashboard: all-time blocks are reused between renders', function () {
    $admin = User::factory()->create(['two_factor_method' => 'email']);
    makeAdmin($admin);

    $component = Livewire::actingAs($admin)->test(AdminDashboard::class);
    $first = speed2Queries(fn () => $component->call('setPeriod', '7d'));
    $second = speed2Queries(fn () => $component->call('setPeriod', 'today'));

    expect($second)->toBeLessThanOrEqual($first)->and($second)->toBeLessThanOrEqual(13);
});

it('buyer dashboard: same totals, fewer queries', function () {
    $buyer = User::factory()->create();
    $own = Order::factory()->paid()->create(['user_id' => $buyer->id, 'grand_total_sen' => 20000, 'paid_at' => now()->startOfMonth()->addDays(1)]);
    // Paid at 16:30 UTC on the last day of last month = 00:30 on the 1st, Malaysia time: this month's bucket.
    Order::factory()->paid()->create(['user_id' => $buyer->id, 'grand_total_sen' => 4000, 'paid_at' => now()->startOfMonth()->subMinutes(90)]);
    Order::factory()->create(['user_id' => $buyer->id]);
    SubOrder::factory()->status(SubOrderStatus::Shipped)->create(['order_id' => $own->id]);

    $component = Livewire::actingAs($buyer)->test(BuyerDashboard::class);

    $old = Order::query()->where('user_id', $buyer->id)->where('payment_status', App\Enums\PaymentStatus::Paid)->whereNotNull('paid_at')->get(['paid_at', 'grand_total_sen'])
        ->groupBy(fn (Order $o) => $o->paid_at->display()->format('Y-m'))->map(fn ($o) => (int) $o->sum('grand_total_sen'));
    $series = (fn () => $this->spendChart())->call($component->instance())['series'][0]['data'];
    $expected = collect(range(5, 0))->map(fn ($back) => round(((int) ($old[App\Support\DisplayTime::now()->startOfMonth()->subMonths($back)->format('Y-m')] ?? 0)) / 100, 2))->all();

    expect($series)->toEqual($expected);
    $component->assertSet('period', '6m');
    expect(Livewire::actingAs($buyer)->test(BuyerDashboard::class)->viewData('totalSpentSen'))->toBe(24000)
        ->and(Livewire::actingAs($buyer)->test(BuyerDashboard::class)->viewData('ordersPlaced'))->toBe(3)
        ->and(Livewire::actingAs($buyer)->test(BuyerDashboard::class)->viewData('quickStatus')[2]['count'])->toBe(1);

    // before: 11 queries on a period click, 10 on mount
    expect(speed2Queries(fn () => $component->set('period', '3m')))->toBeLessThanOrEqual(9);
});

// ── Product form ────────────────────────────────────────────────────

it('product form: option lists come from plain cached arrays and a trip is cheaper', function () {
    $owner = User::factory()->create();
    $owner->assignRole('seller');
    Store::factory()->approved()->create(['user_id' => $owner->id]);
    $top = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $top->id]);
    Brand::factory()->count(3)->create();

    $form = Livewire::actingAs($owner)->test(ProductForm::class)
        ->set('categoryTop', $top->id)->set('hasVariations', true)
        ->set('optionGroups.0.name', 'Size')->set('optionGroups.0.draft', 'S');

    // before: 5 queries per option add
    expect(speed2Queries(fn () => $form->call('addOptionValue', 0)))->toBeLessThanOrEqual(2);
    $form->assertSee($child->getTranslation('name', 'en'));

    expect(CatalogOptions::categories()[0])->toHaveKeys(['id', 'parent_id', 'name'])
        ->and(CatalogOptions::brands())->toHaveCount(3);

    // an admin save flushes the list
    $child->update(['name' => ['en' => 'Renamed leaf']]);
    expect(collect(CatalogOptions::categories())->firstWhere('id', $child->id)['name'])->toBe('Renamed leaf');
    Brand::factory()->create();
    expect(CatalogOptions::brands())->toHaveCount(4);
});

it('product form: condition and attribute picks are browser side', function () {
    $owner = User::factory()->create();
    $owner->assignRole('seller');
    Store::factory()->approved()->create(['user_id' => $owner->id]);

    $html = Livewire::actingAs($owner)->test(ProductForm::class)->html();

    expect($html)->not->toContain('wire:model.live="condition"')
        ->and($html)->toContain("\$wire.entangle('condition')");
});

it('product form: save payload is unchanged when condition and attributes are set from the browser', function () {
    $owner = User::factory()->create();
    $owner->assignRole('seller');
    Store::factory()->approved()->create(['user_id' => $owner->id]);
    $category = Category::factory()->create();

    Livewire::actingAs($owner)->test(ProductForm::class)
        ->set('name.en', 'Speed2 mug')
        ->set('categoryTop', $category->id)
        ->set('condition', 'used')
        ->set('price', '12.50')
        ->set('stock', '4')
        ->call('saveDraft')
        ->assertHasNoErrors();

    $product = App\Models\Product::query()->where('store_id', $owner->store->id)->latest('id')->first();

    expect($product->condition->value)->toBe('used')
        ->and($product->variants()->first()->price_sen)->toBe(1250)
        ->and($product->variants()->first()->stock)->toBe(4);
});

// ── Payouts selection ───────────────────────────────────────────────

it('payout checkboxes select in the browser and the selection still reaches the export', function () {
    $admin = User::factory()->create(['two_factor_method' => 'email']);
    makeAdmin($admin);
    $a = Payout::create(['payout_no' => Payout::generatePayoutNo(), 'store_id' => Store::factory()->approved()->create()->id, 'amount_sen' => 1250, 'status' => PayoutStatus::Approved, 'approved_at' => now(), 'requested_at' => now()->subDay(), 'bank_snapshot' => ['bank_name' => 'Maybank', 'account_name' => 'A', 'account_number' => '111']]);
    $b = Payout::create(['payout_no' => Payout::generatePayoutNo(), 'store_id' => Store::factory()->approved()->create()->id, 'amount_sen' => 999, 'status' => PayoutStatus::Approved, 'approved_at' => now(), 'requested_at' => now()->subDay(), 'bank_snapshot' => ['bank_name' => 'CIMB', 'account_name' => 'B', 'account_number' => '222']]);

    $page = Livewire::actingAs($admin)->test(Payouts::class)->set('tab', PayoutStatus::Approved->value);
    $html = $page->html();

    expect($html)->not->toContain('wire:model.live="selected"')
        ->and($html)->not->toContain('wire:model.live="selectAll"')
        ->and($html)->toContain('x-model="selected"')
        ->and($html)->toContain(e(json_encode([(string) $a->id, (string) $b->id])));

    // The browser sends `selected` with the export call; only that selection is exported.
    $csv = base64_decode((string) data_get(
        $page->set('selected', [(string) $b->id])->call('exportBankCsv')->assertFileDownloaded()->effects,
        'download.content',
    ));

    expect($csv)->toContain($b->payout_no)->and($csv)->not->toContain($a->payout_no);
});
