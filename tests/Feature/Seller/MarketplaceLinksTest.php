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
        ->set('marketplaceLinks', array_fill(0, Form::MAX_MARKETPLACE_LINKS + 2, ['url' => 'https://shopee.com.my/p/1']))
        ->call('saveDraft')
        ->assertHasErrors('marketplaceLinks');

    expect($product->fresh('marketplaceLinks')->marketplaceLinks)->toBeEmpty();
});

it('refuses a url longer than the column allows', function () {
    $url = 'https://shopee.com.my/'.str_repeat('a', MarketplaceLinkResolver::MAX_LENGTH);

    expect(app(MarketplaceLinkResolver::class)->resolve($url))->toBeNull();
});

// ── Seller form ─────────────────────────────────────────────────────────

it('lets a seller attach a marketplace link and stores the derived platform', function () {
    $seller = marketplaceSeller();
    $product = marketplaceProduct($seller);

    Livewire::actingAs($seller)
        ->test(Form::class, ['product' => $product])
        ->call('addMarketplaceLink')
        ->set('marketplaceLinks.0.url', 'https://shopee.com.my/product/123')
        ->call('saveDraft')
        ->assertHasNoErrors();

    $link = $product->fresh('marketplaceLinks')->marketplaceLinks->sole();

    expect($link->platform)->toBe('shopee')
        ->and($link->url)->toBe('https://shopee.com.my/product/123');
});

it('refuses to save a product whose marketplace link is not an allow-listed host', function () {
    $seller = marketplaceSeller();
    $product = marketplaceProduct($seller);

    Livewire::actingAs($seller)
        ->test(Form::class, ['product' => $product])
        ->call('addMarketplaceLink')
        ->set('marketplaceLinks.0.url', 'https://shopee.com.my@evil.com/product/1')
        ->call('saveDraft')
        ->assertHasErrors('marketplaceLinks.0.url');

    expect($product->fresh('marketplaceLinks')->marketplaceLinks)->toBeEmpty();
});

it('refuses two links for the same platform', function () {
    $seller = marketplaceSeller();
    $product = marketplaceProduct($seller);

    Livewire::actingAs($seller)
        ->test(Form::class, ['product' => $product])
        ->call('addMarketplaceLink')
        ->set('marketplaceLinks.0.url', 'https://shopee.com.my/product/1')
        ->call('addMarketplaceLink')
        ->set('marketplaceLinks.1.url', 'https://shopee.com.my/product/2')
        ->call('saveDraft')
        ->assertHasErrors('marketplaceLinks.1.url');

    expect($product->fresh('marketplaceLinks')->marketplaceLinks)->toBeEmpty();
});

it('drops a link the seller removed', function () {
    $seller = marketplaceSeller();
    $product = marketplaceProduct($seller);
    $product->marketplaceLinks()->create(['platform' => 'shopee', 'url' => 'https://shopee.com.my/p/1', 'position' => 0]);

    Livewire::actingAs($seller)
        ->test(Form::class, ['product' => $product])
        ->assertSet('marketplaceLinks.0.url', 'https://shopee.com.my/p/1')
        ->call('removeMarketplaceLink', 0)
        ->call('saveDraft')
        ->assertHasNoErrors();

    expect($product->fresh('marketplaceLinks')->marketplaceLinks)->toBeEmpty();
});

// ── Shopper display ─────────────────────────────────────────────────────

it('shows the outbound link on the product page while the marketplace is listing-only', function () {
    $seller = marketplaceSeller();
    $product = marketplaceProduct($seller);
    $product->marketplaceLinks()->create(['platform' => 'shopee', 'url' => 'https://shopee.com.my/p/1', 'position' => 0]);
    setPurchasing(false);

    $this->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertSee('Also available on')
        ->assertSee('Buy on Shopee')
        ->assertSee('https://shopee.com.my/p/1', false);
});

it('carries the outbound rel and target so the destination cannot reach back', function () {
    $seller = marketplaceSeller();
    $product = marketplaceProduct($seller);
    $product->marketplaceLinks()->create(['platform' => 'shopee', 'url' => 'https://shopee.com.my/p/1', 'position' => 0]);
    setPurchasing(false);

    $this->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertSee('rel="noopener noreferrer nofollow ugc"', false)
        ->assertSee('target="_blank"', false);
});

it('hides the link once our own checkout is back on, unless the seller opted in', function () {
    $seller = marketplaceSeller();
    $product = marketplaceProduct($seller);
    $product->marketplaceLinks()->create(['platform' => 'shopee', 'url' => 'https://shopee.com.my/p/1', 'position' => 0]);
    setPurchasing(true);

    // CONTROL: the link exists and the page renders — it is the visibility rule
    // that hides it, not an empty relation.
    $this->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertDontSee('Buy on Shopee');

    $product->update(['marketplace_links_always_visible' => true]);

    $this->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertSee('Buy on Shopee');
});

it('shows nothing at all when a product has no links', function () {
    $seller = marketplaceSeller();
    $product = marketplaceProduct($seller);
    setPurchasing(false);

    $this->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertSee('Listing only')          // the page is in the state under test
        ->assertDontSee('Also available on');
});

it('translates the shopper-facing copy into Malay and Vietnamese', function () {
    app()->setLocale('ms');
    expect(__('Also available on'))->toBe('Turut tersedia di')
        ->and(__('Buy on :platform', ['platform' => 'Shopee']))->toBe('Beli di Shopee');

    app()->setLocale('vi');
    expect(__('Also available on'))->toBe('Cũng có trên')
        ->and(__('Buy on :platform', ['platform' => 'Shopee']))->toBe('Mua trên Shopee');
});

// ── Ownership ───────────────────────────────────────────────────────────

it('does not let a seller attach a link to someone else\'s product', function () {
    $owner = marketplaceSeller();
    $intruder = marketplaceSeller();
    $product = marketplaceProduct($owner);
    $product->marketplaceLinks()->create(['platform' => 'shopee', 'url' => 'https://shopee.com.my/p/1', 'position' => 0]);

    // CONTROL: the owner can open the form and sees their own link.
    Livewire::actingAs($owner)
        ->test(Form::class, ['product' => $product])
        ->assertOk()
        ->assertSet('marketplaceLinks.0.url', 'https://shopee.com.my/p/1');

    Livewire::actingAs($intruder)
        ->test(Form::class, ['product' => $product])
        ->assertForbidden();
});
