<?php

use App\Enums\CertificateStatus;
use App\Enums\ProductStatus;
use App\Livewire\Admin\Catalog\HalalWording;
use App\Livewire\Admin\Support\Tickets as AdminTickets;
use App\Livewire\Storefront\Help\Tickets;
use App\Models\HalalCertificate;
use App\Models\Product;
use App\Models\Store;
use App\Models\SupportTicket;
use App\Models\User;
use Database\Seeders\PageSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

// HB-B5D: refund line, report link, halal wording flag, public fields, privacy requests.

function lsAdmin(): User
{
    test()->seed(RoleSeeder::class);

    return makeAdmin(User::factory()->create(['two_factor_method' => 'email']));
}

function lsProduct(array $attrs = []): Product
{
    return Product::factory()->create(array_merge(['status' => ProductStatus::Live], $attrs));
}

test('refund page has the re-delivery line in en, ms and vi', function () {
    $this->seed(PageSeeder::class);

    $this->get('/page/refund-policy')->assertOk()->assertSee('the seller pays the re-delivery', false);
    $this->withSession(['locale' => 'ms'])->get('/page/refund-policy')->assertOk()->assertSee('penjual menanggung kos penghantaran semula');
    $this->withSession(['locale' => 'vi'])->get('/page/refund-policy')->assertOk()->assertSee('người bán chịu phí giao lại');
});

test('product page shows Report this listing to a buyer and a sign-in ask to a guest', function () {
    $product = lsProduct();
    $href = route('help.tickets', ['report' => $product->slug]);

    $this->get(route('product.show', $product))->assertOk()
        ->assertSee(__('Sign in to report this listing'))->assertSee(e($href), false);

    $this->actingAs(User::factory()->create())->get(route('product.show', $product))->assertOk()
        ->assertSee(__('Report this listing'))->assertDontSee(__('Sign in to report this listing'));
});

test('the report link opens the ticket form prefilled and saves the category', function () {
    $product = lsProduct();
    $user = User::factory()->create();

    Livewire::actingAs($user)->withQueryParams(['report' => $product->slug])->test(Tickets::class)
        ->assertSet('showForm', true)
        ->assertSet('category', 'report_listing')
        ->assertSet('body', fn ($b) => str_contains($b, route('product.show', $product)))
        ->call('create')
        ->assertHasNoErrors();

    $ticket = SupportTicket::firstOrFail();
    expect($ticket->category)->toBe('report_listing')
        ->and($ticket->replies()->first()->body)->toContain(route('product.show', $product));
});

test('a guest on the report link is sent to sign in', function () {
    $this->get(route('help.tickets', ['report' => 'x']))->assertRedirect(route('login'));
});

test('privacy request category saves and the admin filters on it', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Tickets::class)
        ->call('startTicket')
        ->set('category', 'privacy')
        ->set('subject', 'Delete my data please')
        ->set('body', 'Please tell me what you hold about me and delete it.')
        ->call('create')
        ->assertHasNoErrors();

    $user->tickets()->create(['subject' => 'Parcel late again', 'status' => 'open', 'priority' => 'normal']);

    Livewire::actingAs(lsAdmin())->test(AdminTickets::class)
        ->assertSee('Parcel late again')->assertSee('Delete my data please')
        ->set('category', 'privacy')
        ->assertSee('Delete my data please')->assertDontSee('Parcel late again');
});

test('a bad category is refused', function () {
    Livewire::actingAs(User::factory()->create())->test(Tickets::class)
        ->call('startTicket')
        ->set('category', 'nope')
        ->set('subject', 'Something wrong here')
        ->set('body', 'Describe the problem, with plenty of detail here.')
        ->call('create')
        ->assertHasErrors('category');
});

test('halal wording without an approved in-force certificate is flagged, covered ones are not', function () {
    $store = Store::factory()->approved()->create();
    $cert = HalalCertificate::create([
        'store_id' => $store->id, 'number' => 'MY-JKM-1111-100', 'issuing_body' => 'JAKIM', 'issuing_body_name' => 'JAKIM',
        'holder_name' => 'H', 'valid_from' => now()->subYear(), 'valid_to' => now()->addYear(),
    ]);
    $cert->forceFill(['status' => CertificateStatus::Approved])->save();

    $flagged = lsProduct(['store_id' => $store->id, 'name' => ['en' => 'Halal Beef Rendang']]);
    $arabic = lsProduct(['store_id' => $store->id, 'name' => ['en' => 'Dates'], 'description' => ['en' => 'منتج حلال']]);
    $covered = lsProduct(['store_id' => $store->id, 'name' => ['en' => 'Halal Chicken'], 'halal_certificate_id' => $cert->id]);
    $plain = lsProduct(['store_id' => $store->id, 'name' => ['en' => 'Plain Rice'], 'description' => ['en' => 'Rice']]);

    $ids = Product::query()->halalWordingUnverified()->pluck('id')->all();

    expect($ids)->toContain($flagged->id, $arabic->id)->not->toContain($covered->id, $plain->id);

    Livewire::actingAs(lsAdmin())->test(HalalWording::class)
        ->assertSee('Halal Beef Rendang')->assertDontSee('Halal Chicken')->assertDontSee('Plain Rice');
});

test('a pending or expired certificate does not clear the flag', function () {
    $store = Store::factory()->approved()->create();
    $pending = HalalCertificate::create([
        'store_id' => $store->id, 'number' => 'MY-JKM-2222-100', 'issuing_body' => 'JAKIM', 'issuing_body_name' => 'JAKIM',
        'holder_name' => 'H', 'valid_from' => now()->subYear(), 'valid_to' => now()->addYear(),
    ]);
    $product = lsProduct(['store_id' => $store->id, 'name' => ['en' => 'Halal Soup']]);
    $product->forceFill(['halal_certificate_id' => $pending->id])->save();

    expect(Product::query()->halalWordingUnverified()->pluck('id')->all())->toContain($product->id);
});

test('public APIs and pages expose no seller personal fields', function () {
    $store = Store::factory()->approved()->create([
        'business_name' => 'Secret Biz Sdn', 'contact_phone' => '0123456789', 'contact_email' => 'secret@seller.test',
        'business_address' => '9 Hidden Lane', 'state' => 'Perak',
    ]);
    $product = lsProduct(['store_id' => $store->id]);

    foreach (["/api/v1/stores/{$store->slug}", "/api/v1/products/{$product->slug}", "/api/v1/stores/{$store->slug}/products", '/s/'.$store->slug, '/p/'.$product->slug] as $url) {
        $this->get($url, ['Accept' => 'application/json'])->assertOk()
            ->assertDontSee('Secret Biz')->assertDontSee('0123456789')->assertDontSee('secret@seller.test')->assertDontSee('Hidden Lane');
    }
});
