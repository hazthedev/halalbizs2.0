<?php

use App\Enums\StoreStatus;
use App\Models\User;
use Database\Seeders\RoleSeeder;

require_once __DIR__.'/SellerApiHelpers.php';

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('a buyer without a store gets a 403 seller_required envelope', function () {
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');

    sellerApiAs($buyer)->getJson('/api/v1/seller/dashboard')
        ->assertStatus(403)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error.code', 'seller_required');
});

test('a pending store and a store without the seller role are both seller_required', function () {
    sellerApiAs(sellerApiUser(StoreStatus::Pending))->getJson('/api/v1/seller/orders')
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'seller_required');

    sellerApiAs(sellerApiUser(StoreStatus::Approved, withRole: false))->getJson('/api/v1/seller/orders')
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'seller_required');
});

test('a suspended store gets its own store_suspended code', function () {
    sellerApiAs(sellerApiUser(StoreStatus::Suspended))->getJson('/api/v1/seller/store')
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'store_suspended');
});

test('a guest is 401 and an approved seller passes the guard', function () {
    $this->getJson('/api/v1/seller/store')
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'unauthenticated');

    sellerApiAs(sellerApiUser())->getJson('/api/v1/seller/store')
        ->assertOk()
        ->assertJsonPath('ok', true);
});
