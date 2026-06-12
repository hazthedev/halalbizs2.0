<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@halalbizs.test')],
            [
                'name' => 'Platform Admin',
                'password' => env('ADMIN_PASSWORD', 'password'),
                'email_verified_at' => now(),
            ],
        );

        // Admin accounts must carry 2FA (EnsureAdmin); email-code method by
        // default — codes land in the mail log locally.
        if ($admin->two_factor_method === null) {
            $admin->forceFill(['two_factor_method' => 'email'])->save();
        }

        $admin->syncRoles(['admin']);
    }
}
