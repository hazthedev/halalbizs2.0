<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// C-1: the demo sellers are email-verified and force-approved, and their emails
// are derived from store names printed on the storefront. Their password used to
// be the literal 'password', re-applied on EVERY deploy. If that ever comes back,
// this fails.
test('the demo catalogue never seeds a guessable seller password', function () {
    $this->seed([Database\Seeders\RoleSeeder::class, Database\Seeders\HalalCatalogueSeeder::class]);

    $sellers = User::query()->where('email', 'like', '%@halalbizs.test')->get();

    expect($sellers)->not->toBeEmpty();

    foreach ($sellers as $seller) {
        expect(Hash::check('password', $seller->password))->toBeFalse(
            "{$seller->email} still accepts the password 'password'"
        );
    }
});
