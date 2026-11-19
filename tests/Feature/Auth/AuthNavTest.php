<?php

use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

test('login header offers only Create account, no Start Selling', function () {
    $html = test()->get(route('login'))->assertOk()->getContent();
    $header = substr($html, strpos($html, '<header'), strpos($html, '</header>') - strpos($html, '<header'));
    expect($header)->toContain('Create account')->toContain(route('register'))->not->toContain('Start Selling')->not->toContain('Log in');
});

test('register header offers only Log in, no Start Selling', function () {
    $html = test()->get(route('register'))->assertOk()->getContent();
    $header = substr($html, strpos($html, '<header'), strpos($html, '</header>') - strpos($html, '<header'));
    expect($header)->toContain('Log in')->not->toContain('Start Selling')->not->toContain('Create account');
});

test('another landing page keeps Start Selling', function () {
    test()->get(route('seller.login'))->assertOk()->assertSee('Start Selling');
});

test('storefront footer links Sell on HalalBizs to the seller sign-in', function () {
    test()->get(route('home'))->assertOk()->assertSee('href="'.route('seller.login').'"', false);
});

test('register submit button holds exactly one spinner', function () {
    $html = Livewire::test(\App\Livewire\Storefront\Auth\Register::class)->html();
    expect(substr_count($html, 'animate-spin'))->toBe(1);
});

test('reset password submit button holds exactly one spinner', function () {
    $html = Livewire::test(\App\Livewire\Storefront\Auth\ResetPassword::class, ['token' => 'x'])->html();
    expect(substr_count($html, 'animate-spin'))->toBe(1);
});

test('storefront top bar no longer shows Sell on HalalBizs', function () {
    $html = test()->get(route('home'))->assertOk()->getContent();
    $bar = substr($html, 0, strpos($html, '<header'));
    expect($bar)->not->toContain('Sell on HalalBizs');
});

test('footer Sell on HalalBizs leads each visitor somewhere that works', function () {
    test()->seed(RoleSeeder::class);

    // guest
    test()->get(route('home'))->assertSee('href="'.route('seller.login').'"', false);
    test()->get(route('seller.login'))->assertOk();

    // signed-in buyer: the old seller.login link bounced to /
    $buyer = User::factory()->create();
    test()->actingAs($buyer)->get(route('seller.login'))->assertRedirect('/');
    $html = test()->actingAs($buyer)->get(route('home'))->getContent();
    expect($html)->toContain('href="'.route('seller.apply').'"')->not->toContain('href="'.route('seller.login').'"');
    test()->actingAs($buyer)->get(route('seller.apply'))->assertOk();

    // approved seller
    $seller = User::factory()->create();
    $seller->assignRole('seller');
    Store::factory()->approved()->create(['user_id' => $seller->id]);
    $html = test()->actingAs($seller)->get(route('home'))->getContent();
    expect($html)->toContain('href="'.route('seller.dashboard').'"');
    test()->actingAs($seller)->get(route('seller.dashboard'))->assertOk();
});
