<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@halalbizs.test')],
            [
                'name' => 'Platform Admin',
                // No literal fallback in production: a host that forgets
                // ADMIN_PASSWORD gets an unguessable one it must reset, not the
                // string 'password' on the account that can reach every screen.
                'password' => env('ADMIN_PASSWORD') ?: (app()->isProduction() ? Str::password(20, symbols: false) : 'password'),
                'email_verified_at' => now(),
            ],
        );

        // Admin accounts must carry 2FA (EnsureAdmin); email-code method by
        // default — codes land in the mail log locally.
        if ($admin->two_factor_method === null) {
            $admin->forceFill(['two_factor_method' => 'email'])->save();
        }

        $admin->syncRoles(['admin']);

        // The primary admin is the superadmin, so a fresh install always has
        // exactly one account that can reach the Staff screen and grant the
        // section permissions to everyone else. Without this the emptied `admin`
        // role would leave nobody able to hand out permissions at all.
        if (! $admin->is_superadmin) {
            $admin->forceFill(['is_superadmin' => true])->save();
        }
    }
}
