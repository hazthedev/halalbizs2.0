<?php

use App\Models\Banner;
use App\Models\HelpArticle;
use App\Models\Page;
use App\Models\Store;
use App\Models\User;
use App\Models\Voucher;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('admin list links to standalone forms under the same permission gate', function (string $route, string $model, string $permission, array $attributes) {
    $record = $model::create($attributes);
    $this->actingAs(contentPublisher())->get(route($route))->assertOk()
        ->assertSee(route($route.'.create'), false)->assertSee(route($route.'.edit', $record), false)
        ->assertDontSee('wire:submit="save"', false);
    $this->get(route($route.'.create'))->assertOk()->assertSee('wire:submit="save"', false)->assertDontSee('<table', false)->assertSee(route($route), false);
    $this->get(route($route.'.edit', $record))->assertOk()->assertSee('wire:submit="save"', false)->assertDontSee('<table', false)->assertSee(route($route), false);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    foreach ([$route.'.create' => [], $route.'.edit' => $record] as $name => $parameters) {
        $this->actingAs($admin)->get(route($name, $parameters))->assertForbidden();
    }
    $admin->givePermissionTo($permission);
    $this->get(route($route.'.create'))->assertOk();
    $this->get(route($route.'.edit', $record))->assertOk();
})->with([
    'banners' => ['admin.content.banners', Banner::class, 'cms.manage', ['title' => ['en' => 'Existing banner'], 'position' => 1, 'is_active' => true]],
    'pages' => ['admin.content.pages', Page::class, 'cms.manage', ['slug' => 'custom-page', 'title' => ['en' => 'Existing page'], 'body' => ['en' => '<p>Body</p>'], 'is_active' => true]],
    'articles' => ['admin.support.articles', HelpArticle::class, 'cms.manage', ['category' => 'buying', 'title' => ['en' => 'Existing article'], 'body' => ['en' => '<p>Body</p>'], 'position' => 0, 'is_active' => true]],
    'vouchers' => ['admin.content.vouchers', Voucher::class, 'vouchers.manage', ['scope' => 'platform', 'code' => 'PLATFORM', 'type' => 'fixed', 'value_sen' => 1000, 'starts_at' => '2026-11-18', 'ends_at' => '2026-12-18', 'is_active' => true]],
]);

test('platform voucher edit refuses shop vouchers and buyer prizes', function () {
    $this->actingAs(contentPublisher());
    $fields = ['scope' => 'platform', 'type' => 'fixed', 'value_sen' => 1000, 'starts_at' => now(), 'ends_at' => now()->addWeek()];
    $prize = Voucher::create([...$fields, 'code' => 'PRIZE', 'user_id' => User::factory()->create()->id]);
    $shop = Voucher::create([...$fields, 'scope' => 'shop', 'code' => 'SHOP', 'store_id' => Store::factory()->approved()->create()->id]);
    $this->get(route('admin.content.vouchers.edit', $prize))->assertNotFound();
    $this->get(route('admin.content.vouchers.edit', $shop))->assertNotFound();
});
