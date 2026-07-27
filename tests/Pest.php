<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Make a user a full-access admin.
 *
 * The `admin` role carries NO permissions (bug #1 — it used to carry all eight,
 * which defeated the per-section can: gates). So a test that just assigns the
 * role now gets an admin who is 403 everywhere, which is correct behaviour and
 * almost never what the test means.
 *
 * Use this for "an admin does X" tests. For tests ABOUT scoping, assign the
 * role and call syncPermissions() with the exact subset under test instead.
 */
function makeAdmin(User $user): User
{
    $user->assignRole('admin');
    $user->syncPermissions(RoleSeeder::ADMIN_PERMISSIONS);

    return $user->fresh();
}
