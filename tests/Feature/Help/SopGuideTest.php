<?php

use App\Models\Store;
use App\Models\User;
use App\Support\SopGuide;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Role;

// HB-SOP: the buyer, seller and admin guides in resources/sop, shown as pages.

test('guests can open the help hub and the buyer and seller guides', function () {
    $this->get('/help')->assertOk()
        ->assertSee(route('help.guide', 'buyer'))
        ->assertSee(route('help.guide', 'seller'));

    $this->get('/help/buyer')->assertOk()->assertSee('HalalBizs Buyer Guide')->assertSee('Contents');
    $this->get('/help/seller')->assertOk()->assertSee('HalalBizs Seller Guide');
});

test('the admin guide is not a public guide', function () {
    $this->get('/help/admin')->assertNotFound();
});

test('a guest on the admin guide is sent to the admin sign-in', function () {
    $this->get('/admin/help')->assertRedirect(route('admin.login'));
});

test('an admin can read the admin guide, linked from the sidebar', function () {
    $this->seed(RoleSeeder::class);
    $admin = makeAdmin(User::factory()->create());

    $this->actingAs($admin)->get('/admin/help')
        ->assertOk()
        ->assertSee('HalalBizs Admin Guide')
        ->assertSee(route('admin.help'));
});

test('a Vietnamese visitor gets the Vietnamese guide', function () {
    $this->withSession(['locale' => 'vi'])->get('/help/buyer')
        ->assertOk()
        ->assertSee('Hướng dẫn dành cho người mua trên HalalBizs')
        ->assertDontSee('HalalBizs Buyer Guide');
});

test('no reviewer marker reaches any rendered guide', function () {
    foreach (['buyer', 'seller', 'admin'] as $guide) {
        foreach (['en', 'vi'] as $locale) {
            $html = SopGuide::load($guide, $locale)['html'];

            expect($html)->not->toContain('CHECK:')
                ->and($html)->not->toContain('SHOT:')
                ->and($html)->not->toContain('<!--');
        }
    }

    $this->get('/help/seller')->assertDontSee('CHECK:', false)->assertDontSee('SHOT:', false);
});

test('raw html written into a guide is stripped', function () {
    $guide = SopGuide::render("# Title\n\n## One\n\n1. Step <script>alert(1)</script> done\n\n<script>alert(2)</script>\n\n[x](javascript:alert(3))\n");

    expect($guide['title'])->toBe('Title')
        ->and($guide['html'])->not->toContain('<script')
        ->and($guide['html'])->not->toContain('javascript:')
        ->and($guide['toc'])->toBe([['id' => 'section-1', 'title' => 'One']])
        ->and($guide['html'])->toContain('<h2 id="section-1">One</h2>');
});

test('the seller dashboard and the become-a-seller page link the seller guide', function () {
    $this->actingAs(User::factory()->create())->get(route('seller.apply'))->assertOk()->assertSee(route('help.guide', 'seller'));

    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
    $seller = User::factory()->create();
    $seller->assignRole('seller');
    Store::factory()->approved()->create(['user_id' => $seller->id]);

    $this->actingAs($seller)->get(route('seller.dashboard'))->assertOk()->assertSee(route('help.guide', 'seller'));
});
