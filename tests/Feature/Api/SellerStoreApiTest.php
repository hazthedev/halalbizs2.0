<?php

use App\Support\MalaysianStates;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/SellerApiHelpers.php';

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('GET seller/store returns the store with a masked bank account number', function () {
    $seller = sellerApiUser();
    $store = $seller->store;
    $store->update(['bank_details' => ['bank_name' => 'Maybank', 'account_name' => 'Kedai Nyet', 'account_number' => '1234567890123'], 'shipping_flat_fee_sen' => 800]);

    $response = sellerApiAs($seller)->getJson('/api/v1/seller/store')
        ->assertOk()
        ->assertJsonPath('data.id', $store->id)
        ->assertJsonPath('data.slug', $store->slug)
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.logo', null)
        ->assertJsonPath('data.shipping.mode', 'flat')
        ->assertJsonPath('data.shipping.flat_fee_sen', 800)
        ->assertJsonPath('data.bank.bank_name', 'Maybank')
        ->assertJsonPath('data.bank.account_no', '*********0123');

    expect($response->json('data'))->toHaveKeys(['name', 'description', 'state', 'status_label', 'holiday_mode', 'banner', 'created_at'])
        ->and(json_encode($response->json('data')))->not->toContain('1234567890123');
});

test('PATCH seller/store saves profile, shipping and bank with the settings rules', function () {
    $seller = sellerApiUser();

    sellerApiAs($seller)->patchJson('/api/v1/seller/store', [
        'description' => 'Homemade sambal, cooked weekly.',
        'state' => 'Selangor',
        'holiday_mode' => true,
        'shipping_mode' => 'flat',
        'shipping_flat_fee_sen' => 650,
        'free_shipping_over_sen' => 8000,
        'bank' => ['bank_name' => 'CIMB', 'account_name' => 'Nur Aisyah', 'account_number' => '87654321'],
        'name' => 'Not editable',
    ])
        ->assertOk()
        ->assertJsonPath('data.description', 'Homemade sambal, cooked weekly.')
        ->assertJsonPath('data.state', 'Selangor')
        ->assertJsonPath('data.holiday_mode', true)
        ->assertJsonPath('data.shipping.flat_fee_sen', 650)
        ->assertJsonPath('data.shipping.free_above_sen', 8000)
        ->assertJsonPath('data.bank.bank_name', 'CIMB')
        ->assertJsonPath('data.bank.account_no', '****4321');

    $store = $seller->store->fresh();
    expect($store->bank_details['account_number'])->toBe('87654321')
        ->and($store->name)->not->toBe('Not editable');

    sellerApiAs($seller)->patchJson('/api/v1/seller/store', ['state' => 'Nowhere', 'bank' => ['bank_name' => 'CIMB', 'account_name' => 'X', 'account_number' => '12-34']])
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['fields' => ['state', 'bank.account_number']]]);

    sellerApiAs($seller)->patchJson('/api/v1/seller/store', ['shipping_mode' => 'matrix', 'shipping_matrix' => ['Selangor' => 500]])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');

    $matrix = array_fill_keys(MalaysianStates::ALL, 900);

    sellerApiAs($seller)->patchJson('/api/v1/seller/store', ['shipping_mode' => 'matrix', 'shipping_matrix' => $matrix])
        ->assertOk()
        ->assertJsonPath('data.shipping.mode', 'matrix')
        ->assertJsonPath('data.shipping.matrix.Sabah', 900);
});

test('POST seller/store/logo and /banner replace the single-file collections', function () {
    Storage::fake('public');

    $seller = sellerApiUser();

    $logo = sellerApiAs($seller)->postJson('/api/v1/seller/store/logo', ['logo' => UploadedFile::fake()->image('logo.png', 200, 200)])
        ->assertOk()
        ->json('data.logo');

    expect($logo)->toBeString()->toContain('logo-logo');

    sellerApiAs($seller)->postJson('/api/v1/seller/store/logo', ['logo' => UploadedFile::fake()->image('logo2.png', 200, 200)])->assertOk();

    expect($seller->store->fresh()->getMedia('logo'))->toHaveCount(1);

    sellerApiAs($seller)->postJson('/api/v1/seller/store/banner', ['banner' => UploadedFile::fake()->create('notes.txt', 5, 'text/plain')])
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['fields' => ['banner']]]);

    sellerApiAs($seller)->postJson('/api/v1/seller/store/banner', ['banner' => UploadedFile::fake()->image('wide.jpg', 1200, 400)])
        ->assertOk()
        ->assertJsonPath('data.logo', fn ($url) => is_string($url))
        ->assertJsonPath('data.banner', fn ($url) => is_string($url));
});
