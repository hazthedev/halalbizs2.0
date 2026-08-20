<?php

use App\Enums\ProductStatus;
use App\Livewire\Seller\Products\Form;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\MarketplaceLinkResolver;
use App\Settings\GeneralSettings;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
});

function marketplaceSeller(): User
{
    $user = User::factory()->create();
    $user->assignRole('seller');
    Store::factory()->approved()->create(['user_id' => $user->id]);

    return $user;
}

function marketplaceProduct(User $seller): Product
{
    $product = Product::factory()->create([
        'store_id' => $seller->store->id,
        'status' => ProductStatus::Live,
    ]);
    $product->variants->first()->update(['price_sen' => 10_000, 'sale_price_sen' => null, 'stock' => 5]);

    return $product->fresh('variants');
}

function setPurchasing(bool $enabled): void
{
    $settings = app(GeneralSettings::class);
    $settings->purchasing_enabled = $enabled;
    $settings->save();
}

// ── The trust boundary ──────────────────────────────────────────────────
// Every one of these is a URL a seller could paste and a shopper could click.

dataset('rejected urls', [
    'plain http (no TLS)' => 'http://shopee.com.my/product/1',
    'javascript scheme' => 'javascript:alert(1)',
    'data scheme' => 'data:text/html,<script>alert(1)</script>',
    'protocol-relative' => '//shopee.com.my/product/1',
    // Reads as Shopee to a human; the real host is evil.com.
    'userinfo host spoof' => 'https://shopee.com.my@evil.com/product/1',
    'userinfo with password' => 'https://shopee.com.my:tok@evil.com/x',
    // Host here really IS allow-listed, so this is the case that pins the
    // userinfo rule — the allow-list cannot reject it.
    'credentials in front of a real allow-listed host' => 'https://evil.com@shopee.com.my/x',
    // The str_contains trap: allow-listed string present, wrong host.
    'allow-listed host in the query' => 'https://evil.com/?next=https://shopee.com.my/p/1',
    'allow-listed host in the fragment' => 'https://evil.com/x#shopee.com.my',
    'allow-listed host in the path' => 'https://evil.com/shopee.com.my/p/1',
    // Suffix match without a dot boundary.
    'lookalike suffix domain' => 'https://notshopee.com.my/product/1',
    'lookalike prefix domain' => 'https://shopee.com.my.evil.com/product/1',
    'unknown marketplace' => 'https://www.amazon.com/dp/B000',
    'newline injection' => "https://shopee.com.my/p/1\nX-Evil: 1",
    'not a url at all' => 'shopee',
    'empty' => '',
]);

it('refuses every url that is not provably on an allow-listed host', function (string $url) {
    expect(app(MarketplaceLinkResolver::class)->resolve($url))->toBeNull();
})->with('rejected urls');

// Since 2026-08-20 the allow-list decides VERIFIED, not SAVEABLE. So the two
// questions split, and the dataset above splits with them: a hostile shape must
// still be refused outright, while an ordinary unknown host is now fine.
dataset('unsafe shapes', [
    'plain http (no TLS)' => 'http://example.com/p/1',
    'javascript scheme' => 'javascript:alert(1)',
    'data scheme' => 'data:text/html,<script>alert(1)</script>',
    'protocol-relative' => '//example.com/p/1',
    'userinfo host spoof' => 'https://shopee.com.my@evil.com/product/1',
    'userinfo with password' => 'https://shopee.com.my:tok@evil.com/x',
    'newline injection' => "https://example.com/p/1\nX-Evil: 1",
    'not a url at all' => 'shopee',
    'empty' => '',
]);

it('refuses a url we would never hand to a browser, whatever the host', function (string $url) {
    expect(app(MarketplaceLinkResolver::class)->isSafe($url))->toBeFalse();
})->with('unsafe shapes');

it('accepts an ordinary https link on a host we do not allow-list', function () {
    $resolver = app(MarketplaceLinkResolver::class);

    // CONTROL: this is exactly the URL the old rule rejected, and the thing that
    // changed is which question we ask about it.
    expect($resolver->isSafe('https://www.amazon.com/dp/B000'))->toBeTrue()
        ->and($resolver->resolve('https://www.amazon.com/dp/B000'))->toBeNull();
});

