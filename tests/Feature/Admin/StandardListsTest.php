<?php

use App\Enums\PayoutStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Livewire\Admin\Affiliates\Index as Affiliates;
use App\Livewire\Admin\Catalog\Attributes;
use App\Livewire\Admin\Catalog\Brands;
use App\Livewire\Admin\Coins\Index as Coins;
use App\Livewire\Admin\Content\Vouchers;
use App\Livewire\Admin\Finance\Boosts;
use App\Livewire\Admin\Finance\Payouts;
use App\Livewire\Admin\Live\Index as Live;
use App\Livewire\Admin\Orders\Returns;
use App\Livewire\Admin\Subscriptions\Index as Subscriptions;
use App\Livewire\Admin\Support\Articles;
use App\Livewire\Admin\Support\Tickets;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\HelpArticle;
use App\Models\Payout;
use App\Models\Store;
use App\Models\User;
use App\Models\Voucher;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

// HB-G157: older-style and unpaged admin lists use the standard table.

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['coins.enabled' => true, 'affiliate.enabled' => true, 'subscriptions.enabled' => true, 'live.enabled' => true]);
    $this->admin = User::factory()->create(['two_factor_method' => 'email']);
    makeAdmin($this->admin);
    $this->actingAs($this->admin);
});

function stdListAssert(string $html, bool $sortable = true): void
{
    expect($html)->toContain('<thead')->toContain('wire:key=')->toContain('Showing 1 to')->toContain('type="search"');
    if ($sortable) {
        expect($html)->toContain('aria-sort=')->toContain('wire:click="sortBy(');
    }
    // Old M2 style: uppercase tracked headers.
    expect($html)->not->toContain('uppercase tracking-[0.06em]');
}

function stdTicket(string $subject, array $extra = []): void
{
    User::factory()->create()->tickets()->create($extra + ['subject' => $subject, 'status' => TicketStatus::Open, 'priority' => TicketPriority::Normal]);
}

function stdVoucherRow(string $code): Voucher
{
    return Voucher::create(['scope' => 'platform', 'code' => $code, 'type' => 'fixed', 'value_sen' => 100, 'min_spend_sen' => 0,
        'per_user_limit' => 1, 'starts_at' => now(), 'ends_at' => now()->addWeek(), 'is_active' => true]);
}

test('affiliates roster is a standard table with search and sort whitelist', function () {
    foreach (['Alya Creator', 'Budi Creator'] as $name) {
        app(App\Services\AffiliateService::class)->enroll(User::factory()->create(['name' => $name]));
    }

    $c = Livewire::test(Affiliates::class);
    stdListAssert($c->html());
    $c->assertDontSee('M2.5')
        ->set('search', 'Budi')->assertSee('Budi Creator')->assertDontSee('Alya Creator')
        ->set('search', '%')->assertSee('No affiliates match')
        ->set('search', '')->call('sortBy', 'id; drop table')->assertSet('sort', '');
});

test('coins page has sentence tiles, standard table, search and sort', function () {
    foreach (['Alya', 'Budi'] as $name) {
        app(App\Services\CoinService::class)->credit(User::factory()->create(['name' => $name]), 100, App\Enums\CoinTransactionType::Earn);
    }

    $c = Livewire::test(Coins::class);
    stdListAssert($c->html());
    expect($c->html())->not->toContain('uppercase')->toContain('Coins in circulation')->toContain('font-mono');
    $c->assertDontSee('M2.1')
        ->set('search', 'Budi')->assertSee('Budi')->assertDontSee('Alya')
        ->set('search', '_')->assertSee('No wallets match')
        ->call('sortBy', 'nope')->assertSet('sort', '');
});

test('live sessions list is standard with search', function () {
    $store = Store::factory()->approved()->create();
    foreach (['Spring sale', 'Night market'] as $title) {
        app(App\Services\LiveSessionService::class)->create($store, ['title' => $title]);
    }

    $c = Livewire::test(Live::class);
    stdListAssert($c->html());
    $c->assertDontSee('M2.4')->set('search', 'Night')->assertSee('Night market')->assertDontSee('Spring sale');
});

test('subscriptions list is standard, tabs hide zero counts', function () {
    $c = Livewire::test(Subscriptions::class);
    expect($c->html())->toContain('type="search"')->not->toContain('M2.8')->not->toMatch('/text-emerald">\s*0\s*<\/span>/');

    $buyer = User::factory()->create(['name' => 'Aisyah Buyer']);
    $address = App\Models\Address::factory()->default()->create(['user_id' => $buyer->id]);
    $product = App\Models\Product::factory()->create(['cod_enabled' => true]);
    app(App\Services\SubscriptionService::class)->subscribe($buyer, $product->variants->first(), $address, App\Enums\SubscriptionInterval::Monthly);

    $c = Livewire::test(Subscriptions::class);
    stdListAssert($c->html());
    $c->set('search', 'Aisyah')->assertSee('Aisyah Buyer')->set('search', 'zzz')->assertSee('No subscriptions match');
});

test('tickets are paginated with search, sort, footer and a real Mark normal button', function () {
    foreach (range(1, 25) as $i) {
        stdTicket("Parcel late {$i}");
    }
    stdTicket('Wrong item', ['priority' => TicketPriority::Urgent]);

    $c = Livewire::test(Tickets::class);
    expect($c->html())->toContain('Showing 1 to 20 of 26')->toContain('aria-sort=')->toContain('type="search"')->toContain('Mark normal')->toContain('Mark urgent');
    expect($c->html())->not->toMatch('/text-emerald">\s*0\s*<\/span>/');
    $c->set('search', 'Wrong')->assertSee('Wrong item')->assertDontSee('Parcel late 1')
        ->set('search', '')->call('sortBy', 'subject')->assertSet('sort', 'subject')
        ->call('sortBy', 'x; drop')->assertSet('sort', 'subject')
        ->call('gotoPage', 2)->assertSee('Showing 21 to 26 of 26');
});

