<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public const ADMIN_PERMISSIONS = [
        'sellers.manage',
        'products.moderate',
        'orders.manage',
        'finance.manage',
        'vouchers.manage',
        'cms.manage',
        'settings.manage',
        'localization.manage',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ADMIN_PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'buyer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);

        // The `admin` role deliberately holds NO permissions. It means exactly
        // one thing: "may pass EnsureAdmin and reach /admin". Every section is
        // gated on a per-person permission granted from the Staff screen.
        //
        // It used to hold all eight, which silently defeated that gating —
        // spatie resolves can() as (role permissions ∪ direct permissions), so a
        // products-only admin still cleared can:finance.manage. Bug #1.
        //
        // This MUST stay here rather than being fixed by hand in the database:
        // deploy.sh re-runs RoleSeeder on every deploy, so an out-of-band fix
        // would be silently re-granted on the next push.
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions([]);
    }
}
