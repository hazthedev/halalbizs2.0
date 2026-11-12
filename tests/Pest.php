<?php

use App\Enums\LedgerEntryStatus;
use App\Models\ContentVersion;
use App\Models\StoreLedgerEntry;
use App\Models\User;
use App\Services\ContentDrafts;
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

/**
 * CMS Tier 2: a content save is a draft until a publisher publishes it.
 * Tests about what shoppers end up seeing call this after the save.
 */
function publishDrafts(): void
{
    $publisher = User::factory()->create();

    ContentVersion::where('status', 'draft')->get()
        ->each(fn (ContentVersion $draft) => app(ContentDrafts::class)->publish($draft->subject, $publisher));
}

/**
 * Completion books seller money as `pending` until the return window closes.
 * Tests that exercise post-hold accounting call this to skip the wait.
 */
function releaseLedgerHolds(): void
{
    StoreLedgerEntry::query()
        ->where('status', LedgerEntryStatus::Pending)
        ->update(['status' => LedgerEntryStatus::Available]);
}
