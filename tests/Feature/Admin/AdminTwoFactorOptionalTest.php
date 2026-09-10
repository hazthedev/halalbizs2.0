<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

// Owner decision 2026-09-10: 2FA is optional for admins. EnsureAdmin used to
// park an admin without 2FA on the profile security tab; it must not any more.

test('an admin without 2FA reaches the admin dashboard', function () {
    $user = User::factory()->create(['two_factor_method' => null]);
    makeAdmin($user);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk();
});

test('an admin with 2FA still reaches the admin dashboard', function () {
    $user = User::factory()->create(['two_factor_method' => 'email']);
    makeAdmin($user);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk();
});

test('a non-admin still gets 403 on the admin dashboard', function () {
    $user = User::factory()->create(['two_factor_method' => null]);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});
