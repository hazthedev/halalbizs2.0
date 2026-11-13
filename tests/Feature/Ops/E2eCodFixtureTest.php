<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Spatie\Permission\Models\Role;

test('the local COD fixture creates a product and can be run again', function () {
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
    Category::factory()->create();

    $environment = app()->environment();
    app()['env'] = 'local';
    try {
        $this->artisan('e2e:cod-fixture')->expectsOutput('cod-journey-widget')->assertSuccessful();
        $this->artisan('e2e:cod-fixture')->expectsOutput('cod-journey-widget')->assertSuccessful();
    } finally {
        app()['env'] = $environment;
    }
});

test('the COD fixture uses the selected seller and clears only the selected buyer cart', function () {
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
    Category::factory()->create();
    $selected = User::factory()->create(['email' => 'e2e-selected@halalbizs.test']);
    $other = User::factory()->create(['email' => 'buyer@halalbizs.test']);
    $variant = Product::factory()->create()->variants()->firstOrFail();
    foreach ([$selected, $other] as $buyer) {
        $buyer->cart()->create()->items()->create(['product_variant_id' => $variant->id, 'qty' => 1]);
    }

    $environment = app()->environment();
    app()['env'] = 'local';
    try {
        $this->artisan('e2e:cod-fixture', [
            '--seller' => 'e2e-seller@halalbizs.test', '--buyer' => $selected->email,
        ])->assertSuccessful();
        expect(User::where('email', 'e2e-seller@halalbizs.test')->firstOrFail()->store)->not->toBeNull()
            ->and($selected->cart->items()->count())->toBe(0)
            ->and($other->cart->items()->count())->toBe(1);
    } finally {
        app()['env'] = $environment;
    }
});
