<?php

/**
 * AL-C5 (audit 2026-07-27): the admin route group carries 'verified', so an
 * admin whose email address was never proven cannot reach the panel — email-
 * method 2FA would otherwise deliver codes to an unproven address.
 */

use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    test()->seed(RoleSeeder::class);
});

function verifiedGateAdmin(bool $verified): User
{
    $factory = User::factory()->state(['two_factor_method' => 'email']);

    if (! $verified) {
        $factory = $factory->unverified();
    }

    $user = $factory->create();
    $user->assignRole('admin');
    $user->forceFill(['is_superadmin' => true])->save();

    return $user->fresh();
}

it('turns an unverified admin away from the panel', function () {
    $this->actingAs(verifiedGateAdmin(verified: false))
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('verification.notice'));
});

it('lets a verified admin through', function () {
    $this->actingAs(verifiedGateAdmin(verified: true))
        ->get(route('admin.dashboard'))
        ->assertOk();
});
