<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Superadmin flag (bug #1, admin least-privilege).
 *
 * The `admin` role no longer carries the eight section permissions — it only
 * means "may reach /admin". Permissions are per-person again, which is what the
 * Staff screen always intended. Someone still has to sit above that system, or
 * the first person to lose settings.manage locks everyone out of Staff itself.
 *
 * That someone is a superadmin: a property of the ACCOUNT, grantable from the
 * panel (Haze's call 2026-07-27 — promoting staff should be a panel operation,
 * not a server one). A Gate::before turns the flag into "passes every check".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_superadmin')->default(false)->after('status');
        });

        $this->promoteAnExistingAdmin();
    }

    /**
     * Promote one existing admin, or this migration bricks every live install.
     *
     * deploy.sh runs `migrate --force` and then re-seeds RoleSeeder — which now
     * empties the admin role. On an existing database nobody holds a DIRECT
     * settings.manage grant (permissions have always come from the role), so
     * the moment the role is emptied the Staff screen becomes unreachable and
     * there is no way back in through the UI. AdminUserSeeder would fix it, but
     * deploy.sh deliberately does not run it (it creates default-password
     * accounts). So the promotion has to happen here, before the seeder runs.
     *
     * Picks the ADMIN_EMAIL account if it holds the admin role, else the
     * longest-standing admin. A fresh install has no admins yet and needs
     * nothing — AdminUserSeeder covers that case.
     */
    private function promoteAnExistingAdmin(): void
    {
        $adminRoleId = DB::table('roles')
            ->where('name', 'admin')
            ->where('guard_name', 'web')
            ->value('id');

        if ($adminRoleId === null) {
            return; // fresh install — RoleSeeder + AdminUserSeeder run after this
        }

        $adminIds = DB::table('model_has_roles')
            ->where('role_id', $adminRoleId)
            ->where('model_type', User::class)
            ->pluck('model_id');

        if ($adminIds->isEmpty()) {
            return;
        }

        $primary = DB::table('users')
            ->whereIn('id', $adminIds)
            ->where('email', env('ADMIN_EMAIL', 'admin@halalbizs.test'))
            ->value('id')
            ?? DB::table('users')->whereIn('id', $adminIds)->orderBy('id')->value('id');

        DB::table('users')->where('id', $primary)->update(['is_superadmin' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_superadmin');
        });
    }
};
