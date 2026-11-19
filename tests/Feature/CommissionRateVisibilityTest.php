<?php

use App\Livewire\Admin\Catalog\Categories;
use App\Livewire\Admin\Finance\Commission;
use App\Livewire\Admin\Sellers\StoreDetail;
use App\Livewire\Seller\Apply;
use App\Livewire\Seller\Settings;
use App\Models\Category;
use App\Models\Store;
use App\Models\User;
use App\Settings\CommissionSettings;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $settings = app(CommissionSettings::class);
    $settings->global_rate = 6.0;
    $settings->save();
});

function commissionSeller(?float $override = null): User
{
    $user = User::factory()->create();
    $user->assignRole('seller');
    Store::factory()->approved()->create(['user_id' => $user->id, 'commission_rate' => $override]);

    return $user;
}

function commissionAdmin(): User
{
    $user = User::factory()->create(['two_factor_method' => 'email']);
    makeAdmin($user);

    return $user;
}

test('apply page shows the global commission rate', function () {
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');

    Livewire::actingAs($buyer)->test(Apply::class)->assertSee('6%')->assertSee(__('Commission'));
});

test('apply page shows a range when categories differ', function () {
    Category::factory()->create(['commission_rate' => 10.0]);
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');

    Livewire::actingAs($buyer)->test(Apply::class)->assertSee('6%')->assertSee('10%')->assertSee(__('by category'));
});

test('seller settings shows the store override rate', function () {
    Category::factory()->create(['commission_rate' => 10.0]);

    Livewire::actingAs(commissionSeller(4.5))->test(Settings::class)->assertSee('4.5%')->assertDontSee('10%');
});

test('seller settings shows the global rate without an override', function () {
    Livewire::actingAs(commissionSeller())->test(Settings::class)->assertSee('6%');
});

test('changing the global rate writes one audit record with old and new', function () {
    Livewire::actingAs($admin = commissionAdmin())
        ->test(Commission::class)
        ->set('globalRate', '7.25')
        ->call('saveGlobalRate');

    $log = Activity::query()->where('log_name', 'commission')->sole();
    expect($log->causer_id)->toBe($admin->id)
        ->and($log->properties['old'])->toBe('6')
        ->and($log->properties['new'])->toBe('7.25');
});

test('changing a category rate writes one audit record with old and new', function () {
    $category = Category::factory()->create(['commission_rate' => 8.0]);

    Livewire::actingAs(commissionAdmin())
        ->test(Categories::class)
        ->call('edit', $category->id)
        ->set('commissionRate', '9')
        ->call('save');

    $log = Activity::query()->where('log_name', 'commission')->sole();
    expect($log->subject_id)->toBe($category->id)
        ->and($log->properties['old'])->toBe('8')
        ->and($log->properties['new'])->toBe('9');
});

test('changing a store override writes one audit record with old and new', function () {
    $store = Store::factory()->approved()->create(['commission_rate' => 3.0]);

    Livewire::actingAs(commissionAdmin())
        ->test(StoreDetail::class, ['store' => $store])
        ->set('commissionRate', '4')
        ->call('saveCommission');

    $log = Activity::query()->where('subject_id', $store->id)->where('subject_type', $store->getMorphClass())->where('event', 'updated')->sole();
    expect($log->attribute_changes['old']['commission_rate'])->toBe('3.00')
        ->and($log->attribute_changes['attributes']['commission_rate'])->toBe('4.00');
});