dataset('accepted urls', [
    'bare allow-listed host' => ['https://shopee.com.my/product/123', 'shopee'],
    'subdomain on a dot boundary' => ['https://my.shopee.com.my/product/123', 'shopee'],
    'trailing dot host' => ['https://shopee.com.my./product/123', 'shopee'],
    'uppercase host' => ['https://SHOPEE.COM.MY/product/123', 'shopee'],
    // parse_url does NOT normalise the scheme, so this only passes because
    // resolve() case-folds it. A seller pasting an uppercase URL is not an attack.
    'uppercase scheme' => ['HTTPS://shopee.com.my/product/123', 'shopee'],
    'another platform' => ['https://www.lazada.com.my/products/x-i123.html', 'lazada'],
    'platform that lives on a subdomain' => ['https://shop.tiktok.com/view/product/123', 'tiktok'],
]);

it('accepts allow-listed marketplace urls and derives the platform from the host', function (string $url, string $platform) {
    $resolved = app(MarketplaceLinkResolver::class)->resolve($url);

    expect($resolved)->not->toBeNull()
        ->and($resolved['platform'])->toBe($platform);
})->with('accepted urls');

it('enforces the link cap on the server, not just by hiding the add button', function () {
    $seller = marketplaceSeller();
    $product = marketplaceProduct($seller);

    // A wire:model array is client-writable, so the count guard in
    // addMarketplaceLink() proves nothing on its own.
    Livewire::actingAs($seller)
        ->test(Form::class, ['product' => $product])
        ->set('marketplaceLinks', array_fill(0, Form::MAX_MARKETPLACE_LINKS + 2, ['url' => 'https://shopee.com.my/p/1', 'title' => 'Shopee']))
        ->call('saveDraft')
        ->assertHasErrors('marketplaceLinks');

    expect($product->fresh('marketplaceLinks')->marketplaceLinks)->toBeEmpty();
});

it('refuses a url longer than the column allows', function () {
    $url = 'https://shopee.com.my/'.str_repeat('a', MarketplaceLinkResolver::MAX_LENGTH);

    expect(app(MarketplaceLinkResolver::class)->resolve($url))->toBeNull();
});

// ── Seller form ─────────────────────────────────────────────────────────

it('lets a seller attach a link and stores the title with the derived platform', function () {
    $seller = marketplaceSeller();
    $product = marketplaceProduct($seller);

    Livewire::actingAs($seller)
        ->test(Form::class, ['product' => $product])
        ->call('addMarketplaceLink')
        ->set('marketplaceLinks.0.url', 'https://shopee.com.my/product/123')
        ->set('marketplaceLinks.0.title', 'Our Shopee store')
        ->call('saveDraft')
        ->assertHasNoErrors();

    $link = $product->fresh('marketplaceLinks')->marketplaceLinks->sole();

    expect($link->platform)->toBe('shopee')
        ->and($link->title)->toBe('Our Shopee store')
        ->and($link->url)->toBe('https://shopee.com.my/product/123')
        ->and($link->isVerified())->toBeTrue();
});

it('stores a link on an unknown host with no platform, which is what unverified means', function () {
    $seller = marketplaceSeller();
    $product = marketplaceProduct($seller);

    Livewire::actingAs($seller)
        ->test(Form::class, ['product' => $product])
        ->call('addMarketplaceLink')
        ->set('marketplaceLinks.0.url', 'https://mystore.example/product/9')
        ->set('marketplaceLinks.0.title', 'Our own website')
        ->call('saveDraft')
        ->assertHasNoErrors();

    $link = $product->fresh('marketplaceLinks')->marketplaceLinks->sole();

    expect($link->platform)->toBeNull()
        ->and($link->isVerified())->toBeFalse()
        ->and($link->title)->toBe('Our own website');
});

