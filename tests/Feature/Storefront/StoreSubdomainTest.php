<?php

use App\Models\Store;
use App\Models\UrlRedirect;
use Database\Seeders\RoleSeeder;

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('storefrontUrl uses the /s/ path by default and the subdomain when enabled', function () {
    $store = Store::factory()->approved()->create(['name' => 'Link Mode Shop']);

    config(['app.store_subdomains_enabled' => false]);
    expect($store->storefrontUrl())->toBe(route('store.show', $store->slug))
        ->and($store->storefrontUrl())->toContain('/s/'.$store->slug);

    config(['app.store_subdomains_enabled' => true]);
    expect($store->storefrontUrl())->toBe($store->subdomainUrl())
        ->and($store->storefrontUrl())->toContain($store->slug.'.'.config('app.store_subdomain_base'));
});

test('an approved store resolves on its own subdomain', function () {
    $store = Store::factory()->approved()->create(['name' => 'Kedai Subdomain']);

    $this->get('http://'.$store->slug.'.'.config('app.store_subdomain_base'))
        ->assertOk()
        ->assertSee('Kedai Subdomain');
});

test('pending stores and unknown subdomains 404', function () {
    $pending = Store::factory()->create();

    $this->get('http://'.$pending->slug.'.'.config('app.store_subdomain_base'))->assertNotFound();
    $this->get('http://no-such-shop.'.config('app.store_subdomain_base'))->assertNotFound();
});

test('reserved slugs are deflected on save', function () {
    $store = Store::factory()->approved()->create(['name' => 'Admin']);

    expect($store->slug)->toBe('admin-shop');
});

test('a renamed store redirects from its old subdomain', function () {
    $store = Store::factory()->approved()->create(['name' => 'Old Name Shop']);
    $oldSlug = $store->slug;

    $store->update(['name' => 'Fresh Name Shop', 'slug' => 'fresh-name-shop']);

    expect(UrlRedirect::where('old_path', "/s/{$oldSlug}")->exists())->toBeTrue();

    $this->get('http://'.$oldSlug.'.'.config('app.store_subdomain_base'))
        ->assertRedirect('http://fresh-name-shop.'.config('app.store_subdomain_base'));
});

test('the path route still works as a fallback', function () {
    $store = Store::factory()->approved()->create();

    $this->get('/s/'.$store->slug)->assertOk();
});
