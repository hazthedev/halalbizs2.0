<?php

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