it('refuses to save a link whose url we would never hand to a browser', function () {
    $seller = marketplaceSeller();
    $product = marketplaceProduct($seller);

    Livewire::actingAs($seller)
        ->test(Form::class, ['product' => $product])
        ->call('addMarketplaceLink')
        ->set('marketplaceLinks.0.url', 'https://shopee.com.my@evil.com/product/1')
        ->set('marketplaceLinks.0.title', 'Shopee')
        ->call('saveDraft')
        ->assertHasErrors('marketplaceLinks.0.url');

    expect($product->fresh('marketplaceLinks')->marketplaceLinks)->toBeEmpty();
});

it('refuses a link with no title, because the title is all the shopper reads', function () {
    $seller = marketplaceSeller();
    $product = marketplaceProduct($seller);

    Livewire::actingAs($seller)
        ->test(Form::class, ['product' => $product])
        ->call('addMarketplaceLink')
        ->set('marketplaceLinks.0.url', 'https://shopee.com.my/product/1')
        ->call('saveDraft')
        ->assertHasErrors('marketplaceLinks.0.title');

    expect($product->fresh('marketplaceLinks')->marketplaceLinks)->toBeEmpty();
});

it('allows two links to the same marketplace', function () {
    $seller = marketplaceSeller();
    $product = marketplaceProduct($seller);

    Livewire::actingAs($seller)
        ->test(Form::class, ['product' => $product])
        ->call('addMarketplaceLink')
        ->set('marketplaceLinks.0.url', 'https://shopee.com.my/product/1')
        ->set('marketplaceLinks.0.title', 'Shopee — 500g pack')
        ->call('addMarketplaceLink')
        ->set('marketplaceLinks.1.url', 'https://shopee.com.my/product/2')
        ->set('marketplaceLinks.1.title', 'Shopee — 1kg pack')
        ->call('saveDraft')
        ->assertHasNoErrors();

    expect($product->fresh('marketplaceLinks')->marketplaceLinks)->toHaveCount(2);
});

it('refuses the same url twice, however it is capitalised', function () {
    $seller = marketplaceSeller();
    $product = marketplaceProduct($seller);

    Livewire::actingAs($seller)
        ->test(Form::class, ['product' => $product])
        ->call('addMarketplaceLink')
        ->set('marketplaceLinks.0.url', 'https://shopee.com.my/product/1')
        ->set('marketplaceLinks.0.title', 'Shopee')
        ->call('addMarketplaceLink')
        ->set('marketplaceLinks.1.url', 'HTTPS://Shopee.com.my/product/1')
        ->set('marketplaceLinks.1.title', 'Shopee again')
        ->call('saveDraft')
        ->assertHasErrors('marketplaceLinks.1.url');

    expect($product->fresh('marketplaceLinks')->marketplaceLinks)->toBeEmpty();
});

it('drops a link the seller removed', function () {
    $seller = marketplaceSeller();
    $product = marketplaceProduct($seller);
    $product->marketplaceLinks()->create(['platform' => 'shopee', 'title' => 'Shopee', 'url' => 'https://shopee.com.my/p/1', 'position' => 0]);

    Livewire::actingAs($seller)
        ->test(Form::class, ['product' => $product])
        ->assertSet('marketplaceLinks.0.url', 'https://shopee.com.my/p/1')
        ->assertSet('marketplaceLinks.0.title', 'Shopee')
        ->call('removeMarketplaceLink', 0)
        ->call('saveDraft')
        ->assertHasNoErrors();

    expect($product->fresh('marketplaceLinks')->marketplaceLinks)->toBeEmpty();
});

// ── Shopper display ─────────────────────────────────────────────────────

it('shows the outbound link on the product page while the marketplace is listing-only', function () {
    $seller = marketplaceSeller();
    $product = marketplaceProduct($seller);
    $product->marketplaceLinks()->create(['platform' => 'shopee', 'title' => 'Our Shopee store', 'url' => 'https://shopee.com.my/p/1', 'position' => 0]);
    setPurchasing(false);

    $this->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertSee('Also available in')
        ->assertSee('Our Shopee store')
        ->assertSee('https://shopee.com.my/p/1', false);
});