test('tickets actions still work', function () {
    stdTicket('Parcel late');
    $ticket = App\Models\SupportTicket::first();

    Livewire::test(Tickets::class)->call('togglePriority', $ticket->id)->call('close', $ticket->id);
    expect($ticket->fresh())->priority->toBe(TicketPriority::Urgent)->status->toBe(TicketStatus::Closed);
});

test('platform vouchers index is paginated with search and sort', function () {
    foreach (range(1, 22) as $i) {
        stdVoucherRow('CODE'.str_pad((string) $i, 2, '0', STR_PAD_LEFT));
    }

    $c = Livewire::test(Vouchers::class);
    stdListAssert($c->html());
    expect($c->html())->toContain('Showing 1 to 20 of 22')->not->toContain('arrive in M8');
    $c->set('search', 'CODE22')->assertSee('CODE22')->assertDontSee('CODE01')
        ->set('search', '')->call('sortBy', 'code')->call('sortBy', 'code')->assertSeeInOrder(['CODE22', 'CODE21']);
});

test('brands and attributes are paginated with search and sort', function () {
    foreach (range(1, 22) as $i) {
        Brand::create(['name' => "Brand {$i}", 'is_active' => true]);
        Attribute::create(['name' => ['en' => "Attr {$i}"], 'is_filterable' => true]);
    }

    $b = Livewire::test(Brands::class);
    stdListAssert($b->html());
    expect($b->html())->toContain('Showing 1 to 20 of 22');
    $b->set('search', 'Brand 22')->assertSee('Brand 22')->assertDontSee('Brand 1<')->call('sortBy', 'zzz')->assertSet('sort', '');

    $a = Livewire::test(Attributes::class);
    stdListAssert($a->html());
    expect($a->html())->toContain('Showing 1 to 20 of 22');
    $a->set('search', 'Attr 22')->assertSee('Attr 22')->set('search', 'nothing here')->assertSee('No attributes match');
});

test('help articles have search, category filter, sort and footer', function () {
    HelpArticle::create(['category' => 'buying', 'title' => ['en' => 'How to buy'], 'body' => ['en' => 'x'], 'position' => 0, 'is_active' => true]);
    HelpArticle::create(['category' => 'selling', 'title' => ['en' => 'How to sell'], 'body' => ['en' => 'x'], 'position' => 0, 'is_active' => true]);

    $c = Livewire::test(Articles::class);
    stdListAssert($c->html());
    expect($c->html())->toContain('articles-category');
    $c->set('category', 'selling')->assertSee('How to sell')->assertDontSee('How to buy')
        ->set('category', '')->set('search', 'buy')->assertSee('How to buy')->assertDontSee('How to sell')
        ->set('search', 'zzz')->assertSee('No articles match');
});

test('payouts show the full store name and the same tab style, no roadmap text', function () {
    $store = Store::factory()->approved()->create(['name' => 'Graham, Gutmann and Sons Trading Company']);
    Payout::create(['payout_no' => Payout::generatePayoutNo(), 'store_id' => $store->id, 'amount_sen' => 1250, 'status' => PayoutStatus::Requested,
        'bank_snapshot' => ['bank_name' => 'Maybank', 'account_name' => 'A', 'account_number' => '1'], 'requested_at' => now()]);

    $html = Livewire::test(Payouts::class)->html();
    expect($html)->toContain('Graham, Gutmann and Sons Trading Company')->not->toContain('line-clamp-1 max-w-44')->not->toContain('M8')
        ->toContain('aria-current=')->toContain('border-b-2');
});

test('returns tabs and boosts tiles use the shared components', function () {
    $html = Livewire::test(Returns::class)->html();
    expect($html)->toContain('aria-current=')->toContain('border-b-2')->not->toMatch('/text-emerald">\s*0\s*<\/span>/');

    $tiles = Livewire::test(Boosts::class)->html();
    expect($tiles)->toContain('font-mono text-[20px]')->not->toContain('font-display text-xl font-medium tabular-nums');
});

test('HB-G168: a forced select-all flag on a list without tick boxes selects nothing and does not error', function () {
    Brand::create(['name' => 'Brand 1', 'is_active' => true]);

    Livewire::test(Brands::class)->set('selectPage', true)->assertSet('selected', []);
});

test('HB-G168: a chosen rows-per-page survives later requests and a reload with ?perPage', function () {
    foreach (range(1, 3) as $i) {
        Brand::create(['name' => "Brand {$i}", 'is_active' => true]);
    }

    // The default is each list's own PER_PAGE, not the trait's 20.
    Livewire::test(\App\Livewire\Admin\Catalog\Reviews::class)->assertSet('perPage', \App\Livewire\Admin\Catalog\Reviews::PER_PAGE);

    // Each set/call below is its own Livewire request (hydrate from the snapshot).
    Livewire::test(Brands::class)
        ->assertSet('perPage', Brands::PER_PAGE)
        ->set('perPage', 50)
        ->call('sortBy', 'name')->assertSet('perPage', 50)
        ->set('search', 'Brand')->assertSet('perPage', 50)
        ->call('gotoPage', 2)->assertSet('perPage', 50)
        ->set('search', '')->assertSet('perPage', 50);

    Livewire::withQueryParams(['perPage' => 50])->test(Brands::class)
        ->assertSet('perPage', 50)
        ->call('sortBy', 'name')->assertSet('perPage', 50);
});