it('puts every link behind ONE control, listing the titles', function () {
    $seller = marketplaceSeller();
    $product = marketplaceProduct($seller);
    $product->marketplaceLinks()->createMany([
        ['platform' => 'shopee', 'title' => 'Shopee — 500g', 'url' => 'https://shopee.com.my/p/1', 'position' => 0],
        ['platform' => null, 'title' => 'Our own website', 'url' => 'https://mystore.example/p/9', 'position' => 1],
    ]);
    setPurchasing(false);

    $html = $this->get(route('product.show', $product->slug))->assertOk()->getContent();

    // One dropdown, not one button per link — that is the whole point of the
    // change, and a flat row of buttons would satisfy every other assertion here.
    expect(substr_count($html, 'data-testid="pdp-marketplace-links"'))->toBe(1)
        ->and(substr_count($html, '<details'))->toBe(1)
        ->and($html)->toContain('Shopee — 500g')
        ->and($html)->toContain('Our own website');
});

it('shows an unverified link exactly like a verified one', function () {
    $seller = marketplaceSeller();
    $product = marketplaceProduct($seller);
    $product->marketplaceLinks()->create(['platform' => null, 'title' => 'Our own website', 'url' => 'https://mystore.example/p/9', 'position' => 0]);
    setPurchasing(false);

    // Haze, 2026-08-20: no unverified mark for shoppers. The flag is seller- and
    // admin-facing only, so the storefront must not leak it in any spelling.
    $this->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertSee('Our own website')
        ->assertSee('https://mystore.example/p/9', false)
        ->assertDontSee('nverified')
        ->assertDontSee('ot checked');
});

it('carries the outbound rel and target so the destination cannot reach back', function () {
    $seller = marketplaceSeller();
    $product = marketplaceProduct($seller);
    $product->marketplaceLinks()->create(['platform' => 'shopee', 'title' => 'Shopee', 'url' => 'https://shopee.com.my/p/1', 'position' => 0]);
    setPurchasing(false);

    $this->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertSee('rel="noopener noreferrer nofollow ugc"', false)
        ->assertSee('target="_blank"', false);
});

it('hides the link once our own checkout is back on, unless the seller opted in', function () {
    $seller = marketplaceSeller();
    $product = marketplaceProduct($seller);
    $product->marketplaceLinks()->create(['platform' => 'shopee', 'title' => 'Our Shopee store', 'url' => 'https://shopee.com.my/p/1', 'position' => 0]);
    setPurchasing(true);

    // CONTROL: the link exists and the page renders — it is the visibility rule
    // that hides it, not an empty relation.
    $this->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertDontSee('Our Shopee store');

    $product->update(['marketplace_links_always_visible' => true]);

    $this->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertSee('Our Shopee store');
});

it('shows nothing at all when a product has no links', function () {
    $seller = marketplaceSeller();
    $product = marketplaceProduct($seller);
    setPurchasing(false);

    $this->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertSee('Listing only')          // the page is in the state under test
        ->assertDontSee('Also available in');
});

it('translates the shopper-facing copy into Malay and Vietnamese', function () {
    // The titles themselves are seller-written and are never translated — only
    // the control that opens them is.
    app()->setLocale('ms');
    expect(__('Also available in'))->toBe('Turut tersedia di');

    app()->setLocale('vi');
    expect(__('Also available in'))->toBe('Cũng có trên');
});

// ── Ownership ───────────────────────────────────────────────────────────

it('does not let a seller attach a link to someone else\'s product', function () {
    $owner = marketplaceSeller();
    $intruder = marketplaceSeller();
    $product = marketplaceProduct($owner);
    $product->marketplaceLinks()->create(['platform' => 'shopee', 'title' => 'Shopee', 'url' => 'https://shopee.com.my/p/1', 'position' => 0]);

    // CONTROL: the owner can open the form and sees their own link.
    Livewire::actingAs($owner)
        ->test(Form::class, ['product' => $product])
        ->assertOk()
        ->assertSet('marketplaceLinks.0.url', 'https://shopee.com.my/p/1');

    Livewire::actingAs($intruder)
        ->test(Form::class, ['product' => $product])
        ->assertForbidden();
});
